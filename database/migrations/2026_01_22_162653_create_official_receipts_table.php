<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('official_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('account_id', 50)->index();
            $table->foreignId('payment_id')->constrained()->onDelete('cascade');
            $table->string('receipt_number', 50)->unique();
            $table->decimal('amount', 12, 2);
            $table->timestamp('issued_at');
            $table->foreignId('issued_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('official_receipts');
    }
};