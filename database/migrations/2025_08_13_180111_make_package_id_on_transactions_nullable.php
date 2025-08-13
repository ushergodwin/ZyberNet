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
            $table->unsignedBigInteger('package_id')->nullable()->change(); // Make package_id nullable
            // apply foreign key constraint
            $table->dropForeign(['package_id']); // Drop existing foreign key constraint
            $table->foreign('package_id')
                ->references('id')
                ->on('voucher_packages')
                ->onDelete('cascade'); // Add foreign key constraint with onDelete cascade
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
                ->onDelete('cascade'); // Revert package_id to non-nullable with foreign key constraint
        });
    }
};