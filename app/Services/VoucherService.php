<?php

namespace App\Services;

use App\Models\Voucher;
use App\Models\RouterConfiguration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\MikroTikService;

class VoucherService
{
    public function createVouchersAndPushToRouter(array $voucherDataList, RouterConfiguration $router)
    {
        $processedVouchers = [];
        DB::beginTransaction();

        try {
            $mikrotik = new MikroTikService($router);

            foreach ($voucherDataList as $voucherData) {
                $existingVoucher = null;

                // Check if transaction_id already exists
                if (!empty($voucherData['transaction_id'])) {
                    $existingVoucher = Voucher::withTrashed()->where('transaction_id', $voucherData['transaction_id'])->first();
                }

                if ($existingVoucher) {
                    // Transaction already has a voucher, return it
                    $processedVouchers[] = $existingVoucher;
                    continue;
                }

                // 1. Create hotspot user on router
                $mikrotik->createHotspotUser(
                    $voucherData['code'],
                    $voucherData['code'],
                    $voucherData['session_timeout'],
                    $voucherData['profile_name']
                );

                // 2. Save voucher to DB
                $processedVouchers[] = Voucher::create([
                    'code'           => $voucherData['code'],
                    'package_id'     => $voucherData['package_id'],
                    'expires_at'     => $voucherData['expires_at'],
                    'router_id'      => $router->id,
                    'transaction_id' => $voucherData['transaction_id'] ?? null,
                ]);
            }

            DB::commit();
            return $processedVouchers;
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Voucher generation failed: ' . $e->getMessage());
            throw $e;
        }
    }


    // delete voucher from router and database
    public function deleteVoucher(Voucher $voucher)
    {
        DB::beginTransaction();

        try {
            // 1. Delete hotspot user from router
            $mikrotik = new MikroTikService($voucher->router);
            $mikrotik->deleteHotspotUser($voucher->code);

            // 2. Delete voucher from database
            $voucher->delete();

            DB::commit();
            return true;
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Failed to delete voucher: ' . $e->getMessage());
            throw $e;
        }
    }
}
