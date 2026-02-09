<?php

namespace App\Contracts;

interface PaymentGatewayInterface
{
    /**
     * Process a payment request to the gateway.
     *
     * @param array $payload Payment data including:
     *   - phone_number: string (format: 256XXXXXXXXX)
     *   - amount: float
     *   - currency: string (default: UGX)
     *   - narrative: string (optional description)
     *   - external_reference: string (optional internal reference)
     * @return array Normalized response with keys:
     *   - id: string (gateway transaction reference)
     *   - phone_number: string
     *   - amount: float
     *   - currency: string
     *   - status: string (normalized: new, pending, instructions_sent, processing_started, successful, failed)
     *   - mfscode: string|null (MNO transaction reference)
     *   - raw_response: array (original gateway response)
     */
    public function processPayment(array $payload): array;

    /**
     * Check the status of a payment transaction.
     *
     * @param string $transactionReference The gateway transaction reference or external reference
     * @return array Normalized response with same structure as processPayment
     */
    public function checkPaymentStatus(string $transactionReference): array;

    /**
     * Get the name/identifier of this gateway.
     *
     * @return string
     */
    public function getName(): string;
}
