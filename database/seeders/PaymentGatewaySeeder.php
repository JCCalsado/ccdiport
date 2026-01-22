<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PaymentGateway;

class PaymentGatewaySeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🔌 Seeding payment gateways...');

        $gateways = [
            [
                'name' => 'PayMongo',
                'slug' => 'paymongo',
                'logo_url' => '/images/gateways/paymongo.svg',
                'description' => 'Accept credit/debit cards, e-wallets, and online banking',
                'is_active' => false, // Must be configured first
                'supported_methods' => [
                    'card',
                    'gcash',
                    'grab_pay',
                    'paymaya',
                ],
                'fees' => [
                    'percentage' => 3.5, // 3.5% + ₱15
                    'fixed' => 15.00,
                ],
                'config' => [
                    'environment' => 'sandbox', // 'sandbox' or 'production'
                    'public_key' => null,
                    'secret_key' => null,
                    'webhook_secret' => null,
                ],
                'sort_order' => 1,
            ],
            [
                'name' => 'GCash',
                'slug' => 'gcash',
                'logo_url' => '/images/gateways/gcash.svg',
                'description' => 'Pay using GCash e-wallet',
                'is_active' => false,
                'supported_methods' => ['gcash'],
                'fees' => [
                    'percentage' => 2.0,
                    'fixed' => 0.00,
                ],
                'config' => [
                    'environment' => 'sandbox',
                    'api_key' => null,
                    'secret_key' => null,
                ],
                'sort_order' => 2,
            ],
            [
                'name' => 'Maya (PayMaya)',
                'slug' => 'maya',
                'logo_url' => '/images/gateways/maya.svg',
                'description' => 'Pay using Maya (formerly PayMaya) e-wallet',
                'is_active' => false,
                'supported_methods' => ['paymaya'],
                'fees' => [
                    'percentage' => 2.5,
                    'fixed' => 0.00,
                ],
                'config' => [
                    'environment' => 'sandbox',
                    'api_key' => null,
                    'secret_key' => null,
                ],
                'sort_order' => 3,
            ],
        ];

        foreach ($gateways as $gateway) {
            PaymentGateway::updateOrCreate(
                ['slug' => $gateway['slug']],
                $gateway
            );
        }

        $this->command->info('✅ Payment gateways seeded!');
        $this->command->warn('⚠️  Configure API keys in Admin > Payment Gateways before enabling');
    }
}