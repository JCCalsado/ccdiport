<?php

namespace App\Http\Controllers;

use App\Models\PaymentGateway;
use App\Models\Transaction;
use App\Models\Payment;
use App\Models\Student;
use App\Services\AccountService;
use App\Notifications\PaymentSuccessNotification;
use App\Notifications\PaymentFailedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WebhookController extends Controller
{
    /**
     * ✅ Handle PayMongo webhook events
     */
    public function paymongo(Request $request)
    {
        try {
            // Get PayMongo gateway configuration
            $gateway = PaymentGateway::where('slug', 'paymongo')
                ->where('is_active', true)
                ->firstOrFail();

            // Verify webhook signature
            if (!$this->verifyPayMongoSignature($request, $gateway)) {
                Log::warning('PayMongo webhook signature verification failed', [
                    'ip' => $request->ip(),
                    'headers' => $request->headers->all(),
                ]);
                return response()->json(['error' => 'Invalid signature'], 401);
            }

            $payload = $request->all();
            $eventType = $payload['data']['attributes']['type'] ?? null;

            Log::info('PayMongo webhook received', [
                'event_type' => $eventType,
                'payment_id' => $payload['data']['attributes']['data']['id'] ?? null,
            ]);

            // Handle different event types
            switch ($eventType) {
                case 'payment.paid':
                    return $this->handlePayMongoPaymentPaid($payload, $gateway);
                
                case 'payment.failed':
                    return $this->handlePayMongoPaymentFailed($payload, $gateway);
                
                case 'source.chargeable':
                    return $this->handlePayMongoSourceChargeable($payload, $gateway);
                
                default:
                    Log::info('Unhandled PayMongo webhook event', ['type' => $eventType]);
                    return response()->json(['message' => 'Event received'], 200);
            }

        } catch (\Exception $e) {
            Log::error('PayMongo webhook error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * ✅ Handle GCash webhook events
     */
    public function gcash(Request $request)
    {
        try {
            $gateway = PaymentGateway::where('slug', 'gcash')
                ->where('is_active', true)
                ->firstOrFail();

            if (!$this->verifyGCashSignature($request, $gateway)) {
                Log::warning('GCash webhook signature verification failed');
                return response()->json(['error' => 'Invalid signature'], 401);
            }

            $payload = $request->all();
            $status = $payload['status'] ?? null;
            $referenceId = $payload['referenceId'] ?? null;

            Log::info('GCash webhook received', [
                'status' => $status,
                'reference_id' => $referenceId,
            ]);

            switch ($status) {
                case 'S':
                case 'SUCCESS':
                    return $this->handleGCashSuccess($payload, $gateway);
                
                case 'F':
                case 'FAILED':
                    return $this->handleGCashFailed($payload, $gateway);
                
                default:
                    return response()->json(['message' => 'Event received'], 200);
            }

        } catch (\Exception $e) {
            Log::error('GCash webhook error', [
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * ✅ Handle PayMongo payment.paid event
     */
    protected function handlePayMongoPaymentPaid(array $payload, PaymentGateway $gateway)
    {
        DB::beginTransaction();
        try {
            $paymentData = $payload['data']['attributes']['data'];
            $amount = $paymentData['attributes']['amount'] / 100; // Convert from cents
            $referenceId = $paymentData['attributes']['description'] ?? null;

            // Find transaction by reference
            $transaction = Transaction::where('reference', $referenceId)
                ->where('status', 'pending')
                ->first();

            if (!$transaction) {
                Log::warning('PayMongo webhook: Transaction not found', [
                    'reference' => $referenceId,
                ]);
                return response()->json(['message' => 'Transaction not found'], 404);
            }

            // Update transaction status
            $transaction->update([
                'status' => 'paid',
                'paid_at' => now(),
                'payment_channel' => 'paymongo',
                'meta' => array_merge($transaction->meta ?? [], [
                    'gateway_payment_id' => $paymentData['id'],
                    'gateway_response' => $paymentData,
                    'webhook_received_at' => now()->toISOString(),
                ]),
            ]);

            // Get student
            $student = Student::where('account_id', $transaction->account_id)->first();

            if ($student) {
                // Create Payment record
                Payment::create([
                    'account_id' => $student->account_id,
                    'student_id' => $student->id,
                    'amount' => $amount,
                    'payment_method' => 'paymongo',
                    'reference_number' => $transaction->reference,
                    'description' => 'Online payment via PayMongo',
                    'status' => Payment::STATUS_COMPLETED,
                    'paid_at' => now(),
                ]);

                // Recalculate account balance
                if ($student->user) {
                    AccountService::recalculate($student->user);
                }

                // Send success notification
                if ($student->user) {
                    $student->user->notify(new PaymentSuccessNotification([
                        'amount' => $amount,
                        'reference' => $transaction->reference,
                        'method' => 'PayMongo',
                        'balance' => abs($student->user->account->balance ?? 0),
                    ]));
                }

                Log::info('PayMongo payment processed successfully', [
                    'account_id' => $student->account_id,
                    'amount' => $amount,
                    'transaction_id' => $transaction->id,
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Payment processed successfully',
                'transaction_id' => $transaction->id,
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('PayMongo payment processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'Payment processing failed'], 500);
        }
    }

    /**
     * ✅ Handle PayMongo payment.failed event
     */
    protected function handlePayMongoPaymentFailed(array $payload, PaymentGateway $gateway)
    {
        DB::beginTransaction();
        try {
            $paymentData = $payload['data']['attributes']['data'];
            $referenceId = $paymentData['attributes']['description'] ?? null;
            $failureReason = $paymentData['attributes']['last_payment_error']['message'] ?? 'Unknown error';

            $transaction = Transaction::where('reference', $referenceId)
                ->where('status', 'pending')
                ->first();

            if (!$transaction) {
                return response()->json(['message' => 'Transaction not found'], 404);
            }

            // Update transaction status
            $transaction->update([
                'status' => 'failed',
                'meta' => array_merge($transaction->meta ?? [], [
                    'gateway_payment_id' => $paymentData['id'],
                    'failure_reason' => $failureReason,
                    'gateway_response' => $paymentData,
                    'webhook_received_at' => now()->toISOString(),
                ]),
            ]);

            // Get student and send failure notification
            $student = Student::where('account_id', $transaction->account_id)->first();

            if ($student && $student->user) {
                $student->user->notify(new PaymentFailedNotification([
                    'amount' => $transaction->amount,
                    'reference' => $transaction->reference,
                    'reason' => $failureReason,
                ]));
            }

            DB::commit();

            Log::info('PayMongo payment failed', [
                'account_id' => $transaction->account_id,
                'reason' => $failureReason,
            ]);

            return response()->json([
                'message' => 'Payment failure recorded',
                'transaction_id' => $transaction->id,
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('PayMongo failure processing error', [
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    /**
     * ✅ Handle PayMongo source.chargeable event
     */
    protected function handlePayMongoSourceChargeable(array $payload, PaymentGateway $gateway)
    {
        try {
            $sourceData = $payload['data']['attributes']['data'];
            $sourceId = $sourceData['id'];
            $amount = $sourceData['attributes']['amount'] / 100;

            // Find pending transaction
            $transaction = Transaction::where('meta->source_id', $sourceId)
                ->where('status', 'pending')
                ->first();

            if (!$transaction) {
                Log::warning('PayMongo source.chargeable: No matching transaction', [
                    'source_id' => $sourceId,
                ]);
                return response()->json(['message' => 'Transaction not found'], 404);
            }

            // Create payment using PayMongo API
            $this->createPayMongoPayment($sourceId, $amount, $transaction, $gateway);

            return response()->json(['message' => 'Payment created'], 200);

        } catch (\Exception $e) {
            Log::error('PayMongo source.chargeable error', [
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    /**
     * ✅ Handle GCash success
     */
    protected function handleGCashSuccess(array $payload, PaymentGateway $gateway)
    {
        DB::beginTransaction();
        try {
            $referenceId = $payload['referenceId'] ?? null;
            $amount = $payload['amount'] ?? 0;

            $transaction = Transaction::where('reference', $referenceId)
                ->where('status', 'pending')
                ->first();

            if (!$transaction) {
                return response()->json(['message' => 'Transaction not found'], 404);
            }

            // Update transaction
            $transaction->update([
                'status' => 'paid',
                'paid_at' => now(),
                'payment_channel' => 'gcash',
                'meta' => array_merge($transaction->meta ?? [], [
                    'gateway_response' => $payload,
                    'webhook_received_at' => now()->toISOString(),
                ]),
            ]);

            // Process payment
            $student = Student::where('account_id', $transaction->account_id)->first();

            if ($student) {
                Payment::create([
                    'account_id' => $student->account_id,
                    'student_id' => $student->id,
                    'amount' => $amount,
                    'payment_method' => 'gcash',
                    'reference_number' => $transaction->reference,
                    'description' => 'Online payment via GCash',
                    'status' => Payment::STATUS_COMPLETED,
                    'paid_at' => now(),
                ]);

                if ($student->user) {
                    AccountService::recalculate($student->user);

                    $student->user->notify(new PaymentSuccessNotification([
                        'amount' => $amount,
                        'reference' => $transaction->reference,
                        'method' => 'GCash',
                        'balance' => abs($student->user->account->balance ?? 0),
                    ]));
                }
            }

            DB::commit();

            return response()->json(['message' => 'Payment processed'], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('GCash payment processing failed', [
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    /**
     * ✅ Handle GCash failure
     */
    protected function handleGCashFailed(array $payload, PaymentGateway $gateway)
    {
        DB::beginTransaction();
        try {
            $referenceId = $payload['referenceId'] ?? null;
            $reason = $payload['message'] ?? 'Payment failed';

            $transaction = Transaction::where('reference', $referenceId)
                ->where('status', 'pending')
                ->first();

            if ($transaction) {
                $transaction->update([
                    'status' => 'failed',
                    'meta' => array_merge($transaction->meta ?? [], [
                        'failure_reason' => $reason,
                        'gateway_response' => $payload,
                    ]),
                ]);

                $student = Student::where('account_id', $transaction->account_id)->first();

                if ($student && $student->user) {
                    $student->user->notify(new PaymentFailedNotification([
                        'amount' => $transaction->amount,
                        'reference' => $transaction->reference,
                        'reason' => $reason,
                    ]));
                }
            }

            DB::commit();

            return response()->json(['message' => 'Payment failure recorded'], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('GCash failure processing error', [
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    /**
     * ✅ Verify PayMongo webhook signature
     */
    protected function verifyPayMongoSignature(Request $request, PaymentGateway $gateway): bool
    {
        $signature = $request->header('Paymongo-Signature');
        
        if (!$signature) {
            return false;
        }

        $webhookSecret = $gateway->config['webhook_secret'] ?? null;
        
        if (!$webhookSecret) {
            Log::error('PayMongo webhook secret not configured');
            return false;
        }

        $payload = $request->getContent();
        $computedSignature = hash_hmac('sha256', $payload, $webhookSecret);

        return hash_equals($computedSignature, $signature);
    }

    /**
     * ✅ Verify GCash webhook signature
     */
    protected function verifyGCashSignature(Request $request, PaymentGateway $gateway): bool
    {
        // GCash signature verification logic
        // Implementation depends on GCash API documentation
        $signature = $request->header('X-GCash-Signature');
        
        if (!$signature) {
            return false;
        }

        $webhookSecret = $gateway->config['webhook_secret'] ?? null;
        
        if (!$webhookSecret) {
            return false;
        }

        $payload = $request->getContent();
        $computedSignature = hash_hmac('sha256', $payload, $webhookSecret);

        return hash_equals($computedSignature, $signature);
    }

    /**
     * ✅ Create PayMongo payment from chargeable source
     */
    protected function createPayMongoPayment(string $sourceId, float $amount, Transaction $transaction, PaymentGateway $gateway)
    {
        $secretKey = $gateway->config['secret_key'] ?? null;

        if (!$secretKey) {
            throw new \Exception('PayMongo secret key not configured');
        }

        $client = new \GuzzleHttp\Client();
        
        $response = $client->post('https://api.paymongo.com/v1/payments', [
            'auth' => [$secretKey, ''],
            'json' => [
                'data' => [
                    'attributes' => [
                        'amount' => $amount * 100, // Convert to cents
                        'currency' => 'PHP',
                        'source' => [
                            'id' => $sourceId,
                            'type' => 'source',
                        ],
                        'description' => $transaction->reference,
                    ],
                ],
            ],
        ]);

        $result = json_decode($response->getBody(), true);

        Log::info('PayMongo payment created from source', [
            'payment_id' => $result['data']['id'],
            'transaction_id' => $transaction->id,
        ]);

        return $result;
    }

    /**
     * ✅ Test webhook endpoint (for development)
     */
    public function test(Request $request)
    {
        if (!app()->environment('local')) {
            abort(403, 'This endpoint is only available in local environment');
        }

        return response()->json([
            'message' => 'Webhook endpoint is working',
            'timestamp' => now()->toISOString(),
            'ip' => $request->ip(),
        ]);
    }
}