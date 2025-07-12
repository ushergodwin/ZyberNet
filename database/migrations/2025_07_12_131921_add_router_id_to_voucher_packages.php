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
                ->after('id'); // Assuming 'id' is the last column in voucher_packages
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
