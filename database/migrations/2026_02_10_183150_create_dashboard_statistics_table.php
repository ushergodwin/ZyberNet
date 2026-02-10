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
        Schema::create('dashboard_statistics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('router_id')->nullable(); // null = all routers
            $table->string('period'); // 'current_month' or 'all_time'
            $table->json('statistics');
            $table->timestamp('computed_at');
            $table->timestamps();

            $table->unique(['router_id', 'period']);
            $table->foreign('router_id')->references('id')->on('router_configurations')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dashboard_statistics');
    }
};
