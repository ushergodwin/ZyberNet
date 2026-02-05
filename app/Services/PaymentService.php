<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\VoucherPackage;
use App\Models\Transaction;
use App\Models\TransactionCharge;
use App\Models\Voucher;
use App\Services\VoucherService;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    /**
     * Process a payment and return the result.
     *
     * @param array $paymentData
     * @param VoucherPackage $package
     * @param string $voucher_code
     * @return array
     */
    public static function processPayment(array $payload, VoucherPackage $package, object $chargeDetails, string $voucher_code = '',): array
    {

        try {
            $response = Http::withToken(config('services.cinemaug.token'))
                ->post(config('services.cinemaug.api_url'), $payload);

            if (!$response->successful()) {
                return [
                    'message' => 'Payment request failed',
                    'error'   => $response->body(),
                ];
            }

            $paymentData = $response->json();

            $transaction = Transaction::create([
                'phone_number'  => $paymentData['contact']['phone_number'] ?? null,
                'amount'        => $paymentData['amount'] ?? null,
                'currency'      => $paymentData['currency'] ?? null,
                'status'        => $paymentData['status'] ?? null,
                'payment_id'    => $paymentData['id'] ?? null,
                'mfscode'       => $paymentData['mfscode'] ?? null,
                'package_id'    => $package->id,
                'response_json' => json_encode($paymentData),
                'channel'       => 'mobile_money',
                'router_id'     => $package->router_id,
                'charge'        => $chargeDetails->charge,
                'total_amount'  => ($chargeDetails->total + $chargeDetails->charge),
            ]);

            if ($voucher_code) {
                $voucher = Voucher::where('code', $voucher_code)->first();
                if ($voucher) {
                    $voucher->transaction_id = $transaction->id;
                    $voucher->save();
                    Log::info('🔗 Voucher linked to transaction', [
                        'voucher_code' => $voucher_code,
                        'transaction_id' => $transaction->id
                    ]);
                }
            }

            return $paymentData;
        } catch (\Exception $e) {
            Log::error('❌ processPayment Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'message' => 'Exception during payment processing',
                'error'   => $e->getMessage(),
            ];
        }
    }

    // check payment status
    public static function checkPaymentStatus(int $id, Transaction $transaction, bool $generate_voucher = true, mixed $voucher_code = '')
    {

        $response = Http::withToken(config('services.cinemaug.token'))
            ->get(config('services.cinemaug.api_url') . '?id=' . $id);

        if (!$response->successful()) {
            return ['message' => 'Payment request failed'];
        }
        $paymentData = $response->json();
        // Update transaction status
        $transaction->status = $paymentData['status'];
        $transaction->response_json = json_encode($paymentData);


        $voucher = null;
        if ($voucher_code) {
            $voucher = Voucher::where('code', $voucher_code)->first();
            if ($voucher) {
                $voucher->transaction_id = $transaction->id;
                $voucher->save();
                $transaction->voucher = $voucher;
            }
        }
        if ($transaction->status === 'successful' && !$transaction->voucher && $generate_voucher) {
            $session_timeout = substr($transaction->package->session_timeout, 0, -1); // Remove the last character (h or d)
            $session_timeout_unit = substr($transaction->package->session_timeout, -1); // Get the last character (h or d)
            // Calculate expiration date based on session timeout
            $expiresAt = now()->add($session_timeout_unit === 'd' ? $session_timeout . ' days' : $session_timeout . ' hours');

            $code = VoucherService::generateVoucherCode(4);
            $voucher = [
                'code'           => $code,
                'transaction_id' => $transaction->id,
                'package_id'     => $transaction->package_id,
                'expires_at'     => $expiresAt,
                'session_timeout' => $transaction->package->session_timeout,
                'profile_name'   => $transaction->package->profile_name,
            ];
            // Create voucher
            $voucherService = new VoucherService();
            $router = $transaction->package->router;
            $voucher = $voucherService->createVouchersAndPushToRouter([$voucher], $router)[0];
        }

        if ($transaction->voucher && !$voucher) {
            $voucher = $transaction->voucher;
        }
        $transaction->save();
        $transaction->delete();
        return $voucher;
    }



    /**
     * Get the applicable charge for a transaction
     * 
     * @param int $amount Transaction amount in UGX
     * @param string $network Network provider (MTN or AIRTEL)
     * @return float|int Charge amount
     */
    static function getTransactionCharge(int $amount, string $network)
    {
        $chargeConfig = TransactionCharge::where('network', $network)
            ->where('min_amount', '<=', $amount)
            ->where('max_amount', '>=', $amount)
            ->first();

        return $chargeConfig ? $chargeConfig->charge : 0;
    }

    /**
     * Calculate total amount - charges
     * 
     * @param int $amount Base transaction amount
     * @param string $network Network provider
     * @return object ['charge' => float, 'total' => float]
     */
    static function calculateTotalWithCharge(int $amount, string $network)
    {
        $charge = self::getTransactionCharge($amount, $network);

        return (object)[
            'charge' => $charge,
            'total' => $amount > 500 ? $amount - $charge : $amount,
        ];
    }
}
