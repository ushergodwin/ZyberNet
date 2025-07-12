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
            $table->foreignId('router_id')
                ->nullable()
                ->constrained('router_configurations')
                ->onDelete('set null')
                ->after('package_id'); // Assuming 'package_id' is the last column in transactions
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            //
            $table->dropForeign(['router_id']);
        });
    }
};
