<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Disable foreign key checks temporarily
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // 1. Delete all transactions
        DB::table('transactions')->truncate();

        // 2. Delete all payments
        DB::table('payments')->truncate();

        // 3. Delete all student assessments
        DB::table('student_assessments')->truncate();

        // 4. Delete all student enrollments
        DB::table('student_enrollments')->truncate();

        // 5. Delete all student curricula
        DB::table('student_curricula')->truncate();

        // 6. Reset all account balances to 0
        DB::table('accounts')->update(['balance' => 0]);

        // 7. Reset all student total_balance to 0
        DB::table('students')->update(['total_balance' => 0]);

        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration cannot be reversed as it deletes data
        // If you need to restore data, use a database backup
    }
};