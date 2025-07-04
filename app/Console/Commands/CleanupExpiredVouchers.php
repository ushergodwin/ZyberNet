<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Voucher;
use App\Services\MikroTikService;

class CleanupExpiredVouchers extends Command
{
    protected $signature = 'app:cleanup-expired-vouchers';
    protected $description = 'Remove expired hotspot users from MikroTik';

    public function handle()
    {
        $expiredVouchers = Voucher::where('expires_at', '<', now())
            ->get();

        foreach ($expiredVouchers as $voucher) {
            try {
                $mikrotik = new MikroTikService($voucher->router);
                $mikrotik->deleteHotspotUser($voucher->code);
                $voucher->delete();
                $this->info("Removed voucher: {$voucher->code}");
            } catch (\Throwable $e) {
                $this->error("Failed to remove voucher: {$voucher->code} - {$e->getMessage()}");
            }
        }
    }
}