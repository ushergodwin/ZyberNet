<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use App\Models\VoucherPackage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Services\VoucherService;

class VoucherController extends Controller
{
    //

    // get a list of vouchers
    public function getVouchers(Request $request)
    {
        $searchTerm = $request->input('search');
        $routerId = $request->input('router_id');

        $vouchers = Voucher::when($routerId, function ($query) use ($routerId, $searchTerm) {
            $query->where('router_id', $routerId)
                ->when($searchTerm === 'active', function ($query) {
                    $query->where('expires_at', '>', now());
                })
                ->when($searchTerm === 'expired', function ($query) {
                    $query->where('expires_at', '<', now());
                })
                ->when($searchTerm === 'used', function ($query) {
                    $query->where('is_used', 1);
                })
                ->when($searchTerm === 'unused', function ($query) {
                    $query->where('is_used', 0);
                })
                ->when(!in_array($searchTerm, ['active', 'expired', 'used', 'unused']), function ($query) use ($searchTerm) {
                    $query->where(function ($q) use ($searchTerm) {
                        $q->where('code', 'like', '%' . $searchTerm . '%')
                            ->orWhereHas('package', function ($q2) use ($searchTerm) {
                                $q2->where('name', 'like', '%' . $searchTerm . '%');
                            });
                    });
                });
        }, function ($query) use ($searchTerm) {
            $query->when($searchTerm === 'active', function ($query) {
                $query->where('expires_at', '>', now());
            })
                ->when($searchTerm === 'expired', function ($query) {
                    $query->where('expires_at', '<', now());
                })
                ->when($searchTerm === 'used', function ($query) {
                    $query->where('is_used', 1);
                })
                ->when($searchTerm === 'unused', function ($query) {
                    $query->where('is_used', 0);
                })
                ->when(!in_array($searchTerm, ['active', 'expired', 'used', 'unused']), function ($query) use ($searchTerm) {
                    $query->where(function ($q) use ($searchTerm) {
                        $q->where('code', 'like', '%' . $searchTerm . '%')
                            ->orWhereHas('package', function ($q2) use ($searchTerm) {
                                $q2->where('name', 'like', '%' . $searchTerm . '%');
                            });
                    });
                });
        })
            ->with('package')
            ->orderBy('created_at', 'desc')
            ->paginate(50);


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

        $package = VoucherPackage::with('router')->findOrFail($request->input('package_id'));
        $quantity = $request->input('quantity');

        $voucherDataList = [];

        for ($i = 0; $i < $quantity; $i++) {
            $session_timeout = $package->session_timeout;
            $duration = (int) substr($session_timeout, 0, -1);
            $unit = substr($session_timeout, -1);

            $expiresAt = now()->add($unit === 'd' ? "{$duration} days" : "{$duration} hours");
            $code = strtoupper(Str::random(6));
            $voucherDataList[] = [
                'code'            => $code,
                'package_id'      => $package->id,
                'expires_at'      => $expiresAt,
                'session_timeout' => $session_timeout,
                'profile_name'    => $package->profile_name,
            ];
        }

        try {
            $voucherService = new VoucherService();
            $vouchers = $voucherService->createVouchersAndPushToRouter($voucherDataList, $package->router);

            return response()->json([
                'message' => 'Vouchers created and pushed to Router successfully',
                'vouchers' => $vouchers,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Voucher creation failed',
                'error' => $e->getMessage(),
            ], 500);
        }
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

        $voucher = Voucher::with('package')->with('router')->findOrFail($id);
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
            'router_id' => $voucher->router->id,
        ];

        $transaction = $voucher->transaction()->create($transactionData);
        $voucher->transaction_id = $transaction->id;
        $voucher->save();

        return response()->json([
            'message' => 'Voucher transaction saved successfully',
            'voucher' => $voucher,
        ]);
    }

    // deleteVoucher
    public function destroy($code)
    {
        try {
            $voucher = Voucher::with('router')->where('code', $code)->first();
            if (!$voucher) {
                return response()->json(['message' => 'Voucher not found'], 202);
            }
            $voucherService = new VoucherService();
            $voucherService->deleteVoucher($code, $voucher->router);
            return response()->json(['message' => 'Voucher deleted from router and database successfully']);
        } catch (\Throwable $e) {
            Log::error('Failed to delete voucher: ' . $e->getMessage(), [
                'code' => $code,
                'trace' => $e->getTrace(),
            ]);
            return response()->json(['message' => 'Failed to delete voucher', 'error' => $e->getMessage()], 500);
        }
    }
}
