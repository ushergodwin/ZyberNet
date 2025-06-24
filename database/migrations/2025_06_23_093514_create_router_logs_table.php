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
        Schema::create('router_logs', function (Blueprint $table) {
            $table->id();
            $table->string('voucher')->nullable(); // Voucher code if applicable
            $table->string('action'); // e.g., create-hotspot-user
            $table->boolean('success')->default(false)->index();
            $table->text('message')->nullable(); // error or response
            $table->boolean('is_manual')->default(false)->index(); // true if manually triggered
            $table->string('router_name')->nullable(); // Name of the router
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('router_logs');
    }
};
