<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class GCashPaymentService
{
    protected $client;
    protected $apiKey;
    protected $apiSecret;
    protected $baseUrl;

    public function __construct()
    {
        $this->client = new Client();
        $this->apiKey = config('services.gcash.api_key');
        $this->apiSecret = config('services.gcash.api_secret');
        $this->baseUrl = config('services.gcash.base_url');
    }

    public function createPaymentLink(array $data): array
    {
        try {
            $response = $this->client->post("{$this->baseUrl}/v1/payments", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->getAccessToken(),
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'requestReferenceNumber' => $data['reference'],
                    'amount' => [
                        'currency' => 'PHP',
                        'value' => (string) $data['amount'],
                    ],
                    'redirectUrl' => [
                        'success' => route('payment.gcash.success'),
                        'failure' => route('payment.gcash.failure'),
                        'cancel' => route('payment.gcash.cancel'),
                    ],
                    'metadata' => [
                        'account_id' => $data['account_id'],
                        'student_name' => $data['student_name'],
                    ],
                ],
            ]);

            $body = json_decode($response->getBody(), true);
            
            return [
                'success' => true,
                'payment_url' => $body['redirectUrl'],
                'reference' => $body['referenceNumber'],
            ];

        } catch (\Exception $e) {
            Log::error('GCash payment creation failed', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    protected function getAccessToken(): string
    {
        // Implement OAuth token retrieval
        // Cache token for reuse
        return cache()->remember('gcash_access_token', 3600, function () {
            // Token retrieval logic
        });
    }

    public function verifyPayment(string $referenceNumber): array
    {
        try {
            $response = $this->client->get("{$this->baseUrl}/v1/payments/{$referenceNumber}", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->getAccessToken(),
                ],
            ]);

            $body = json_decode($response->getBody(), true);

            return [
                'success' => true,
                'status' => $body['status'],
                'amount' => $body['amount']['value'],
                'paid_at' => $body['paymentDate'] ?? null,
            ];

        } catch (\Exception $e) {
            Log::error('GCash payment verification failed', [
                'reference' => $referenceNumber,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}