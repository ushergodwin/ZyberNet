<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Voucher;
use Carbon\Carbon;

class RecalculateVoucherExpiry extends Command
{
    protected $signature = 'app:recalculate-voucher-expiry 
                            {--dry-run : Only show what would be updated without saving}';

    protected $description = 'Recalculate and update expires_at for all activated vouchers based on their activated_at and package session timeout';

    public function handle(): int
    {
        $vouchers = Voucher::with('package')
            ->whereNotNull('activated_at')
            ->get();

        if ($vouchers->isEmpty()) {
            $this->info('No activated vouchers found.');
            return 0;
        }

        $dryRun = $this->option('dry-run');
        $count  = 0;

        foreach ($vouchers as $voucher) {
            if (!$voucher->package) {
                $this->warn("Voucher {$voucher->id} skipped (missing package).");
                continue;
            }

            $timeoutValue = (int) preg_replace('/[^0-9]/', '', $voucher->package->session_timeout);
            $timeoutUnit  = strtolower(substr($voucher->package->session_timeout, -1));

            $expiresAt = Carbon::parse($voucher->activated_at)->add(
                $timeoutUnit === 'd' ? $timeoutValue . ' days' : $timeoutValue . ' hours'
            );

            if ($dryRun) {
                $this->line("Voucher {$voucher->code} => would set expires_at to {$expiresAt}");
            } else {
                $voucher->update(['expires_at' => $expiresAt]);
                $this->info("Voucher {$voucher->code} updated: expires_at = {$expiresAt}");
                $count++;
            }
        }

        if (!$dryRun) {
            $this->info("✔ Updated {$count} vouchers.");
        }

        return 0;
    }
}

