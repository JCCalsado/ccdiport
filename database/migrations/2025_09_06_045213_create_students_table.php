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
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->string('student_id')->unique();
            $table->string('last_name');
            $table->string('first_name');
            $table->string('middle_initial')->nullable();
            $table->string('email')->unique();
            $table->string('course')->nullable();
            $table->string('year_level')->nullable();
            $table->enum('status', ['enrolled', 'active', 'inactive', 'graduated', 'dropped'])->default('enrolled');
            $table->date('birthday')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->decimal('total_balance', 10, 2)->default(0);
            $table->string('account_id')->nullable();
            $table->timestamps();
            
            // Foreign key for account_id
            $table->foreign('account_id')
                  ->references('id')
                  ->on('accounts')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};