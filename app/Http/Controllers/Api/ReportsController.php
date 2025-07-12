<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RouterConfiguration;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherPackage;
use Illuminate\Http\Request;

class ReportsController extends Controller
{
    //

    // get statistics data 
    public function getStatistics(Request $request)
    {
        $routerId = $request->router_id;
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
            'total_users' => User::count(),
            'total_vouchers' => Voucher::when($routerId, function ($query) use ($routerId) {
                return $query->where('router_id', $routerId);
            })->count(),
            'active_vouchers' => Voucher::where('expires_at', '>', now())
                ->when($routerId, function ($query) use ($routerId) {
                    return $query->where('router_id', $routerId);
                })->count(),
            'expired_vouchers' => Voucher::where('expires_at', '<', now())->when($routerId, function ($query) use ($routerId) {
                return $query->where('router_id', $routerId);
            })->count(),
            'used_vouchers' => Voucher::where('is_used', 1)->when($routerId, function ($query) use ($routerId) {
                return $query->where('router_id', $routerId);
            })->count(),
            'unused_vouchers' => Voucher::where('is_used', 0)->when($routerId, function ($query) use ($routerId) {
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

        return response()->json($statistics);
    }
}
