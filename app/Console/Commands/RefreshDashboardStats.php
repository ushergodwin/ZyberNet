<?php

namespace App\Console\Commands;

use App\Models\DashboardStatistic;
use App\Models\RouterConfiguration;
use App\Models\Transaction;
use App\Models\Voucher;
use App\Models\VoucherPackage;
use App\Services\MikroTikService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RefreshDashboardStats extends Command
{
    protected $signature = 'app:refresh-dashboard-stats';
    protected $description = 'Compute and cache dashboard statistics for all routers';

    public function handle()
    {
        $startTime = microtime(true);

        $routers = RouterConfiguration::all();
        $routerIds = $routers->pluck('id')->toArray();

        // Compute for each router + null (all routers)
        $targets = array_merge([null], $routerIds);

        foreach ($targets as $routerId) {
            foreach (['current_month', 'all_time'] as $period) {
                $stats = $this->computeStats($routerId, $period);

                // Include MikroTik stats for specific routers
                if ($routerId && $period === 'current_month' && config('app.env') !== 'local') {
                    $router = $routers->firstWhere('id', $routerId);
                    if ($router) {
                        $routerStats = $this->fetchRouterStats($router);
                        $stats = array_merge($stats, $routerStats);
                    }
                }

                DashboardStatistic::updateOrCreate(
                    ['router_id' => $routerId, 'period' => $period],
                    ['statistics' => $stats, 'computed_at' => now()]
                );
            }
        }

        $duration = round(microtime(true) - $startTime, 2);
        $count = count($targets) * 2;

        Log::info("Dashboard stats refreshed: {$count} entries in {$duration}s");
        $this->info("Dashboard stats refreshed: {$count} entries in {$duration}s");
    }

    protected function computeStats(?int $routerId, string $period): array
    {
        if ($period === 'current_month') {
            $start = now()->startOfMonth();
            $end = now()->endOfMonth();
        }

        $allTime = $period === 'all_time';

        // Base transaction query (successful YoPayments only — CinemaUG txns are cleaned up)
        $base = Transaction::where('status', 'successful')
            ->where('gateway', 'yopayments')
            ->when($routerId, fn($q) => $q->where('router_id', $routerId))
            ->when(!$allTime, fn($q) => $q->whereBetween('created_at', [$start, $end]));

        $totalRevenue = (clone $base)->where('amount', '>', 0)->sum('amount');
        $totalCharges = (clone $base)->where('amount', '>', 0)->sum('charge');
        $cashRevenue = (clone $base)->where('channel', 'cash')->where('amount', '>', 0)->sum('amount');
        $mobileMoneyRevenue = (clone $base)->where('channel', 'mobile_money')->where('amount', '>', 0)->sum('amount');
        $totalWithdrawals = abs((clone $base)->where('amount', '<', 0)->sum('amount'));
        $balance = $totalRevenue - $totalWithdrawals;

        $fmt = fn($n) => number_format((float) $n, 2, '.', ',');

        // Vouchers
        $voucherBase = Voucher::withTrashed()
            ->when($routerId, fn($q) => $q->where('router_id', $routerId))
            ->when(!$allTime, fn($q) => $q->whereBetween('created_at', [$start, $end]));

        return [
            'total_vouchers' => $voucherBase->count(),
            'expired_vouchers' => (clone $voucherBase)->where('expires_at', '<', now())->count(),
            'total_packages' => VoucherPackage::when($routerId, fn($q) => $q->where('router_id', $routerId))->count(),
            'transactions' => Transaction::when($routerId, fn($q) => $q->where('router_id', $routerId))
                ->when(!$allTime, fn($q) => $q->whereBetween('created_at', [$start, $end]))->count(),
            'successful_txn' => (clone $base)->count(),
            'failed_tnx' => Transaction::where('status', 'failed')
                ->when($routerId, fn($q) => $q->where('router_id', $routerId))
                ->when(!$allTime, fn($q) => $q->whereBetween('created_at', [$start, $end]))->count(),
            'cash_revenue' => $fmt($cashRevenue) . ' UGX',
            'mm_revenue' => $fmt($mobileMoneyRevenue) . ' UGX',
            'total_revenue' => $fmt($totalRevenue) . ' UGX',
            'total_withdrawals' => $fmt($totalWithdrawals) . ' UGX',
            'total_charges' => $fmt($totalCharges) . ' UGX',
            'balance' => $fmt($balance) . ' UGX',
        ];
    }

    protected function fetchRouterStats(RouterConfiguration $router): array
    {
        try {
            $mikrotik = new MikroTikService($router);
            return $mikrotik->getUserStatistics();
        } catch (\Exception $e) {
            Log::warning("Could not fetch router stats for {$router->name}: " . $e->getMessage());
            return [];
        }
    }
}
