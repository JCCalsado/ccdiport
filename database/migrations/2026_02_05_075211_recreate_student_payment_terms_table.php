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
        Schema::create('student_payment_terms', function (Blueprint $table) {
            $table->id();
            
            // Foreign Keys
            $table->string('account_id', 50);
            $table->foreign('account_id')
                  ->references('account_id')
                  ->on('students')
                  ->onDelete('cascade');
            
            $table->unsignedBigInteger('user_id')->nullable(); // Backward compatibility
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
            
            $table->unsignedBigInteger('assessment_id');
            $table->foreign('assessment_id')
                  ->references('id')
                  ->on('student_assessments')
                  ->onDelete('cascade');
            
            // Term Details
            $table->string('school_year')->nullable(); // e.g., "2025-2026"
            $table->string('semester')->nullable(); // e.g., "1st Sem"
            $table->string('term_name'); // Upon Registration, Prelim, Midterm, etc.
            $table->integer('term_order')->default(0); // ✅ ADDED
            $table->date('due_date');
            
            // Financial Details
            $table->decimal('amount', 10, 2);
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->decimal('balance', 10, 2); // ✅ Auto-calculated
            
            // Status & Tracking
            $table->enum('status', ['pending', 'paid', 'partial', 'overdue'])->default('pending');
            $table->timestamp('payment_date')->nullable();
            $table->string('reference_number')->nullable();
            $table->text('remarks')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('account_id');
            $table->index('user_id');
            $table->index('assessment_id');
            $table->index('status');
            $table->index('due_date');
            $table->index(['account_id', 'status']); // Composite for common queries
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_payment_terms');
    }
};