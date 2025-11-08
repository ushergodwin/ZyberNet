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
        Schema::create('transaction_charges', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('min_amount')->default(500)->index(); // in ugx
            $table->bigInteger('max_amount')->default(5000000)->index(); // in ugx
            $table->decimal('charge', 8, 2)->default(0)->index(); // charge amount
            $table->enum('network', ['MTN', 'AIRTEL'])->nullable()->index();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_charges');
    }
};
