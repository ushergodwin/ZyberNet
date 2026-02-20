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
        if (!hasPermission('view_vouchers')) {
            return response()->json([
                'message' => 'You are not authorized to view vouchers. Please contact system admin.'
            ], 401);
        }

        $searchTerm = $request->input('search');
        $routerId   = $request->input('router_id');
        $dateFrom   = $request->input('date_from'); // optional, format: Y-m-d
        $dateTo     = $request->input('date_to');   // optional, format: Y-m-d

        $vouchers = Voucher::query()
            ->where(fn($q) => $q->whereIn('gateway', ['shop', 'yopayments']))
            ->when($routerId, fn($q) => $q->where('router_id', $routerId))
            ->when($searchTerm, fn($q) => $this->applySearchFilter($q, $searchTerm))
            ->when($dateFrom, fn($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn($q) => $q->whereDate('created_at', '<=', $dateTo))
            ->with('package')
            ->orderByDesc('created_at')
            ->paginate(150);

        return response()->json($vouchers);
    }

    /**
     * Apply search filters to the query.
     */
    protected function applySearchFilter($query, $searchTerm)
    {
        return $query->when($searchTerm === 'active', fn($q) => $q->where(fn($sub) => $sub->whereNull('activated_at')->orWhere('expires_at', '>', now())))
            ->when($searchTerm === 'expired', fn($q) => $q->whereNotNull('activated_at')->where('expires_at', '<', now()))
            ->when($searchTerm === 'used', fn($q) => $q->where('is_used', 1))
            ->when($searchTerm === 'unused', fn($q) => $q->where('is_used', 0))
            ->when($searchTerm === 'activated:Y', fn($q) => $q->whereNotNull('activated_at'))
            ->when($searchTerm === 'activated:N', fn($q) => $q->whereNull('activated_at'))
            ->when(
                !in_array($searchTerm, ['active', 'expired', 'used', 'unused', 'activated:Y', 'activated:N']),
                fn($q) => $q->where(function ($sub) use ($searchTerm) {
                    $sub->where('code', 'like', "%$searchTerm%")
                        ->orWhereHas('package', fn($q2) => $q2->where('name', 'like', "%$searchTerm%"));
                })
            );
    }


    // get a single voucher
    public function getVoucher($id)
    {
        if (!hasPermission('view_vouchers')) {
            return response()->json(['message' => 'You are not authorized to view vouchers. Please contact system admin.'], 401);
        }
        $voucher = Voucher::with('package')->with('transaction')
            ->with('transaction.package')
            ->whereIN('gateway', ['shop', 'yopayments'])
            ->findOrFail($id);

        return response()->json($voucher);
    }

    //getVoucherTransaction
    public function getVoucherTransaction($id)
    {
        try {
            $voucher = Voucher::withTrashed()->with('transaction')
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
        if (!hasPermission('create_vouchers')) {
            return response()->json(['message' => 'You are not authorized to create vouchers. Please contact system admin.'], 401);
        }
        $request->validate([
            'package_id' => 'required|exists:voucher_packages,id',
            'quantity'   => 'required|integer|min:1',
            'voucher_format' => 'required|in:n,l,nl',
            'voucher_length' => 'required|integer|min:4|max:6'
        ]);

        $package = VoucherPackage::with('router')->findOrFail($request->input('package_id'));
        $quantity = $request->input('quantity');

        $voucherDataList = [];
        $batchCodes = [];

        $session_timeout = $package->session_timeout;
        $duration = (int) substr($session_timeout, 0, -1);
        $unit = substr($session_timeout, -1);
        $expiresAt = now()->add($unit === 'd' ? "{$duration} days" : "{$duration} hours");

        for ($i = 0; $i < $quantity; $i++) {
            $code = VoucherService::generateVoucherCode($request->voucher_length, $request->voucher_format, $batchCodes);
            $batchCodes[] = $code;
            $voucherDataList[] = [
                'code'            => $code,
                'package_id'      => $package->id,
                'expires_at'      => $expiresAt,
                'session_timeout' => $session_timeout,
                'profile_name'    => $package->profile_name,
                'gateway'         => 'shop',
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
            if (!hasPermission('delete_vouchers')) {
                return response()->json(['message' => 'You are not authorized to delete vouchers. Please contact system admin.'], 401);
            }
            $voucher = Voucher::with('router')->where('code', $code)->first();
            if (!$voucher) {
                return response()->json(['message' => 'Voucher not found'], 202);
            }
            if (!$voucher->router) {
                return response()->json(['message' => 'Router configuration not found for this voucher'], 404);
            }
            $voucherService = new VoucherService();
            $voucherService->deleteVoucher($voucher);
            return response()->json(['message' => 'Voucher deleted from router and database successfully']);
        } catch (\Throwable $e) {
            Log::error('Failed to delete voucher: ' . $e->getMessage(), [
                'code' => $code,
                'trace' => $e->getTrace(),
            ]);
            return response()->json(['message' => 'Failed to delete voucher', 'error' => $e->getMessage()], 500);
        }
    }

    public function deleteBatchVouchers(Request $request)
    {
        try {
            $validated = $request->validate([
                'vouchers' => 'required|array|min:1',
                'vouchers.*' => 'int|distinct'
            ]);

            $voucherIds = $validated['vouchers'];

            $vouchers = Voucher::with('router')->whereIn('id', $voucherIds)->get();

            if ($vouchers->isEmpty()) {
                return response()->json([
                    'message' => 'No matching vouchers found',
                ], 404);
            }

            $voucherService = new VoucherService();
            $result = $voucherService->deleteVouchers($vouchers);

            return response()->json([
                'message' => 'Batch deletion of vouchers completed successfully!',
                'deleted' => $result['deleted'],
                'failed' => $result['failed'],
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to delete vouchers: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to delete vouchers. Kindly contact IT for support!',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
