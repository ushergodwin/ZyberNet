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
        Schema::create('voucher_packages', function (Blueprint $table) {
            $table->id();

            // Basic details
            $table->string('name', 50)->index();            // e.g. 'Gold', '300MB 2Mbps'
            $table->decimal('price', 10, 2);                // e.g. 1000.00 UGX

            // Router profile mapping
            $table->string('profile_name');
            $table->string('rate_limit')->nullable();       // e.g. '2M/2M'
            $table->string('session_timeout')->nullable();  // e.g. '1h', enforced by router
            $table->bigInteger('limit_bytes_total')->nullable(); // e.g. 300MB
            $table->unsignedTinyInteger('shared_users')->default(1); // How many devices

            // Meta
            $table->boolean('is_active')->default(true)->index();
            $table->text('description')->nullable();        // Optional admin reference
            $table->timestamps();
            $table->softDeletes();                          // For safe deletion
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voucher_packages');
    }
};