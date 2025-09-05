<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\VoucherPackage;
use App\Models\Transaction;
use App\Models\Voucher;
use App\Services\VoucherService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
    public static function processPayment(array $payload, VoucherPackage $package, string $voucher_code = ''): array
    {
        $response = Http::withToken(env('CINEMAUG_API_TOKEN'))
            ->post('https://cinemaug.com/payments/collect.php', $payload);

        if (!$response->successful()) {
            return [
                'message' => 'Payment request failed',
                'error'   => $response->body(),
            ];
        }

        $paymentData = $response->json();
        // Store transaction
        $transaction = Transaction::create([
            'phone_number'  => $paymentData['contact']['phone_number'],
            'amount'        => $paymentData['amount'],
            'currency'      => $paymentData['currency'],
            'status'        => $paymentData['status'],
            'payment_id'    => $paymentData['id'],
            'mfscode'       => $paymentData['mfscode'],
            'package_id'    => $package->id,
            'response_json' => json_encode($paymentData),
            'channel'       => 'mobile_money',
            'router_id'     => $package->router_id,
        ]);

        if ($voucher_code) {
            $voucher = Voucher::where('code', $voucher_code)->first();
            if ($voucher) {
                $voucher->transaction_id = $transaction->id;
                $voucher->save();
            }
        }

        return $paymentData;
    }

    // check payment status
    public static function checkPaymentStatus(int $id, Transaction $transaction, bool $generate_voucher = true, string $voucher_code = '')
    {

        $response = Http::withToken(env('CINEMAUG_API_TOKEN'))
            ->get('https://cinemaug.com/payments/collect.php?id=' . $id);

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

            $code = strtoupper(Str::random(6));
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
        return $voucher;
    }
}
