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
        Schema::create('support_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('type', 100)->index();
            $table->string('phone_number', 15)->index()->nullable();
            // email 
            $table->string('email', 100)->index()->nullable();
            // router_id
            $table->foreignId('router_id')->nullable()->constrained('router_configurations')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_contacts');
    }
};