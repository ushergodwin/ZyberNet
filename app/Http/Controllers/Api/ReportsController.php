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

        $totalRevenue = Transaction::where('status', 'successful')->sum('amount');
        // Ensure that the total revenue is formatted correctly
        $totalRevenue = number_format(intval($totalRevenue), 2);
        $statistics = [
            'total_users' => User::count(),
            'total_vouchers' => Voucher::count(),
            'active_vouchers' => Voucher::where('expires_at', '>', now())->count(),
            'inactive_vouchers' => Voucher::where('expires_at', '>', now())->where('is_used', 0)->count(),
            'expired_vouchers' => Voucher::where('expires_at', '<', now())->count(),
            'used_vouchers' => Voucher::where('is_used', 1)->count(),
            'unused_vouchers' => Voucher::where('is_used', 0)->count(),
            'total_packages' => VoucherPackage::count(),
            'active_routers' => RouterConfiguration::count(),
            'transactions' => Transaction::count(),
            'successful_transactions' => Transaction::where('status', 'successful')->count(),
            'failed_transactions' => Transaction::where('status', 'failed')->count(),
            'total_revenue' => $totalRevenue . ' UGX',
        ];

        return response()->json($statistics);
    }
}
