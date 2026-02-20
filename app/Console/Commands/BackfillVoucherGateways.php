<?php

namespace App\Console\Commands;

use App\Models\Voucher;
use Illuminate\Console\Command;

class BackfillVoucherGateways extends Command
{
    protected $signature   = 'vouchers:backfill-gateways';
    protected $description = 'Backfill gateway on existing vouchers. '
        . 'Vouchers with no transaction → shop. '
        . 'Vouchers with a transaction but no gateway → copy from the transaction.';

    public function handle(): int
    {
        // ── Case 1: no transaction, no gateway → admin-printed, treat as shop ──
        $noTxn = Voucher::withTrashed()
            ->whereNull('gateway')
            ->whereNull('transaction_id')
            ->count();

        if ($noTxn > 0) {
            Voucher::withTrashed()
                ->whereNull('gateway')
                ->whereNull('transaction_id')
                ->update(['gateway' => 'shop']);

            $this->info("Set gateway = 'shop' on {$noTxn} voucher(s) with no transaction.");
        } else {
            $this->line('No gateway-less vouchers without a transaction found.');
        }

        // ── Case 2: has transaction, no gateway → copy from transaction ──
        $vouchers = Voucher::withTrashed()
            ->whereNull('gateway')
            ->whereNotNull('transaction_id')
            ->with('transaction')
            ->get();

        $copied = 0;
        $skipped = 0;

        foreach ($vouchers as $voucher) {
            $txnGateway = $voucher->transaction?->gateway;

            if ($txnGateway) {
                $voucher->gateway = $txnGateway;
                $voucher->saveQuietly();
                $copied++;
            } else {
                $this->warn("Voucher {$voucher->code}: transaction #{$voucher->transaction_id} also has no gateway — skipped.");
                $skipped++;
            }
        }

        if ($copied > 0) {
            $this->info("Copied gateway from transaction on {$copied} voucher(s).");
        }
        if ($skipped > 0) {
            $this->warn("{$skipped} voucher(s) skipped (transaction also has no gateway).");
        }

        $this->info('Done.');
        return Command::SUCCESS;
    }
}
