<?php

namespace App\Services;

use App\Models\PaymentGatewayConfig;
use App\Models\Transaction;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class PaymentGatewayService
{
    protected Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 30,
            'verify' => true,
        ]);
    }

    /**
     * Create payment based on gateway type
     */
    public function createPayment(
        PaymentGatewayConfig $gateway,
        Transaction $transaction,
        string $paymentMethod
    ): string {
        return match($gateway->gateway) {
            'paymongo' => $this->createPayMongoPayment($gateway, $transaction, $paymentMethod),
            'gcash' => $this->createGCashPayment($gateway, $transaction),
            'maya' => $this->createMayaPayment($gateway, $transaction),
            default => throw new \Exception("Unsupported gateway: {$gateway->gateway}")
        };
    }

    /**
     * Create PayMongo payment
     */
    protected function createPayMongoPayment(
        PaymentGatewayConfig $gateway,
        Transaction $transaction,
        string $paymentMethod
    ): string {
        try {
            $amount = (int)($transaction->amount * 100); // Convert to cents
            
            // Step 1: Create payment intent
            $response = $this->client->post('https://api.paymongo.com/v1/payment_intents', [
                'auth' => [$gateway->secret_key, ''],
                'json' => [
                    'data' => [
                        'attributes' => [
                            'amount' => $amount,
                            'payment_method_allowed' => [$paymentMethod],
                            'payment_method_options' => [
                                'card' => ['request_three_d_secure' => 'any']
                            ],
                            'currency' => 'PHP',
                            'description' => $transaction->reference,
                            'statement_descriptor' => 'CCDI Payment',
                        ]
                    ]
                ]
            ]);

            $intent = json_decode($response->getBody(), true);
            $intentId = $intent['data']['id'];
            $clientKey = $intent['data']['attributes']['client_key'];

            // Step 2: Create payment method
            $pmResponse = $this->client->post('https://api.paymongo.com/v1/payment_methods', [
                'auth' => [$gateway->public_key, ''],
                'json' => [
                    'data' => [
                        'attributes' => [
                            'type' => $paymentMethod,
                            'billing' => [
                                'name' => $transaction->user->name ?? 'Student',
                                'email' => $transaction->user->email ?? 'student@ccdi.edu.ph',
                            ]
                        ]
                    ]
                ]
            ]);

            $paymentMethodData = json_decode($pmResponse->getBody(), true);
            $paymentMethodId = $paymentMethodData['data']['id'];

            // Step 3: Attach payment method to intent
            $attachResponse = $this->client->post(
                "https://api.paymongo.com/v1/payment_intents/{$intentId}/attach",
                [
                    'auth' => [$gateway->secret_key, ''],
                    'json' => [
                        'data' => [
                            'attributes' => [
                                'payment_method' => $paymentMethodId,
                                'client_key' => $clientKey,
                                'return_url' => route('student.payment.success', $transaction->id),
                            ]
                        ]
                    ]
                ]
            );

            $result = json_decode($attachResponse->getBody(), true);
            
            // Update transaction with gateway data
            $transaction->update([
                'meta' => array_merge($transaction->meta ?? [], [
                    'gateway_intent_id' => $intentId,
                    'gateway_payment_method_id' => $paymentMethodId,
                    'client_key' => $clientKey,
                ])
            ]);

            // Return checkout URL or redirect URL
            return $result['data']['attributes']['next_action']['redirect']['url'] 
                ?? route('student.payment.success', $transaction->id);

        } catch (\Exception $e) {
            Log::error('PayMongo payment creation failed', [
                'error' => $e->getMessage(),
                'transaction_id' => $transaction->id,
            ]);
            throw new \Exception('Failed to create PayMongo payment: ' . $e->getMessage());
        }
    }

    /**
     * Create GCash payment
     */
    protected function createGCashPayment(
        PaymentGatewayConfig $gateway,
        Transaction $transaction
    ): string {
        try {
            // GCash uses PayMongo as processor
            $amount = (int)($transaction->amount * 100);

            $response = $this->client->post('https://api.paymongo.com/v1/sources', [
                'auth' => [$gateway->secret_key, ''],
                'json' => [
                    'data' => [
                        'attributes' => [
                            'type' => 'gcash',
                            'amount' => $amount,
                            'currency' => 'PHP',
                            'redirect' => [
                                'success' => route('student.payment.success', $transaction->id),
                                'failed' => route('student.payment.failed', $transaction->id),
                            ],
                            'billing' => [
                                'name' => $transaction->user->name ?? 'Student',
                                'email' => $transaction->user->email ?? 'student@ccdi.edu.ph',
                            ],
                            'description' => $transaction->reference,
                        ]
                    ]
                ]
            ]);

            $source = json_decode($response->getBody(), true);
            $sourceId = $source['data']['id'];
            $checkoutUrl = $source['data']['attributes']['redirect']['checkout_url'];

            // Update transaction
            $transaction->update([
                'meta' => array_merge($transaction->meta ?? [], [
                    'gateway_source_id' => $sourceId,
                ])
            ]);

            return $checkoutUrl;

        } catch (\Exception $e) {
            Log::error('GCash payment creation failed', [
                'error' => $e->getMessage(),
                'transaction_id' => $transaction->id,
            ]);
            throw new \Exception('Failed to create GCash payment: ' . $e->getMessage());
        }
    }

    /**
     * Create Maya payment
     */
    protected function createMayaPayment(
        PaymentGatewayConfig $gateway,
        Transaction $transaction
    ): string {
        try {
            $amount = (int)($transaction->amount * 100);

            $response = $this->client->post('https://api.paymongo.com/v1/sources', [
                'auth' => [$gateway->secret_key, ''],
                'json' => [
                    'data' => [
                        'attributes' => [
                            'type' => 'paymaya',
                            'amount' => $amount,
                            'currency' => 'PHP',
                            'redirect' => [
                                'success' => route('student.payment.success', $transaction->id),
                                'failed' => route('student.payment.failed', $transaction->id),
                            ],
                            'billing' => [
                                'name' => $transaction->user->name ?? 'Student',
                                'email' => $transaction->user->email ?? 'student@ccdi.edu.ph',
                            ],
                            'description' => $transaction->reference,
                        ]
                    ]
                ]
            ]);

            $source = json_decode($response->getBody(), true);
            $sourceId = $source['data']['id'];
            $checkoutUrl = $source['data']['attributes']['redirect']['checkout_url'];

            $transaction->update([
                'meta' => array_merge($transaction->meta ?? [], [
                    'gateway_source_id' => $sourceId,
                ])
            ]);

            return $checkoutUrl;

        } catch (\Exception $e) {
            Log::error('Maya payment creation failed', [
                'error' => $e->getMessage(),
                'transaction_id' => $transaction->id,
            ]);
            throw new \Exception('Failed to create Maya payment: ' . $e->getMessage());
        }
    }

    /**
     * Verify payment status
     */
    public function verifyPayment(PaymentGatewayConfig $gateway, string $paymentId): array
    {
        try {
            $response = $this->client->get(
                "https://api.paymongo.com/v1/payments/{$paymentId}",
                ['auth' => [$gateway->secret_key, '']]
            );

            return json_decode($response->getBody(), true);

        } catch (\Exception $e) {
            Log::error('Payment verification failed', [
                'error' => $e->getMessage(),
                'payment_id' => $paymentId,
            ]);
            throw $e;
        }
    }

    /**
     * Test gateway connection
     */
    public function testConnection(PaymentGatewayConfig $gateway): array
    {
        try {
            // Test with a simple API call
            $response = $this->client->get('https://api.paymongo.com/v1/payment_methods', [
                'auth' => [$gateway->public_key, ''],
            ]);

            if ($response->getStatusCode() === 200) {
                return ['success' => true, 'message' => 'Connection successful'];
            }

            return ['success' => false, 'error' => 'Unexpected response'];

        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Calculate total with fees
     */
    public function calculateTotal(PaymentGatewayConfig $gateway, float $amount): array
    {
        $fee = $gateway->calculateFee($amount);
        $total = $amount + $fee;

        return [
            'amount' => $amount,
            'fee' => $fee,
            'total' => $total,
        ];
    }
}