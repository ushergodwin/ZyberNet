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
            $table->string('profile', 100)->nullable()->default('default')->index()->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('voucher_packages', function (Blueprint $table) {
            //
            // Remove the profile column if it exists
            $table->dropColumn('profile');
        });
    }
};
