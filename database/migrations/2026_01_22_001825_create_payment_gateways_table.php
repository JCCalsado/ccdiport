<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateways', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // "PayMongo", "GCash", "Maya"
            $table->string('slug')->unique(); // "paymongo", "gcash", "maya"
            $table->string('logo_url')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(false);
            $table->json('supported_methods')->nullable(); // ["card", "gcash", "grab_pay"]
            $table->json('fees')->nullable(); // {"percentage": 2.5, "fixed": 15}
            $table->json('config')->nullable(); // API keys, settings
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            $table->index('slug');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateways');
    }
};