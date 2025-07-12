<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SetDefaultRouterIdOnRelatedTables extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        try {
            DB::beginTransaction();
            // Set default router_id to 1 for all related tables
            DB::table('voucher_packages')->whereNull('router_id')->update(['router_id' => 1]);
            DB::table('transactions')->whereNull('router_id')->update(['router_id' => 1]);
            DB::table('router_logs')->whereNull('router_id')->update(['router_id' => 1]);
            DB::table('vouchers')->whereNull('router_id')->update(['router_id' => 1]);
            DB::commit();
        } catch (\Exception $e) {
            // Handle any exceptions that may occur
            DB::rollBack();
            Log::error('Error setting default router_id: ' . $e->getMessage());
        }
    }
}
