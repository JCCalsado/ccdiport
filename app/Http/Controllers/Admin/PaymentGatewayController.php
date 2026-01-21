<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentGatewayConfig;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class PaymentGatewayController extends Controller
{
    public function index()
    {
        $gateways = PaymentGatewayConfig::orderBy('gateway')->get();
        
        return Inertia::render('Admin/PaymentGateways', [
            'gateways' => $gateways->map(fn($gateway) => [
                'id' => $gateway->id,
                'gateway' => $gateway->gateway,
                'environment' => $gateway->environment,
                'is_enabled' => $gateway->is_enabled,
                'transaction_fee_percentage' => (float) $gateway->transaction_fee_percentage,
                'transaction_fee_fixed' => (float) $gateway->transaction_fee_fixed,
                'has_keys_configured' => !empty($gateway->public_key) && !empty($gateway->secret_key),
                'created_at' => $gateway->created_at?->toISOString(),
            ]),
            'availableGateways' => [
                'paymongo' => 'PayMongo',
                'xendit' => 'Xendit',
                'gcash' => 'GCash Direct',
                'maya' => 'Maya',
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'gateway' => 'required|string|in:paymongo,xendit,gcash,maya',
            'environment' => 'required|string|in:sandbox,production',
            'public_key' => 'required|string',
            'secret_key' => 'required|string',
            'webhook_secret' => 'nullable|string',
            'transaction_fee_percentage' => 'required|numeric|min:0|max:100',
            'transaction_fee_fixed' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $gateway = PaymentGatewayConfig::updateOrCreate(
                [
                    'gateway' => $validated['gateway'],
                    'environment' => $validated['environment'],
                ],
                [
                    'public_key' => $validated['public_key'],
                    'secret_key' => $validated['secret_key'],
                    'webhook_secret' => $validated['webhook_secret'] ?? null,
                    'transaction_fee_percentage' => $validated['transaction_fee_percentage'],
                    'transaction_fee_fixed' => $validated['transaction_fee_fixed'],
                    'is_enabled' => false, // Must be tested before enabling
                ]
            );

            DB::commit();

            return redirect()->back()->with('success', 'Payment gateway configured successfully. Please test before enabling.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Failed to configure gateway: ' . $e->getMessage()]);
        }
    }

    public function toggleStatus(PaymentGatewayConfig $gateway)
    {
        $gateway->update(['is_enabled' => !$gateway->is_enabled]);
        
        $status = $gateway->is_enabled ? 'enabled' : 'disabled';
        return redirect()->back()->with('success', "Gateway {$status} successfully.");
    }

    public function test(PaymentGatewayConfig $gateway)
    {
        // Test gateway connection
        try {
            $service = app("App\\Services\\PaymentGateways\\{$gateway->gateway}Service");
            $result = $service->testConnection($gateway);
            
            if ($result['success']) {
                return response()->json(['success' => true, 'message' => 'Connection successful']);
            } else {
                return response()->json(['success' => false, 'message' => $result['error']], 400);
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}