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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('phone_number', 20)->index(); // Phone number of the user
            $table->decimal('amount', 10, 2);
            $table->string('currency');
            $table->string('status', 100)->default('new')->index();
            $table->bigInteger('payment_id')->nullable()->index(); // Payment gateway transaction ID
            $table->string('mfscode')->nullable();
            $table->foreignId('package_id')->constrained('voucher_packages');
            $table->json('response_json')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
