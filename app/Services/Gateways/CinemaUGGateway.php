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
            // CinemaUG expects phone in +256XXXXXXXXX format
            $phone = $payload['phone_number'];
            if (!str_starts_with($phone, '+')) {
                $phone = '+' . $phone;
            }

            $requestPayload = [
                'phone_number' => $phone,
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
                    'error' => 'Status check failed (HTTP ' . $response->status() . ')',
                    'raw_response' => $response->json() ?? ['body' => $response->body()],
                ];
            }

            $data = $response->json();

            Log::info('CinemaUG status check raw response', [
                'reference' => $transactionReference,
                'response' => $data,
            ]);

            return $this->normalizeStatusResponse($data, $transactionReference);
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
     * Normalize CinemaUG response for payment creation.
     *
     * CinemaUG response fields:
     *   id (int), phonenumber, contact.phone_number, amount (string "880.0000"),
     *   currency, status, mfscode, error_message, error_details
     */
    protected function normalizeResponse(array $data): array
    {
        if (empty($data['id'])) {
            return [
                'success' => false,
                'error' => $data['error_message'] ?? $data['detail'] ?? 'Invalid response from CinemaUG',
                'raw_response' => $data,
            ];
        }

        return [
            'success' => true,
            'id' => (string) $data['id'],
            'phone_number' => $data['contact']['phone_number'] ?? ($data['phonenumber'] ?? ''),
            'amount' => (float) ($data['amount'] ?? 0),
            'currency' => $data['currency'] ?? 'UGX',
            'status' => $this->mapStatus($data['status'] ?? 'pending'),
            'mfscode' => $data['mfscode'] ?? null,
            'raw_response' => $data,
        ];
    }

    /**
     * Normalize CinemaUG response for status checks.
     * Validates that the response contains the expected transaction data.
     */
    protected function normalizeStatusResponse(array $data, string $reference): array
    {
        // Validate the response has transaction data (id and status are required)
        if (empty($data['id']) || !isset($data['status'])) {
            Log::warning('CinemaUG status response missing required fields', [
                'reference' => $reference,
                'response' => $data,
            ]);

            return [
                'success' => false,
                'error' => $data['error_message'] ?? $data['detail'] ?? 'Invalid status response from CinemaUG',
                'raw_response' => $data,
            ];
        }

        $status = $this->mapStatus($data['status']);

        return [
            'success' => true,
            'id' => (string) $data['id'],
            'phone_number' => $data['contact']['phone_number'] ?? ($data['phonenumber'] ?? ''),
            'amount' => (float) ($data['amount'] ?? 0),
            'currency' => $data['currency'] ?? 'UGX',
            'status' => $status,
            'mfscode' => $data['mfscode'] ?? null,
            'error_message' => $data['error_message'] ?? null,
            'raw_response' => $data,
        ];
    }

    /**
     * Map CinemaUG status to ZyberNet internal status.
     *
     * CinemaUG statuses: new, pending_payment, pending, instructions_sent,
     *   processing_started, successful, failed, reversed, cashed_out
     */
    protected function mapStatus(string $cinemaStatus): string
    {
        $map = [
            'new' => 'pending',
            'pending_payment' => 'pending',
            'pending' => 'pending',
            'instructions_sent' => 'instructions_sent',
            'processing_started' => 'processing_started',
            'successful' => 'successful',
            'failed' => 'failed',
            'reversed' => 'failed',
            'cashed_out' => 'successful',
        ];

        return $map[strtolower(trim($cinemaStatus))] ?? 'pending';
    }

    /**
     * Get the gateway name.
     */
    public function getName(): string
    {
        return 'cinemaug';
    }
}
