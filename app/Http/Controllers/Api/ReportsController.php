<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RouterConfiguration;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherPackage;
use App\Services\MikroTikService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ReportsController extends Controller
{
    //

    // get statistics data 
    public function getStatistics(Request $request)
    {
        $routerId = $request->router_id ?? 1;

        // Base successful filter (reuse via clones)
        $base = Transaction::where('status', 'successful')
            ->when($routerId, fn($q) => $q->where('router_id', $routerId));

        // Revenues (positives only)
        $totalRevenue        = (clone $base)->where('amount', '>', 0)->sum('amount');
        $cashRevenue         = (clone $base)->where('channel', 'cash')->where('amount', '>', 0)->sum('amount');
        $mobileMoneyRevenue  = (clone $base)->where('channel', 'mobile_money')->where('amount', '>', 0)->sum('amount');

        // Withdrawals (negatives only) and balance
        $totalWithdrawalsRaw = (clone $base)->where('amount', '<', 0)->sum('amount');
        $totalWithdrawals    = abs($totalWithdrawalsRaw);
        $balance             = $totalRevenue - $totalWithdrawals;

        // Helper to format once (force float, set separators)
        $fmt = fn($n) => number_format((float)$n, 2, '.', ',');

        $statistics = [
            'total_vouchers' => Voucher::when($routerId, fn($q) => $q->where('router_id', $routerId))->count(),
            'expired_vouchers' => Voucher::where('expires_at', '<', now())->when($routerId, fn($q) => $q->where('router_id', $routerId))->count(),
            'total_packages' => VoucherPackage::when($routerId, fn($q) => $q->where('router_id', $routerId))->count(),
            'active_routers' => RouterConfiguration::when($routerId, fn($q) => $q->where('id', $routerId))->count(),
            'transactions' => Transaction::when($routerId, fn($q) => $q->where('router_id', $routerId))->count(),
            'successful_transactions' => Transaction::where('status', 'successful')->when($routerId, fn($q) => $q->where('router_id', $routerId))->count(),
            'failed_transactions' => Transaction::where('status', 'failed')->when($routerId, fn($q) => $q->where('router_id', $routerId))->count(),

            // Format ONCE here; no intval anywhere
            'cash_revenue'          => $fmt($cashRevenue) . ' UGX',
            'mobile_money_revenue'  => $fmt($mobileMoneyRevenue) . ' UGX',
            'total_revenue'         => $fmt($totalRevenue) . ' UGX',
            'total_withdrawals'     => $fmt($totalWithdrawals) . ' UGX',
            'balance'               => $fmt($balance) . ' UGX',
        ];

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

        return response()->json(array_merge($routerStats, $statistics));
    }
}