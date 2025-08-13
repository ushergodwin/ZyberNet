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
        // Total revenue (only positive amounts)
        $totalRevenue = Transaction::where('status', 'successful')
            ->when($routerId, function ($query) use ($routerId) {
                return $query->where('router_id', $routerId);
            })
            ->where('amount', '>', 0)
            ->sum('amount');

        // Cash revenue (positive amounts only)
        $cashRevenue = Transaction::where('status', 'successful')
            ->where('channel', 'cash')
            ->where('amount', '>', 0)
            ->when($routerId, function ($query) use ($routerId) {
                $query->where('router_id', $routerId);
            })
            ->sum('amount');

        // Mobile money revenue (positive amounts only)
        $mobileMoneyRevenue = Transaction::where('status', 'successful')
            ->where('channel', 'mobile_money')
            ->where('amount', '>', 0)
            ->when($routerId, function ($query) use ($routerId) {
                $query->where('router_id', $routerId);
            })
            ->sum('amount');

        // Withdrawals (negative amounts only)
        $totalWithdrawals = Transaction::where('status', 'successful')
            ->when($routerId, function ($query) use ($routerId) {
                return $query->where('router_id', $routerId);
            })
            ->where('amount', '<', 0)
            ->sum('amount');

        // Since withdrawals are negative, make them positive for reporting
        $totalWithdrawals = abs($totalWithdrawals);

        // Balance = Total revenue - Withdrawals
        $balance = $totalRevenue - $totalWithdrawals;

        // Format numbers
        $totalRevenue     = number_format($totalRevenue, 2);
        $cashRevenue      = number_format($cashRevenue, 2);
        $mobileMoneyRevenue = number_format($mobileMoneyRevenue, 2);
        $totalWithdrawals = number_format($totalWithdrawals, 2);
        $balance          = number_format($balance, 2);

        $statistics = [
            'total_vouchers' => Voucher::when($routerId, function ($query) use ($routerId) {
                return $query->where('router_id', $routerId);
            })->count(),
            'expired_vouchers' => Voucher::where('expires_at', '<', now())->when($routerId, function ($query) use ($routerId) {
                return $query->where('router_id', $routerId);
            })->count(),
            'total_packages' => VoucherPackage::when($routerId, function ($query) use ($routerId) {
                return $query->where('router_id', $routerId);
            })->count(),
            'active_routers' => RouterConfiguration::when($routerId, function ($query) use ($routerId) {
                return $query->where('id', $routerId);
            })->count(),
            'transactions' => Transaction::when($routerId, function ($query) use ($routerId) {
                return $query->where('router_id', $routerId);
            })->count(),
            'successful_transactions' => Transaction::where('status', 'successful')
                ->when($routerId, function ($query) use ($routerId) {
                    return $query->where('router_id', $routerId);
                })->count(),
            'failed_transactions' => Transaction::where('status', 'failed')
                ->when($routerId, function ($query) use ($routerId) {
                    return $query->where('router_id', $routerId);
                })->count(),
            'cash_revenue' => number_format(intval($cashRevenue), 2) . ' UGX',
            'mobile_money_revenue' => number_format(intval($mobileMoneyRevenue), 2) . ' UGX',
            'total_revenue' => $totalRevenue . ' UGX',
            'total_withdrawals' => $totalWithdrawals . ' UGX',
            'balance' => $balance . ' UGX',
        ];

        $routerStats = [];

        if ($routerId) {
            $router = RouterConfiguration::find($routerId);
            if ($router && config('app.env') != 'local') {
                try {
                    $mikrotik = new MikroTikService($router);
                    $routerStats = $mikrotik->getUserStatistics();
                } catch (\Exception $e) {
                    Log::warning("Could not fetch router stats: " . $e->getMessage());
                }
            }
        }

        $statistics = array_merge($routerStats, $statistics);


        return response()->json($statistics);
    }
}