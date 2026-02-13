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

    public function deleteVouchers($vouchers)
    {
        DB::beginTransaction();

        $deleted = [];
        $failed = [];

        try {
            foreach ($vouchers as $voucher) {
                try {
                    // router deletion must be one by one
                    $mikrotik = new MikroTikService($voucher->router);
                    $mikrotik->deleteHotspotUser($voucher->code);

                    // database deletion
                    $voucher->delete();

                    $deleted[] = $voucher->code;
                } catch (\Throwable $e) {
                    Log::error("Failed to delete voucher {$voucher->code}: " . $e->getMessage());
                    $failed[] = $voucher->code;
                    // continue to the next voucher instead of rollback
                }
            }

            // ✅ commit only DB deletions that worked
            DB::commit();

            return [
                'deleted' => $deleted,
                'failed' => $failed
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Batch voucher deletion failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * @param array $exclude Codes to exclude (e.g. codes already generated in the current batch)
     */
    static function generateVoucherCode(int $length = 6, string $type = 'nl', array $exclude = []): string
    {
        $characters = match ($type) {
            'n' => '0123456789',
            'l' => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
            default => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789',
        };

        $maxIndex = strlen($characters) - 1;
        $excludeSet = array_flip($exclude);
        $maxAttempts = 20;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            // After 10 failed attempts, increase length by 1
            $currentLength = $attempt >= 10 ? $length + 1 : $length;

            $code = '';
            for ($i = 0; $i < $currentLength; $i++) {
                $code .= $characters[random_int(0, $maxIndex)];
            }

            // Check both in-batch duplicates and DB
            if (!isset($excludeSet[$code]) && !Voucher::withTrashed()->where('code', $code)->exists()) {
                return $code;
            }
        }

        // Last resort: use length + 2
        $code = '';
        for ($i = 0; $i < $length + 2; $i++) {
            $code .= $characters[random_int(0, $maxIndex)];
        }

        return $code;
    }
}