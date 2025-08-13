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
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('package_id')
                ->nullable()
                ->constrained('voucher_packages')
                ->onDelete('set null')
                ->change(); // Make package_id nullable and set foreign key constraint
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            //
            $table->foreignId('package_id')
                ->constrained('voucher_packages')
                ->onDelete('cascade')
                ->change(); // Revert package_id to non-nullable with foreign key constraint
        });
    }
};