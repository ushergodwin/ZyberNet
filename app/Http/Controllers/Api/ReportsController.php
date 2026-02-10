<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DashboardStatistic;
use App\Models\RouterConfiguration;
use App\Models\Transaction;
use App\Models\Voucher;
use App\Models\VoucherPackage;
use App\Services\MikroTikService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ReportsController extends Controller
{
    public function getStatistics(Request $request)
    {
        $routerId = $request->router_id ?? null;
        $dateFrom = $request->date_from;
        $dateTo = $request->date_to;
        $allTime = $request->all ?? false;

        // Determine if we can use cached stats
        $isCustomDateRange = !$allTime && $dateFrom && $dateTo;
        $period = $allTime ? 'all_time' : 'current_month';

        // Try cached stats for non-custom date ranges
        if (!$isCustomDateRange) {
            $cached = DashboardStatistic::where('router_id', $routerId ?: null)
                ->where('period', $period)
                ->first();

            if ($cached) {
                return $this->respondWithStats($cached->statistics, $routerId);
            }
        }

        // Fall back to live queries for custom date ranges or if cache is empty
        return $this->respondWithLiveStats($routerId, $dateFrom, $dateTo, $allTime);
    }

    protected function respondWithStats(array $statistics, $routerId): \Illuminate\Http\JsonResponse
    {
        // Filter revenue stats based on permission
        if (!hasPermission('view_revenue_stats')) {
            // Only return router-specific stats (MikroTik data)
            $revenueKeys = [
                'total_vouchers', 'expired_vouchers', 'total_packages',
                'transactions', 'successful_txn', 'failed_tnx',
                'cash_revenue', 'mm_revenue', 'total_revenue',
                'total_withdrawals', 'total_charges', 'balance',
            ];
            $statistics = array_diff_key($statistics, array_flip($revenueKeys));
        }

        return response()->json($statistics);
    }

    protected function respondWithLiveStats($routerId, $dateFrom, $dateTo, $allTime): \Illuminate\Http\JsonResponse
    {
        if (!$allTime) {
            if ($dateFrom && $dateTo) {
                try {
                    $start = now()->parse($dateFrom)->startOfDay();
                    $end   = now()->parse($dateTo)->endOfDay();
                } catch (\Exception $e) {
                    return response()->json(['error' => 'Invalid date format. Use YYYY-MM-DD'], 400);
                }
            } else {
                $start = now()->startOfMonth();
                $end   = now()->endOfMonth();
            }
        }

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

        $fmt = fn($n) => number_format((float)$n, 2, '.', ',');

        $voucherBase = Voucher::withTrashed()
            ->when($routerId, fn($q) => $q->where('router_id', $routerId))
            ->when(!$allTime, fn($q) => $q->whereBetween('created_at', [$start, $end]));

        $statistics = [
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

        // Live MikroTik stats for custom date ranges
        $routerStats = [];
        if ($routerId) {
            $router = RouterConfiguration::find($routerId);
            if ($router && config('app.env') !== 'local') {
                try {
                    $mikrotik = new MikroTikService($router);
                    $routerStats = $mikrotik->getUserStatistics();
                } catch (\Exception $e) {
                    Log::warning("Could not fetch router stats: " . $e->getMessage());
                }
            }
        }

        $finalStats = $routerStats;
        if (hasPermission('view_revenue_stats')) {
            $finalStats = array_merge($statistics, $routerStats);
        }

        return response()->json($finalStats);
    }
}
