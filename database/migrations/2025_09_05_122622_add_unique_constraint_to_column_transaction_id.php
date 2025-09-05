<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Remove duplicate vouchers and keep the latest (highest id) per transaction_id
        DB::statement("
            DELETE v1
            FROM vouchers v1
            JOIN vouchers v2 
              ON v1.transaction_id = v2.transaction_id 
             AND v1.id < v2.id
        ");

        // 2. Add unique constraint
        Schema::table('vouchers', function (Blueprint $table) {
            $table->unique('transaction_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropUnique(['transaction_id']);
        });
    }
};
