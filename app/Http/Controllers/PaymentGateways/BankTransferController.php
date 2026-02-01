<?php

namespace App\Http\Controllers\PaymentGateways;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BankTransferController extends Controller
{
    /**
     * Show bank transfer instructions
     */
    public function create(Request $request)
    {
        $validated = $request->validate([
            'payment_id' => 'required|exists:payments,id',
            'amount' => 'required|numeric|min:1',
        ]);

        $payment = Payment::findOrFail($validated['payment_id']);

        return inertia('PaymentGateways/BankTransfer/Create', [
            'payment' => $payment->load('student.user', 'fee'),
            'bankAccounts' => $this->getBankAccounts(),
        ]);
    }

    /**
     * Submit bank transfer proof
     */
    public function submit(Request $request)
    {
        $validated = $request->validate([
            'payment_id' => 'required|exists:payments,id',
            'bank_name' => 'required|string',
            'account_name' => 'required|string',
            'reference_number' => 'required|string',
            'transfer_date' => 'required|date',
            'proof_of_payment' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120', // 5MB max
        ]);

        $payment = Payment::findOrFail($validated['payment_id']);

        // Store proof of payment
        $path = $request->file('proof_of_payment')->store('payment-proofs', 'public');

        $payment->update([
            'status' => 'pending_verification',
            'gateway_response' => [
                'bank_name' => $validated['bank_name'],
                'account_name' => $validated['account_name'],
                'reference_number' => $validated['reference_number'],
                'transfer_date' => $validated['transfer_date'],
                'proof_path' => $path,
                'submitted_at' => now(),
            ],
        ]);

        Log::info('Bank transfer proof submitted', [
            'payment_id' => $payment->id,
            'reference' => $validated['reference_number'],
        ]);

        return redirect()->route('payment.bank.success', ['payment' => $payment->id])
            ->with('success', 'Bank transfer proof submitted. Awaiting verification.');
    }

    /**
     * Success page
     */
    public function success(Payment $payment)
    {
        return inertia('PaymentGateways/BankTransfer/Success', [
            'payment' => $payment->load('student.user', 'fee'),
        ]);
    }

    /**
     * Get available bank accounts
     */
    protected function getBankAccounts(): array
    {
        return [
            [
                'bank_name' => 'BDO',
                'account_name' => 'CCDI Account',
                'account_number' => '1234-5678-9012',
                'branch' => 'Sorsogon Branch',
            ],
            [
                'bank_name' => 'BPI',
                'account_name' => 'CCDI Account',
                'account_number' => '9876-5432-1098',
                'branch' => 'Legazpi Branch',
            ],
        ];
    }
}