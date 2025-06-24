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
            $table->string('name', 20)->index(); // Name of the voucher package
            $table->integer('duration_minutes'); // How long the voucher lasts
            $table->decimal('price', 10, 2);     // Price in UGX
            $table->integer('speed_limit')->nullable(); // Optional KBps
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
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
