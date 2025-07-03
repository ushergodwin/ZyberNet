<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use App\Models\VoucherPackage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
            ->orderBy('created_at', 'desc')
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
            $transaction = $voucher->transaction;
            if (!$transaction) {
                $transaction = null;
            }
            return response()->json([
                'transaction' => $transaction,
            ]);
            return response()->json();
        } catch (\Exception $e) {
            return response()->json(['message' => 'Voucher not found'], 404);
        }
    }

    // generateVoucher
    public function generateVoucher(Request $request)
    {
        $request->validate([
            'package_id' => 'required|exists:voucher_packages,id',
            'quantity'   => 'required|integer|min:1',
        ]);

        $package = $request->input('package_id');
        $quantity = $request->input('quantity');

        $vouchers = [];
        for ($i = 0; $i < $quantity; $i++) {
            $voucherPackage = VoucherPackage::findOrFail($package);
            $session_timeout = substr($voucherPackage->session_timeout, 0, -1); // Remove the last character (h or d)
            $session_timeout_unit = substr($voucherPackage->session_timeout, -1); // Get the last character (h or d)
            // Calculate expiration date based on session timeout
            $expiresAt = now()->add($session_timeout_unit === 'd' ? $session_timeout . ' days' : $session_timeout . ' hours');
            $code = strtoupper(Str::random(8)); // Generate a random voucher code
            $vouchers[] = Voucher::create([
                'code'       => $code,
                'package_id' => $package,
                'expires_at' => $expiresAt,
            ]);
        }

        return response()->json([
            'message' => 'Vouchers generated successfully',
            'vouchers' => $vouchers,
            'sync_vouchers' => config('app.sync_vouchers_from_local') ? true : false,
        ]);
    }

    // saveVoucherTransaction
    public function saveVoucherTransaction(Request $request, $id)
    {
        $request->validate([
            'phone_number' => 'required|string',
            'amount' => 'required|string|max:20',
            'currency' => 'required|string|max:3',
            'status' => 'required|string|in:pending,successful,failed',
        ]);

        $voucher = Voucher::with('package')->findOrFail($id);
        // generate a unique payment ID, only digits, 8 characters long prefix ME
        $paymentId = '90' . random_int(10000000, 99999999);
        $transactionData = [
            'phone_number' => $request->input('phone_number'),
            'amount' => intval($request->input('amount')),
            'currency' => $request->input('currency'),
            'status' => $request->input('status'),
            'payment_id' => $paymentId,
            'mfscode' => uniqid('ME'),
            'package_id' => $voucher->package->id,
            'channel' => 'cash',
        ];

        $transaction = $voucher->transaction()->create($transactionData);
        $voucher->transaction_id = $transaction->id;
        $voucher->save();

        return response()->json([
            'message' => 'Voucher transaction saved successfully',
            'voucher' => $voucher,
        ]);
    }

    public function syncVouchersFromLocalHost(Request $request,)
    {
        try {
            // if request origin is not == to config('app.local_domain'), return 403
            if ($request->getHost() !== config('app.local_domain')) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            $vouchers = $request->input('vouchers', []);
            if (empty($vouchers)) {
                return response()->json(['message' => 'No vouchers to sync'], 400);
            }

            foreach ($vouchers as $voucherData) {
                DB::table('vouchers')->updateOrInsert(
                    ['code' => $voucherData['code']],
                    [
                        'package_id' => $voucherData['package_id'],
                        'expires_at' => $voucherData['expires_at'],
                        'is_used' => $voucherData['is_used'] ?? 0,
                        'transaction_id' => $voucherData['transaction_id'] ?? null,
                    ]
                );
            }
        } catch (\Throwable $th) {
            //throw $th;
            Log::error('Voucher sync error: ' . $th->getMessage(), [
                'vouchers' => $vouchers,
                'error' => $th->getMessage(),
            ]);
            return response()->json(['message' => 'An error occurred while syncing vouchers', 'error' => $th->getMessage()], 500);
        }
    }
}