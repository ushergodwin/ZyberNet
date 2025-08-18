<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Transaction;
use App\Services\PaymentService;
use App\Services\SmsService;
use Illuminate\Support\Facades\Log;

class CheckPendingTransactions extends Command
{
    protected $signature = 'app:check-pending-transactions';
    protected $description = 'Check status of pending transactions and issue vouchers if successful';

    public function handle()
    {
        $this->info('Checking pending transactions...');

        $transactions = Transaction::whereIn('status', ['new', 'instructions_sent', 'pending'])
            // amount not negative
            ->where('amount', '>', 0)
            ->with('package')
            ->get();

        if ($transactions->isNotEmpty()) {
            $this->info('Found pending transactions. Processing them...');
            Log::info('Found pending transactions', [
                'count' => $transactions->count(),
            ]);
            $this->handleVouchers($transactions);
        } else {
            $this->info('No pending transactions found.');
        }

        // Transactions that are successful but do not have a voucher
        $transactionsWithoutVoucher = Transaction::where('status', 'successful')
            ->with(['package', 'voucher'])
            ->whereDoesntHave('voucher')
            ->where('amount', '>', 0)
            ->get();

        if ($transactionsWithoutVoucher->isNotEmpty()) {
            $this->info('Found successful transactions without vouchers. Processing them...');
            Log::info('Found successful transactions without vouchers', [
                'count' => $transactionsWithoutVoucher->count(),
            ]);
            $this->handleVouchers($transactionsWithoutVoucher);
        } else {
            $this->info('No successful transactions without vouchers found.');
        }

        $this->info('Done checking pending transactions.');
        return 0;
    }


    protected function handleVouchers($transactions)
    {
        try {
            foreach ($transactions as $transaction) {
                $this->info("Checking transaction ID: {$transaction->payment_id}");

                try {
                    $voucher = PaymentService::checkPaymentStatus(
                        $transaction->payment_id,
                        $transaction,
                        true
                    );

                    // Only send SMS if the transaction is successful and voucher exists
                    if ($transaction->status === 'successful' && $voucher) {
                        $phoneNumber = preg_replace('/^\+/', '', $transaction->phone_number);
                        $smsSent = SmsService::send(
                            $phoneNumber,
                            "Your SuperSpotWiFi voucher code is: {$voucher->code}. Use it to access the internet. Thank you for using our service!"
                        );

                        if ($smsSent) {
                            $this->info("SMS sent to {$phoneNumber}");
                        } else {
                            $this->warn("Failed to send SMS to {$phoneNumber}");
                        }
                    }
                } catch (\Throwable $e) {
                    Log::error('Error processing transaction ' . $transaction->payment_id, [
                        'error' => $e->getMessage(),
                    ]);
                    $this->error("Failed to process transaction {$transaction->payment_id}: {$e->getMessage()}");
                }
            }
        } catch (\Throwable $th) {
            //throw $th;
            Log::error('Error checking pending transactions', [
                'error' => $th->getMessage(),
            ]);
        }
    }
}
