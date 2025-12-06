<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Verify all students have account_id
        $missingCount = DB::table('students')->whereNull('account_id')->count();
        
        if ($missingCount > 0) {
            throw new \Exception(
                "Cannot enforce constraints: {$missingCount} students still missing account_id. " .
                "Run: php artisan migrate --path=database/migrations/2025_12_01_021940_backfill_account_ids.php"
            );
        }

        // Step 2: Make account_id NOT NULL
        Schema::table('students', function (Blueprint $table) {
            $table->string('account_id', 50)->nullable(false)->change();
        });

        // Step 3: Ensure all financial tables have account_id NOT NULL where applicable
        $tables = ['student_payment_terms', 'student_assessments', 'transactions', 'payments'];
        
        foreach ($tables as $tableName) {
            $missingInTable = DB::table($tableName)->whereNull('account_id')->count();
            
            if ($missingInTable > 0) {
                $this->command->warn("⚠️  {$tableName} has {$missingInTable} records without account_id");
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->string('account_id', 50)->nullable(false)->change();
            });
            
            $this->command->info("✓ {$tableName}.account_id is now NOT NULL");
        }

        $this->command->info('✅ All constraints enforced successfully!');
    }

    public function down(): void
    {
        $tables = ['students', 'student_payment_terms', 'student_assessments', 'transactions', 'payments'];
        
        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('account_id', 50)->nullable()->change();
            });
        }

        $this->command->info('✓ Constraints relaxed (account_id nullable again)');
    }
};