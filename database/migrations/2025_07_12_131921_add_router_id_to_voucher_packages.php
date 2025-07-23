<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('voucher_packages', function (Blueprint $table) {
            //
            $table->foreignId('router_id')
                ->nullable()
                ->constrained('router_configurations')
                ->onDelete('set null')
                ->after('id');
            // unique index on profile_name and router_id
            $table->unique(['profile_name', 'router_id'], 'unique_profile_router');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('voucher_packages', function (Blueprint $table) {
            //
            $table->dropForeign(['router_id']);
        });
    }
};