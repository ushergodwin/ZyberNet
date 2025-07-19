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
        $totalRevenue = Transaction::where('status', 'successful')->when($routerId, function ($query) use ($routerId) {
            return $query->where('router_id', $routerId);
        })->sum('amount');
        $cashRevenue = Transaction::where('status', 'successful')->where('channel', 'cash')->where(function ($query) use ($routerId) {
            if ($routerId) {
                $query->where('router_id', $routerId);
            }
        })->sum('amount');
        $mobileMoneyRevenue = Transaction::where('status', 'successful')->where('channel', 'mobile_money')
            ->where(function ($query) use ($routerId) {
                if ($routerId) {
                    $query->where('router_id', $routerId);
                }
            })->sum('amount');
        // Ensure that the total revenue is formatted correctly
        $totalRevenue = number_format(intval($totalRevenue), 2);
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
