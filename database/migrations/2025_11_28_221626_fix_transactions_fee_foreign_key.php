<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Drop the old column if it exists without constraint
            if (Schema::hasColumn('transactions', 'fee_id')) {
                // Check if foreign key exists
                $foreignKeys = DB::select(
                    "SELECT CONSTRAINT_NAME 
                     FROM information_schema.KEY_COLUMN_USAGE 
                     WHERE TABLE_NAME = 'transactions' 
                     AND COLUMN_NAME = 'fee_id' 
                     AND CONSTRAINT_SCHEMA = DATABASE()"
                );
                
                if (empty($foreignKeys)) {
                    // No foreign key, add it
                    $table->foreign('fee_id')
                        ->references('id')
                        ->on('fees')
                        ->onDelete('set null');
                }
            } else {
                // Column doesn't exist, create it with foreign key
                $table->foreignId('fee_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained()
                    ->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['fee_id']);
        });
    }
};