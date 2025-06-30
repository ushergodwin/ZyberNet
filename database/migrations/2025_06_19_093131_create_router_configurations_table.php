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
        Schema::create('router_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->default('Default Router')->index();
            $table->string('host', 60)->unique()->index(); // Router IP or hostname
            $table->integer('port')->default(8728); // API port
            $table->string('username', 100);
            $table->string('password')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('router_configurations');
    }
};