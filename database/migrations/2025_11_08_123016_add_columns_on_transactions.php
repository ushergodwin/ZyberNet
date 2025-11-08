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
            //
            $table->decimal('charge', 8, 2)->default(0)->after('amount')->index()->nullable(); // charge amount
            // total amount after deducting charge
            $table->decimal('total_amount', 10, 2)->default(0)->after('charge')->index()->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            //
            $table->dropColumn('charge');
            $table->dropColumn('total_amount');
        });
    }
};
