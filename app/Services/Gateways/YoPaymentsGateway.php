<?php

namespace App\Services\Gateways;

use App\Contracts\PaymentGatewayInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class YoPaymentsGateway implements PaymentGatewayInterface
{
    protected string $apiUrl;
    protected string $apiUsername;
    protected string $apiPassword;
    protected string $narrative;

    /**
     * Status mapping from YoPayments to ZyberNet statuses.
     */
    protected array $statusMap = [
        'PENDING' => 'instructions_sent',
        'SUCCEEDED' => 'successful',
        'FAILED' => 'failed',
        'INDETERMINATE' => 'pending',
    ];

    public function __construct()
    {
        $this->apiUrl = config('services.yopayments.api_url', 'https://paymentsapi1.yo.co.ug/ybs/task.php');
        $this->apiUsername = config('services.yopayments.username');
        $this->apiPassword = config('services.yopayments.password');
        $this->narrative = config('services.yopayments.narrative', 'SuperSpot WiFi Payment');
    }

    /**
     * Process a payment request using YoPayments acdepositfunds method.
     */
    public function processPayment(array $payload): array
    {
        try {
            $externalReference = $payload['external_reference'] ?? $this->generateExternalReference();

            $xml = $this->buildDepositFundsXml([
                'amount' => $payload['amount'],
                'account' => $this->normalizePhoneNumber($payload['phone_number']),
                'narrative' => $payload['narrative'] ?? $this->narrative,
                'external_reference' => $externalReference,
            ]);

            Log::info('YoPayments deposit request', [
                'phone' => $payload['phone_number'],
                'amount' => $payload['amount'],
                'external_reference' => $externalReference,
            ]);

            $response = Http::withHeaders([
                'Content-Type' => 'text/xml',
                'Content-transfer-encoding' => 'text',
            ])->withBody($xml, 'text/xml')->post($this->apiUrl);

            if (!$response->successful()) {
                Log::error('YoPayments HTTP request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'success' => false,
                    'error' => 'Payment request failed: HTTP ' . $response->status(),
                    'raw_response' => ['body' => $response->body()],
                ];
            }

            $xmlResponse = $this->parseXmlResponse($response->body());

            return $this->normalizeResponse($xmlResponse, $payload['phone_number'], $payload['amount'], $externalReference);
        } catch (\Exception $e) {
            Log::error('YoPayments processPayment exception', [
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
     * Check the status of a payment transaction using actransactioncheckstatus method.
     */
    public function checkPaymentStatus(string $transactionReference): array
    {
        try {
            $xml = $this->buildCheckStatusXml($transactionReference);

            Log::info('YoPayments status check', [
                'reference' => $transactionReference,
            ]);

            $response = Http::withHeaders([
                'Content-Type' => 'text/xml',
                'Content-transfer-encoding' => 'text',
            ])->withBody($xml, 'text/xml')->post($this->apiUrl);

            if (!$response->successful()) {
                Log::error('YoPayments status check HTTP failed', [
                    'reference' => $transactionReference,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'success' => false,
                    'error' => 'Status check failed: HTTP ' . $response->status(),
                    'raw_response' => ['body' => $response->body()],
                ];
            }

            $xmlResponse = $this->parseXmlResponse($response->body());

            return $this->normalizeStatusResponse($xmlResponse, $transactionReference);
        } catch (\Exception $e) {
            Log::error('YoPayments checkPaymentStatus exception', [
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
     * Build XML for acdepositfunds request.
     */
    protected function buildDepositFundsXml(array $params): string
    {
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><AutoCreate/>');
        $request = $xml->addChild('Request');

        $request->addChild('APIUsername', $this->xmlEscape($this->apiUsername));
        $request->addChild('APIPassword', $this->xmlEscape($this->apiPassword));
        $request->addChild('Method', 'acdepositfunds');
        $request->addChild('NonBlocking', 'TRUE');
        $request->addChild('Amount', (string) $params['amount']);
        $request->addChild('Account', $this->xmlEscape($params['account']));
        $request->addChild('Narrative', $this->xmlEscape($params['narrative']));
        $request->addChild('ExternalReference', $this->xmlEscape($params['external_reference']));

        return $xml->asXML();
    }

    /**
     * Build XML for actransactioncheckstatus request.
     */
    protected function buildCheckStatusXml(string $reference): string
    {
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><AutoCreate/>');
        $request = $xml->addChild('Request');

        $request->addChild('APIUsername', $this->xmlEscape($this->apiUsername));
        $request->addChild('APIPassword', $this->xmlEscape($this->apiPassword));
        $request->addChild('Method', 'actransactioncheckstatus');
        $request->addChild('PrivateTransactionReference', $this->xmlEscape($reference));

        return $xml->asXML();
    }

    /**
     * Parse XML response from YoPayments.
     */
    protected function parseXmlResponse(string $xmlString): array
    {
        try {
            $xml = simplexml_load_string($xmlString, 'SimpleXMLElement', LIBXML_NOCDATA);

            if ($xml === false) {
                Log::error('YoPayments XML parse error', ['xml' => $xmlString]);
                return ['Status' => 'ERROR', 'StatusMessage' => 'Failed to parse XML response'];
            }

            $response = $xml->Response ?? $xml;

            return [
                'Status' => (string) ($response->Status ?? 'ERROR'),
                'StatusCode' => (string) ($response->StatusCode ?? '-1'),
                'StatusMessage' => (string) ($response->StatusMessage ?? ''),
                'TransactionStatus' => (string) ($response->TransactionStatus ?? ''),
                'TransactionReference' => (string) ($response->TransactionReference ?? ''),
                'MNOTransactionReferenceId' => (string) ($response->MNOTransactionReferenceId ?? ''),
                'ErrorMessage' => (string) ($response->ErrorMessage ?? ''),
                'ErrorMessageCode' => (string) ($response->ErrorMessageCode ?? ''),
                'Amount' => (string) ($response->Amount ?? ''),
                'AmountFormatted' => (string) ($response->AmountFormatted ?? ''),
                'CurrencyCode' => (string) ($response->CurrencyCode ?? ''),
                'TransactionInitiationDate' => (string) ($response->TransactionInitiationDate ?? ''),
                'TransactionCompletionDate' => (string) ($response->TransactionCompletionDate ?? ''),
                'IssuingOrganizationCode' => (string) ($response->IssuingOrganizationCode ?? ''),
                'raw_xml' => $xmlString,
            ];
        } catch (\Exception $e) {
            Log::error('YoPayments XML parse exception', [
                'message' => $e->getMessage(),
                'xml' => $xmlString,
            ]);

            return ['Status' => 'ERROR', 'StatusMessage' => $e->getMessage()];
        }
    }

    /**
     * Normalize YoPayments response to standard format for processPayment.
     */
    protected function normalizeResponse(array $data, string $phoneNumber, float $amount, string $externalReference): array
    {
        $isSuccess = ($data['Status'] ?? '') === 'OK';
        $transactionStatus = $data['TransactionStatus'] ?? '';

        if (!$isSuccess) {
            return [
                'success' => false,
                'error' => $data['StatusMessage'] ?? $data['ErrorMessage'] ?? 'Unknown error',
                'status' => 'failed',
                'raw_response' => $data,
            ];
        }

        $normalizedStatus = $this->mapStatus($transactionStatus);

        return [
            'success' => true,
            'id' => $data['TransactionReference'] ?: $externalReference,
            'phone_number' => $phoneNumber,
            'amount' => $amount,
            'currency' => $data['CurrencyCode'] ?: 'UGX',
            'status' => $normalizedStatus,
            'mfscode' => $data['MNOTransactionReferenceId'] ?: null,
            'raw_response' => $data,
        ];
    }

    /**
     * Normalize YoPayments response for status check.
     */
    protected function normalizeStatusResponse(array $data, string $reference): array
    {
        $isSuccess = ($data['Status'] ?? '') === 'OK';
        $transactionStatus = $data['TransactionStatus'] ?? '';

        // Handle transaction not found error (StatusCode -30)
        if (!$isSuccess && ($data['StatusCode'] ?? '') === '-30') {
            return [
                'success' => false,
                'error' => 'Transaction not found',
                'status' => 'failed',
                'raw_response' => $data,
            ];
        }

        if (!$isSuccess && $transactionStatus === '') {
            return [
                'success' => false,
                'error' => $data['StatusMessage'] ?? $data['ErrorMessage'] ?? 'Unknown error',
                'status' => 'failed',
                'raw_response' => $data,
            ];
        }

        $normalizedStatus = $this->mapStatus($transactionStatus);

        return [
            'success' => true,
            'id' => $data['TransactionReference'] ?: $reference,
            'phone_number' => '',
            'amount' => (float) ($data['Amount'] ?? 0),
            'currency' => $data['CurrencyCode'] ?: 'UGX',
            'status' => $normalizedStatus,
            'mfscode' => $data['MNOTransactionReferenceId'] ?: null,
            'raw_response' => $data,
        ];
    }

    /**
     * Map YoPayments transaction status to ZyberNet status.
     */
    protected function mapStatus(string $yoStatus): string
    {
        $upperStatus = strtoupper(trim($yoStatus));
        return $this->statusMap[$upperStatus] ?? 'pending';
    }

    /**
     * Normalize phone number to YoPayments format (256XXXXXXXXX).
     */
    protected function normalizePhoneNumber(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (str_starts_with($phone, '0')) {
            $phone = '256' . substr($phone, 1);
        } elseif (!str_starts_with($phone, '256')) {
            $phone = '256' . $phone;
        }

        return $phone;
    }

    /**
     * Generate a unique external reference for tracking.
     */
    protected function generateExternalReference(): string
    {
        return 'ZYB-' . date('Ymd') . '-' . strtoupper(Str::random(8));
    }

    /**
     * Escape special characters for XML.
     */
    protected function xmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * Get the gateway name.
     */
    public function getName(): string
    {
        return 'yopayments';
    }
}
