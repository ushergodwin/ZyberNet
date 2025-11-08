<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
        $allTime = $request->all ?? false; // if true, ignore date filters

        // Determine date range if not all-time
        if (!$allTime) {
            if ($dateFrom && $dateTo) {
                // Custom date range
                try {
                    $start = now()->parse($dateFrom)->startOfDay();
                    $end   = now()->parse($dateTo)->endOfDay();
                } catch (\Exception $e) {
                    return response()->json(['error' => 'Invalid date format. Use YYYY-MM-DD'], 400);
                }
            } else {
                // Default: current month
                $start = now()->startOfMonth();
                $end   = now()->endOfMonth();
            }
        }

        // Base transaction query (successful only)
        $base = Transaction::where('status', 'successful')
            ->when($routerId, fn($q) => $q->where('router_id', $routerId))
            ->when(!$allTime, fn($q) => $q->whereBetween('created_at', [$start, $end]));

        // Revenues (positives)
        $totalRevenue = (clone $base)->where('amount', '>', 0)->sum('amount');
        // total charges
        $totalCharges = (clone $base)->where('amount', '>', 0)->sum('charge');
        $cashRevenue = (clone $base)->where('channel', 'cash')->where('amount', '>', 0)->sum('amount');
        $mobileMoneyRevenue = (clone $base)->where('channel', 'mobile_money')->where('amount', '>', 0)->sum('amount');

        // Withdrawals (negatives)
        $totalWithdrawals = abs((clone $base)->where('amount', '<', 0)->sum('amount'));
        $balance = $totalRevenue - $totalWithdrawals;

        $fmt = fn($n) => number_format((float)$n, 2, '.', ',');

        // Vouchers
        $voucherBase = Voucher::withTrashed()
            ->when($routerId, fn($q) => $q->where('router_id', $routerId))
            ->when(!$allTime, fn($q) => $q->whereBetween('created_at', [$start, $end]));

        $statistics = [
            'total_vouchers' => $voucherBase->count(),
            'expired_vouchers' => (clone $voucherBase)->where('expires_at', '<', now())->count(),
            'total_packages' => VoucherPackage::when($routerId, fn($q) => $q->where('router_id', $routerId))->count(),
            'transactions' => Transaction::when($routerId, fn($q) => $q->where('router_id', $routerId))
                ->when(!$allTime, fn($q) => $q->whereBetween('created_at', [$start, $end]))->count(),
            'successful_transactions' => (clone $base)->count(),
            'failed_transactions' => Transaction::where('status', 'failed')
                ->when($routerId, fn($q) => $q->where('router_id', $routerId))
                ->when(!$allTime, fn($q) => $q->whereBetween('created_at', [$start, $end]))->count(),

            'cash_revenue' => $fmt($cashRevenue) . ' UGX',
            'mobile_money_revenue' => $fmt($mobileMoneyRevenue) . ' UGX',
            'total_revenue' => $fmt($totalRevenue) . ' UGX',
            'total_withdrawals' => $fmt($totalWithdrawals) . ' UGX',
            'total_charges' => $fmt($totalCharges) . ' UGX',
            'balance' => $fmt($balance) . ' UGX',
        ];

        // Router stats
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
