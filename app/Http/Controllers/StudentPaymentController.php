<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\PaymentGateway;
use App\Models\Transaction;
use App\Services\PaymentGatewayService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StudentPaymentController extends Controller
{
    protected PaymentGatewayService $gatewayService;

    public function __construct(PaymentGatewayService $gatewayService)
    {
        $this->gatewayService = $gatewayService;
    }

    /**
     * ✅ Show payment form
     */
    public function create(Request $request)
    {
        $user = $request->user();
        $student = Student::where('user_id', $user->id)->firstOrFail();

        if (!$student->account_id) {
            return back()->withErrors([
                'error' => 'Student profile not configured properly.'
            ]);
        }

        // Get active payment gateways
        $gateways = PaymentGateway::where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(function ($gateway) {
                return [
                    'id' => $gateway->id,
                    'name' => $gateway->name,
                    'slug' => $gateway->slug,
                    'supported_methods' => $gateway->supported_methods,
                    'fees' => $gateway->fees,
                    'logo_url' => $gateway->logo_url,
                ];
            });

        // Get unpaid payment terms
        $paymentTerms = $student->paymentTerms()
            ->where('status', '!=', 'paid')
            ->orderBy('term_order')
            ->get()
            ->map(function ($term) {
                return [
                    'id' => $term->id,
                    'term_name' => $term->term_name,
                    'amount' => (float) $term->amount,
                    'remaining_balance' => (float) $term->remaining_balance,
                    'due_date' => $term->due_date?->format('Y-m-d'),
                ];
            });

        $totalBalance = abs($user->account->balance ?? 0);
        $minPayment = 100.00; // Minimum payment amount

        return Inertia::render('Student/Payment', [
            'account_id' => $student->account_id,
            'gateways' => $gateways,
            'payment_terms' => $paymentTerms,
            'total_balance' => $totalBalance,
            'min_payment' => $minPayment,
        ]);
    }

    /**
     * ✅ Process payment
     */
    public function process(Request $request)
    {
        $user = $request->user();
        $student = Student::where('user_id', $user->id)->firstOrFail();

        $validated = $request->validate([
            'gateway_id' => 'required|exists:payment_gateways,id',
            'payment_method' => 'required|string',
            'amount' => 'required|numeric|min:100',
            'term_ids' => 'nullable|array',
            'term_ids.*' => 'exists:student_payment_terms,id',
        ]);

        $gateway = PaymentGateway::findOrFail($validated['gateway_id']);
        $totalBalance = abs($user->account->balance ?? 0);

        // Validate amount
        if ($validated['amount'] > $totalBalance) {
            return back()->withErrors([
                'amount' => 'Payment amount cannot exceed your outstanding balance.'
            ]);
        }

        DB::beginTransaction();
        try {
            // Create pending transaction
            $transaction = Transaction::create([
                'account_id' => $student->account_id,
                'user_id' => $user->id,
                'reference' => 'PAY-' . strtoupper(\Illuminate\Support\Str::random(12)),
                'payment_channel' => $gateway->slug,
                'kind' => 'payment',
                'type' => 'Online Payment',
                'amount' => $validated['amount'],
                'status' => 'pending',
                'meta' => [
                    'gateway_id' => $gateway->id,
                    'payment_method' => $validated['payment_method'],
                    'term_ids' => $validated['term_ids'] ?? null,
                    'initiated_at' => now()->toISOString(),
                ],
            ]);

            // Initiate payment with gateway
            $paymentUrl = $this->gatewayService->createPayment(
                $gateway,
                $transaction,
                $validated['payment_method']
            );

            DB::commit();

            return Inertia::location($paymentUrl);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Payment initiation failed', [
                'account_id' => $student->account_id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'error' => 'Failed to initiate payment. Please try again.'
            ]);
        }
    }

    /**
     * ✅ Payment success callback
     */
    public function success(Request $request, Transaction $transaction)
    {
        // Verify transaction belongs to current user
        $user = $request->user();
        $student = Student::where('user_id', $user->id)->first();

        if (!$student || $transaction->account_id !== $student->account_id) {
            abort(403, 'Unauthorized access');
        }

        return Inertia::render('Student/PaymentSuccess', [
            'transaction' => [
                'id' => $transaction->id,
                'reference' => $transaction->reference,
                'amount' => (float) $transaction->amount,
                'payment_channel' => $transaction->payment_channel,
                'paid_at' => $transaction->paid_at?->toISOString(),
                'status' => $transaction->status,
            ],
            'new_balance' => abs($user->account->balance ?? 0),
            'payment_method_name' => ucfirst($transaction->payment_channel),
            'receipt_url' => route('student.payment.receipt', $transaction->id),
        ]);
    }

    /**
     * ✅ Payment failure callback
     */
    public function failed(Request $request, Transaction $transaction)
    {
        $user = $request->user();
        $student = Student::where('user_id', $user->id)->first();

        if (!$student || $transaction->account_id !== $student->account_id) {
            abort(403, 'Unauthorized access');
        }

        return Inertia::render('Student/PaymentFailed', [
            'transaction' => [
                'id' => $transaction->id,
                'reference' => $transaction->reference,
                'amount' => (float) $transaction->amount,
                'payment_channel' => $transaction->payment_channel,
                'created_at' => $transaction->created_at->toISOString(),
                'status' => $transaction->status,
                'meta' => $transaction->meta,
            ],
            'failure_reason' => $transaction->meta['failure_reason'] ?? null,
            'support_contact' => [
                'email' => 'accounting@ccdi.edu.ph',
                'phone' => '09181234502',
            ],
        ]);
    }

    /**
     * ✅ Download payment receipt
     */
    public function receipt(Transaction $transaction)
    {
        $user = auth()->user();
        $student = Student::where('user_id', $user->id)->first();

        if (!$student || $transaction->account_id !== $student->account_id) {
            abort(403, 'Unauthorized access');
        }

        if ($transaction->status !== 'paid') {
            return back()->withErrors([
                'error' => 'Receipt is only available for paid transactions.'
            ]);
        }

        // Generate PDF receipt
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.payment-receipt', [
            'transaction' => $transaction,
            'student' => $student,
        ]);

        return $pdf->download("receipt-{$transaction->reference}.pdf");
    }
}