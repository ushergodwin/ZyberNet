<?php

namespace App\Console\Commands;

use App\Models\RouterConfiguration;
use App\Models\Transaction;
use Illuminate\Console\Command;
use App\Services\MikroTikService;
use Illuminate\Support\Facades\Log;

class CleanupExpiredVouchers extends Command
{
    protected $signature = 'app:cleanup-expired-vouchers';
    protected $description = 'Remove expired hotspot users from MikroTik and clean up CinemaUG transactions';

    public function handle()
    {
        $this->cleanupExpiredHotspotUsers();
        $this->cleanupTransactions();
    }

    protected function cleanupExpiredHotspotUsers()
    {
        try {
            $routers = RouterConfiguration::all();
            if ($routers->isEmpty()) {
                Log::info('No router configurations found for cleanup.');
                return;
            }

            foreach ($routers as $routerConfig) {
                $mikrotik = new MikroTikService($routerConfig);
                $mikrotik->removeExpiredHotspotUsers();
            }
        } catch (\Throwable $th) {
            Log::error('Failed to cleanup expired vouchers: ' . $th->getMessage(), [
                'trace' => $th->getTraceAsString(),
            ]);
        }
    }

    /**
     * Clean up transactions that are no longer needed.
     *
     * Deletes  transactions that are:
     * - successful or failed (terminal states)
     * - pending for more than 10 minutes (stale — gives enough time for USSD PIN entry + polling)
     *
     * For successful transactions with vouchers, detaches the voucher first.
     */
    protected function cleanupTransactions()
    {
        try {
            $transactions = Transaction::where('gateway', 'cinemaug')
                ->where(function ($query) {
                    $query->whereIn('status', ['successful', 'failed'])
                        ->orWhere(function ($q) {
                            $q->whereNotIn('status', ['successful', 'failed'])
                                ->where('created_at', '<=', now()->subMinutes(10));
                        });
                })
                ->with('voucher')
                ->get();

            if ($transactions->isEmpty()) {
                return;
            }

            $deleted = 0;

            foreach ($transactions as $transaction) {
                // Detach voucher before deleting the transaction
                if ($transaction->voucher) {
                    $transaction->voucher->transaction_id = null;
                    $transaction->voucher->save();
                }

                $transaction->forceDelete();
                $deleted++;
            }

            if ($deleted > 0) {
                Log::info("TXN cleanup: deleted {$deleted} transactions.");
                $this->info("TXN cleanup: deleted {$deleted} transactions.");
            }
        } catch (\Throwable $th) {
            Log::error('Failed to cleanup CinemaUG transactions: ' . $th->getMessage(), [
                'trace' => $th->getTraceAsString(),
            ]);
            $this->error('Failed to cleanup CinemaUG transactions: ' . $th->getMessage());
        }
    }
}
