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
        $createdVouchers = [];
        DB::beginTransaction();

        try {
            $mikrotik = new MikroTikService($router);

            foreach ($voucherDataList as $voucherData) {
                // 1. Create hotspot user on router
                $mikrotik->createHotspotUser(
                    $voucherData['code'],
                    $voucherData['code'],
                    $voucherData['session_timeout'],
                    $voucherData['profile_name']
                );

                // 2. Save voucher to DB
                $createdVouchers[] = Voucher::create([
                    'code' => $voucherData['code'],
                    'package_id' => $voucherData['package_id'],
                    'expires_at' => $voucherData['expires_at'],
                    'router_id' => $router->id,
                ]);
            }

            DB::commit();
            return $createdVouchers;
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Voucher generation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    // delete voucher from router and database
    public function deleteVoucher($voucherCode, RouterConfiguration $router)
    {
        $voucher = Voucher::where('code', $voucherCode)->firstOrFail();
        DB::beginTransaction();

        try {
            // 1. Delete hotspot user from router
            $mikrotik = new MikroTikService($router);
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
