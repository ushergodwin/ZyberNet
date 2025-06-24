<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use Illuminate\Http\Request;

class VoucherController extends Controller
{
    //

    // get a list of vouchers
    public function getVouchers(Request $request)
    {
        $searchTerm = $request->input('search');
        $vouchers = Voucher::when($searchTerm === 'active', function ($query) {
            $query->where('expires_at', '>', now());
        })
            // when search term == 'expired', filter by expires_at < now
            ->when($searchTerm === 'expired', function ($query) {
                $query->where('expires_at', '<', now());
            })
            // search term == used filter is_used === 1
            ->when($searchTerm === 'used', function ($query) {
                $query->where('is_used', 1);
            })
            // search term == unused filter is_used === 0
            ->when($searchTerm === 'unused', function ($query) {
                $query->where('is_used', 0);
            })
            ->when(!in_array($searchTerm, ['active', 'expired', 'used', 'unused']), function ($query) use ($searchTerm) {
                $query->where('code', 'like', '%' . $searchTerm . '%')
                    ->orWhereHas('package', function ($q) use ($searchTerm) {
                        $q->where('name', 'like', '%' . $searchTerm . '%');
                    });
            })
            ->with('package')
            ->paginate(10);

        return response()->json($vouchers);
    }

    // get a single voucher
    public function getVoucher($id)
    {
        $voucher = Voucher::with('package')->with('transaction')
            ->with('transaction.package')->findOrFail($id);
        return response()->json($voucher);
    }

    //getVoucherTransaction
    public function getVoucherTransaction($id)
    {
        try {
            $voucher = Voucher::with('transaction')
                ->with('transaction.package')->findOrFail($id);
            return response()->json($voucher->transaction);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Voucher not found'], 404);
        }
    }
}
