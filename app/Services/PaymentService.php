<?php

namespace App\Services;

use App\Models\VoucherPackage;
use App\Models\Transaction;
use App\Models\TransactionCharge;
use App\Models\Voucher;
use App\Services\VoucherService;
use App\Services\PaymentGatewayFactory;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    /**
     * Process a payment and return the result.
     *
     * @param array $payload Payment payload with phone_number, amount, currency
     * @param VoucherPackage $package The voucher package being purchased
     * @param object $chargeDetails Charge details object with 'charge' and 'total' properties
     * @param string $voucher_code Optional voucher code to link
     * @return array Payment data or error array
     */
    public static function processPayment(array $payload, VoucherPackage $package, object $chargeDetails, string $voucher_code = ''): array
    {
        try {
            // Prevent duplicate transactions: check for a recent non-terminal transaction
            // from the same phone number + package within the last 5 minutes
            $phoneNumber = $payload['phone_number'] ?? null;
            if ($phoneNumber) {
                $recentTransaction = Transaction::where('phone_number', $phoneNumber)
                    ->where('package_id', $package->id)
                    ->whereNotIn('status', ['failed'])
                    ->where('created_at', '>=', now()->subMinutes(5))
                    ->latest()
                    ->first();

                if ($recentTransaction) {
                    Log::info('Duplicate payment blocked — returning existing transaction', [
                        'phone' => $phoneNumber,
                        'package_id' => $package->id,
                        'existing_transaction_id' => $recentTransaction->id,
                        'existing_status' => $recentTransaction->status,
                    ]);

                    return [
                        'id' => $recentTransaction->payment_id,
                        'phone_number' => $recentTransaction->phone_number,
                        'amount' => $recentTransaction->amount,
                        'currency' => $recentTransaction->currency ?? 'UGX',
                        'status' => $recentTransaction->status,
                        'mfscode' => $recentTransaction->mfscode,
                        'contact' => ['phone_number' => $recentTransaction->phone_number],
                        '_gateway' => $recentTransaction->gateway,
                    ];
                }
            }

            $gateway = PaymentGatewayFactory::make();
            $gatewayName = $gateway->getName();

            Log::info('Processing payment via gateway', [
                'gateway' => $gatewayName,
                'phone' => $phoneNumber ?? 'unknown',
                'amount' => $payload['amount'] ?? 0,
            ]);

            $gatewayResponse = $gateway->processPayment($payload);

            if (!($gatewayResponse['success'] ?? false)) {
                Log::error('Payment gateway request failed', [
                    'gateway' => $gatewayName,
                    'error' => $gatewayResponse['error'] ?? 'Unknown error',
                    'raw_response' => $gatewayResponse['raw_response'] ?? [],
                ]);

                return [
                    'message' => 'Payment request failed',
                    'error' => $gatewayResponse['error'] ?? 'Unknown error',
                ];
            }

            $transaction = Transaction::create([
                'phone_number'  => $gatewayResponse['phone_number'] ?? ($payload['phone_number'] ?? null),
                'amount'        => $gatewayResponse['amount'] ?? ($payload['amount'] ?? null),
                'currency'      => $gatewayResponse['currency'] ?? 'UGX',
                'status'        => $gatewayResponse['status'] ?? 'pending',
                'payment_id'    => $gatewayResponse['id'] ?? null,
                'mfscode'       => $gatewayResponse['mfscode'] ?? null,
                'package_id'    => $package->id,
                'response_json' => json_encode($gatewayResponse['raw_response'] ?? $gatewayResponse),
                'channel'       => 'mobile_money',
                'router_id'     => $package->router_id,
                'charge'        => $chargeDetails->charge,
                'total_amount'  => ($chargeDetails->total + $chargeDetails->charge),
                'gateway'       => $gatewayName,
            ]);

            // Record gateway usage for auto-switch counter
            PaymentGatewayFactory::recordGatewayUsage($gatewayName);

            if ($voucher_code) {
                $voucher = Voucher::where('code', $voucher_code)->first();
                if ($voucher) {
                    $voucher->transaction_id = $transaction->id;
                    $voucher->save();
                    Log::info('Voucher linked to transaction', [
                        'voucher_code' => $voucher_code,
                        'transaction_id' => $transaction->id
                    ]);
                }
            }

            // Return response in format compatible with existing code
            // Include both normalized fields and raw response for backward compatibility
            return self::buildCompatibleResponse($gatewayResponse, $payload, $gatewayName);
        } catch (\Exception $e) {
            Log::error('processPayment Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'message' => 'Exception during payment processing',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check payment status and optionally generate voucher on success.
     *
     * @param int|string $id Payment ID or transaction reference
     * @param Transaction $transaction The transaction record
     * @param bool $generate_voucher Whether to generate voucher on success
     * @param mixed $voucher_code Optional voucher code to link
     * @return Voucher|array|null Returns voucher on success, array with error, or null
     */
    public static function checkPaymentStatus(int|string $id, Transaction $transaction, bool $generate_voucher = true, mixed $voucher_code = '')
    {
        try {
            // Use the gateway that originally processed this transaction
            $gateway = PaymentGatewayFactory::make($transaction->gateway);

            Log::info('Checking payment status via gateway', [
                'gateway' => $gateway->getName(),
                'reference' => $id,
            ]);

            $gatewayResponse = $gateway->checkPaymentStatus((string) $id);

            // Don't overwrite terminal states (successful/failed) with gateway errors
            $isTerminal = in_array($transaction->status, ['successful', 'failed']);

            if (!($gatewayResponse['success'] ?? false)) {
                Log::error('Payment status check failed', [
                    'gateway' => $gateway->getName(),
                    'reference' => $id,
                    'error' => $gatewayResponse['error'] ?? 'Unknown error',
                ]);

                if ($isTerminal) {
                    // Transaction already reached a final state — keep it as-is
                    return $transaction->voucher;
                }

                return ['message' => 'Payment status check failed'];
            }

            // Only update status if the transaction hasn't already reached a terminal state
            if (!$isTerminal) {
                $transaction->status = $gatewayResponse['status'] ?? $transaction->status;
            }

            $transaction->response_json = json_encode($gatewayResponse['raw_response'] ?? $gatewayResponse);

            // Update mfscode if provided
            if (!empty($gatewayResponse['mfscode'])) {
                $transaction->mfscode = $gatewayResponse['mfscode'];
            }

            $voucher = null;

            // Link existing voucher if provided
            if ($voucher_code) {
                $voucher = Voucher::where('code', $voucher_code)->first();
                if ($voucher) {
                    $voucher->transaction_id = $transaction->id;
                    $voucher->save();
                    $transaction->voucher = $voucher;
                }
            }

            // Generate voucher on successful payment
            if ($transaction->status === 'successful' && !$transaction->voucher && $generate_voucher && $transaction->package) {
                $voucher = self::generateVoucherForTransaction($transaction);
            }

            // Return existing voucher if no new one was created
            if ($transaction->voucher && !$voucher) {
                $voucher = $transaction->voucher;
            }

            $transaction->save();

            return $voucher;
        } catch (\Exception $e) {
            Log::error('checkPaymentStatus Exception', [
                'reference' => $id,
                'message' => $e->getMessage(),
            ]);

            return ['message' => 'Exception during status check: ' . $e->getMessage()];
        }
    }

    /**
     * Generate a voucher for a successful transaction.
     *
     * @param Transaction $transaction
     * @return Voucher|null
     */
    protected static function generateVoucherForTransaction(Transaction $transaction): ?Voucher
    {
        if (!$transaction->package) {
            Log::error('Cannot generate voucher: transaction has no package', [
                'transaction_id' => $transaction->id,
            ]);
            return null;
        }

        $session_timeout = substr($transaction->package->session_timeout, 0, -1);
        $session_timeout_unit = substr($transaction->package->session_timeout, -1);

        $expiresAt = now()->add(
            $session_timeout_unit === 'd'
                ? $session_timeout . ' days'
                : $session_timeout . ' hours'
        );

        $code = VoucherService::generateVoucherCode(4);

        $voucherData = [
            'code' => $code,
            'transaction_id' => $transaction->id,
            'package_id' => $transaction->package_id,
            'expires_at' => $expiresAt,
            'session_timeout' => $transaction->package->session_timeout,
            'profile_name' => $transaction->package->profile_name,
        ];

        $voucherService = new VoucherService();
        $router = $transaction->package->router;

        $vouchers = $voucherService->createVouchersAndPushToRouter([$voucherData], $router);

        return $vouchers[0] ?? null;
    }

    /**
     * Build a response compatible with existing code that expects CinemaUG format.
     *
     * @param array $gatewayResponse Normalized gateway response
     * @param array $originalPayload Original payment payload
     * @return array
     */
    protected static function buildCompatibleResponse(array $gatewayResponse, array $originalPayload, string $gatewayName = ''): array
    {
        // Build response that matches the existing CinemaUG format for backward compatibility
        return [
            'id' => $gatewayResponse['id'] ?? null,
            'phone_number' => $gatewayResponse['phone_number'] ?? ($originalPayload['phone_number'] ?? null),
            'amount' => $gatewayResponse['amount'] ?? ($originalPayload['amount'] ?? null),
            'currency' => $gatewayResponse['currency'] ?? 'UGX',
            'status' => $gatewayResponse['status'] ?? 'pending',
            'mfscode' => $gatewayResponse['mfscode'] ?? null,
            'contact' => [
                'phone_number' => $gatewayResponse['phone_number'] ?? ($originalPayload['phone_number'] ?? null),
            ],
            '_gateway' => $gatewayName,
        ];
    }

    /**
     * Get the applicable charge for a transaction.
     *
     * @param int $amount Transaction amount in UGX
     * @param string $network Network provider (MTN or AIRTEL)
     * @return float|int Charge amount
     */
    public static function getTransactionCharge(int $amount, string $network)
    {
        $chargeConfig = TransactionCharge::where('network', $network)
            ->where('min_amount', '<=', $amount)
            ->where('max_amount', '>=', $amount)
            ->first();

        return $chargeConfig ? $chargeConfig->charge : 0;
    }

    /**
     * Calculate total amount after deducting charges.
     *
     * @param int $amount Base transaction amount
     * @param string $network Network provider
     * @return object Object with 'charge' and 'total' properties
     */
    public static function calculateTotalWithCharge(int $amount, string $network)
    {
        $charge = self::getTransactionCharge($amount, $network);

        return (object) [
            'charge' => $charge,
            'total' => $amount > 500 ? $amount - $charge : $amount,
        ];
    }

    /**
     * Process a test payment without creating a voucher.
     * Used for testing gateway connectivity.
     *
     * @param array $payload Payment payload
     * @return array Gateway response
     */
    public static function processTestPayment(array $payload): array
    {
        try {
            $gateway = PaymentGatewayFactory::make();
            $gatewayName = $gateway->getName();

            Log::info('Processing TEST payment via gateway', [
                'gateway' => $gatewayName,
                'phone' => $payload['phone_number'] ?? 'unknown',
                'amount' => $payload['amount'] ?? 0,
            ]);

            $gatewayResponse = $gateway->processPayment($payload);

            if (!($gatewayResponse['success'] ?? false)) {
                return [
                    'success' => false,
                    'message' => 'Payment request failed',
                    'error' => $gatewayResponse['error'] ?? 'Unknown error',
                    'gateway' => $gatewayName,
                ];
            }

            // Create transaction record for audit (without voucher)
            $transaction = Transaction::create([
                'phone_number' => $gatewayResponse['phone_number'] ?? ($payload['phone_number'] ?? null),
                'amount' => $gatewayResponse['amount'] ?? ($payload['amount'] ?? null),
                'currency' => $gatewayResponse['currency'] ?? 'UGX',
                'status' => $gatewayResponse['status'] ?? 'pending',
                'payment_id' => $gatewayResponse['id'] ?? null,
                'mfscode' => $gatewayResponse['mfscode'] ?? null,
                'package_id' => null, // No package for test payments
                'response_json' => json_encode($gatewayResponse['raw_response'] ?? $gatewayResponse),
                'channel' => 'mobile_money',
                'router_id' => null,
                'charge' => 0,
                'total_amount' => $payload['amount'] ?? 0,
                'gateway' => $gatewayName,
            ]);

            // Record gateway usage for auto-switch counter
            PaymentGatewayFactory::recordGatewayUsage($gatewayName);

            return [
                'success' => true,
                'message' => 'Test payment initiated successfully',
                'gateway' => $gatewayName,
                'transaction_id' => $transaction->id,
                'payment_id' => $gatewayResponse['id'] ?? null,
                'status' => $gatewayResponse['status'] ?? 'pending',
                'raw_response' => $gatewayResponse['raw_response'] ?? [],
            ];
        } catch (\Exception $e) {
            Log::error('processTestPayment Exception', [
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Exception during test payment',
                'error' => $e->getMessage(),
            ];
        }
    }
}
