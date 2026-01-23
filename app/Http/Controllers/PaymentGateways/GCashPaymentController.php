<?php

namespace App\Http\Controllers\PaymentGateways;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GCashPaymentController extends Controller
{
    /**
     * Initiate GCash payment
     */
    public function create(Request $request)
    {
        $validated = $request->validate([
            'payment_id' => 'required|exists:payments,id',
            'amount' => 'required|numeric|min:1',
        ]);

        $payment = Payment::findOrFail($validated['payment_id']);

        // TODO: Integrate with actual GCash API
        // For now, return mock response
        return response()->json([
            'success' => true,
            'payment_url' => route('payment-gateways.gcash.checkout', ['payment' => $payment->id]),
            'reference_number' => 'GCASH-' . strtoupper(uniqid()),
        ]);
    }

    /**
     * Show GCash checkout page
     */
    public function checkout(Payment $payment)
    {
        return inertia('PaymentGateways/GCash/Checkout', [
            'payment' => $payment->load('student.user', 'fee'),
        ]);
    }

    /**
     * Handle GCash callback/webhook
     */
    public function callback(Request $request)
    {
        Log::info('GCash Callback Received', $request->all());

        // TODO: Verify webhook signature
        // TODO: Update payment status based on GCash response

        $referenceNumber = $request->input('reference_number');
        $status = $request->input('status');

        if (!$referenceNumber) {
            return response()->json(['error' => 'Missing reference number'], 400);
        }

        $payment = Payment::where('reference_number', $referenceNumber)->first();

        if (!$payment) {
            return response()->json(['error' => 'Payment not found'], 404);
        }

        if ($status === 'success' || $status === 'completed') {
            $payment->update([
                'status' => 'completed',
                'verified_at' => now(),
                'gateway_response' => $request->all(),
            ]);

            return response()->json(['success' => true]);
        }

        $payment->update([
            'status' => 'failed',
            'gateway_response' => $request->all(),
        ]);

        return response()->json(['success' => false]);
    }

    /**
     * Handle successful payment return
     */
    public function success(Request $request)
    {
        $referenceNumber = $request->input('reference_number');
        $payment = Payment::where('reference_number', $referenceNumber)->first();

        if (!$payment) {
            return redirect()->route('student.dashboard')
                ->with('error', 'Payment not found');
        }

        return inertia('PaymentGateways/GCash/Success', [
            'payment' => $payment->load('student.user', 'fee'),
        ]);
    }

    /**
     * Handle failed payment return
     */
    public function failed(Request $request)
    {
        $referenceNumber = $request->input('reference_number');
        $payment = Payment::where('reference_number', $referenceNumber)->first();

        return inertia('PaymentGateways/GCash/Failed', [
            'payment' => $payment ? $payment->load('student.user', 'fee') : null,
            'error' => $request->input('error', 'Payment failed'),
        ]);
    }
}