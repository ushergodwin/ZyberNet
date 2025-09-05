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
        $this->info('Checking transactions...');

        // 1. Process pending transactions
        $this->processTransactions(
            Transaction::whereIn('status', ['new', 'instructions_sent', 'pending', 'processing_started'])
                ->where('amount', '>', 0)
                ->with('package'),
            'pending'
        );

        // 2. Process successful transactions without vouchers
        $this->processTransactions(
            Transaction::where('status', 'successful')
                ->where('amount', '>', 0)
                ->whereDoesntHave('voucher')
                ->with('package'),
            'successful_without_voucher'
        );

        $this->info('Done checking transactions.');
        return 0;
    }

    /**
     * Process transactions in chunks
     */
    protected function processTransactions($query, string $context)
    {
        $query->chunkById(100, function ($transactions) use ($context) {
            if ($transactions->isEmpty()) {
                $this->info("No {$context} transactions found in this chunk.");
                return;
            }

            $this->info("Processing {$transactions->count()} {$context} transactions...");
            Log::info("Processing {$context} transactions", [
                'count' => $transactions->count(),
            ]);

            foreach ($transactions as $transaction) {
                $this->handleVoucher($transaction);
            }
        });
    }

    /**
     * Handle voucher issuing and SMS notification for a single transaction
     */
    protected function handleVoucher(Transaction $transaction)
    {
        $this->info("Checking transaction ID: {$transaction->payment_id}");

        try {
            $voucher = PaymentService::checkPaymentStatus(
                $transaction->payment_id,
                $transaction,
                true
            );

            if ($transaction->status === 'successful' && $voucher) {
                $phoneNumber = preg_replace('/^\+/', '', $transaction->phone_number);

                $smsSent = SmsService::send(
                    $phoneNumber,
                    __("Your SuperSpotWiFi voucher code is: :code. Use it to access the internet. Thank you for using our service!", [
                        'code' => $voucher->code,
                    ])
                );

                if ($smsSent) {
                    $this->info("SMS sent to {$phoneNumber}");
                } else {
                    $this->warn("Failed to send SMS to {$phoneNumber}");
                }
            } else {
                $this->info("Transaction {$transaction->payment_id} status: {$transaction->status}. No voucher issued.");
            }
        } catch (\Throwable $e) {
            Log::error('Error processing transaction ' . $transaction->payment_id, [
                'error' => $e->getMessage(),
            ]);
            $this->error("Failed to process transaction {$transaction->payment_id}: {$e->getMessage()}");
        }
    }
}
