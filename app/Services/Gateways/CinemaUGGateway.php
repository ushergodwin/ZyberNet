<?php

namespace App\Services\Gateways;

use App\Contracts\PaymentGatewayInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CinemaUGGateway implements PaymentGatewayInterface
{
    protected string $apiUrl;
    protected string $apiToken;

    public function __construct()
    {
        $this->apiUrl = config('services.cinemaug.api_url');
        $this->apiToken = config('services.cinemaug.token');
    }

    /**
     * Process a payment request to CinemaUG.
     */
    public function processPayment(array $payload): array
    {
        try {
            $requestPayload = [
                'phone_number' => $payload['phone_number'],
                'amount' => $payload['amount'],
                'currency' => $payload['currency'] ?? 'UGX',
            ];

            $response = Http::withToken($this->apiToken)
                ->post($this->apiUrl, $requestPayload);

            if (!$response->successful()) {
                Log::error('CinemaUG payment request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'success' => false,
                    'error' => 'Payment request failed',
                    'raw_response' => $response->json() ?? ['body' => $response->body()],
                ];
            }

            $data = $response->json();

            return $this->normalizeResponse($data);
        } catch (\Exception $e) {
            Log::error('CinemaUG processPayment exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'raw_response' => [],
            ];
        }
    }

    /**
     * Check the status of a payment transaction.
     */
    public function checkPaymentStatus(string $transactionReference): array
    {
        try {
            $response = Http::withToken($this->apiToken)
                ->get($this->apiUrl . '?id=' . $transactionReference);

            if (!$response->successful()) {
                Log::error('CinemaUG status check failed', [
                    'reference' => $transactionReference,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'success' => false,
                    'error' => 'Status check failed',
                    'raw_response' => $response->json() ?? ['body' => $response->body()],
                ];
            }

            $data = $response->json();

            return $this->normalizeResponse($data);
        } catch (\Exception $e) {
            Log::error('CinemaUG checkPaymentStatus exception', [
                'reference' => $transactionReference,
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'raw_response' => [],
            ];
        }
    }

    /**
     * Normalize CinemaUG response to standard format.
     */
    protected function normalizeResponse(array $data): array
    {
        return [
            'success' => true,
            'id' => (string) ($data['id'] ?? ''),
            'phone_number' => $data['contact']['phone_number'] ?? ($data['phone_number'] ?? ''),
            'amount' => (float) ($data['amount'] ?? 0),
            'currency' => $data['currency'] ?? 'UGX',
            'status' => $data['status'] ?? 'pending',
            'mfscode' => $data['mfscode'] ?? null,
            'raw_response' => $data,
        ];
    }

    /**
     * Get the gateway name.
     */
    public function getName(): string
    {
        return 'cinemaug';
    }
}
