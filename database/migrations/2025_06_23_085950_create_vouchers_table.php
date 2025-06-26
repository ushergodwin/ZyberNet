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
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();                  // Unique voucher code
            $table->foreignId('transaction_id')->nullable()->constrained(); // Link to transaction
            $table->foreignId('package_id')->constrained('voucher_packages');     // Link to voucher package
            $table->timestamp('expires_at')->index();                   // When the voucher expires
            $table->boolean('is_used')->default(false)->index(); // Whether the voucher has been used
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};