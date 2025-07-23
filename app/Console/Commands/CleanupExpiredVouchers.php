<?php

namespace App\Console\Commands;

use App\Models\RouterConfiguration;
use Illuminate\Console\Command;
use App\Services\MikroTikService;
use Illuminate\Support\Facades\Log;

class CleanupExpiredVouchers extends Command
{
    protected $signature = 'app:cleanup-expired-vouchers';
    protected $description = 'Remove expired hotspot users from MikroTik';

    public function handle()
    {
        try {
            $router = RouterConfiguration::all();
            if ($router->isEmpty()) {
                Log::info('No router configurations found for cleanup.');
                return;
            }

            foreach ($router as $routerConfig) {
                $mikrotik = new MikroTikService($routerConfig);
                $mikrotik->removeExpiredHotspotUsers();
            }
        } catch (\Throwable $th) {
            //throw $th;
            Log::error('Failed to cleanup expired vouchers: ' . $th->getMessage(), [
                'trace' => $th->getTraceAsString(),
            ]);
        }
    }
}
