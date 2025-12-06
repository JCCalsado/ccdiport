<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Models\Student;

return new class extends Migration
{
    public function up(): void
    {
        $this->command->info('🔧 Starting account_id backfill...');

        // Step 1: Generate account_id for students without one
        $studentsWithoutAccountId = DB::table('students')
            ->whereNull('account_id')
            ->orderBy('id')
            ->get();

        if ($studentsWithoutAccountId->isEmpty()) {
            $this->command->info('✅ All students already have account_id');
            return;
        }

        $date = now()->format('Ymd');
        $counter = $this->getNextAccountCounter($date);
        $generated = 0;

        foreach ($studentsWithoutAccountId as $student) {
            $accountId = "ACC-{$date}-" . str_pad($counter, 4, '0', STR_PAD_LEFT);
            
            // Ensure uniqueness
            while (DB::table('students')->where('account_id', $accountId)->exists()) {
                $counter++;
                $accountId = "ACC-{$date}-" . str_pad($counter, 4, '0', STR_PAD_LEFT);
            }
            
            DB::table('students')
                ->where('id', $student->id)
                ->update(['account_id' => $accountId]);
            
            $this->command->info("  ✓ Generated {$accountId} for Student #{$student->id}");
            $counter++;
            $generated++;
        }

        $this->command->info("✅ Generated {$generated} account_ids");

        // Step 2: Backfill student_payment_terms
        $this->backfillPaymentTerms();

        // Step 3: Backfill student_assessments
        $this->backfillAssessments();

        // Step 4: Backfill transactions
        $this->backfillTransactions();

        // Step 5: Backfill payments
        $this->backfillPayments();

        $this->command->info('✅ Backfill completed successfully!');
    }

    public function down(): void
    {
        // Cannot reverse data backfill
        $this->command->warn('⚠️  Backfill cannot be reversed');
    }

    protected function backfillPaymentTerms(): void
    {
        $count = DB::table('student_payment_terms')
            ->whereNull('account_id')
            ->count();

        if ($count === 0) {
            $this->command->info('✓ Payment terms already have account_id');
            return;
        }

        $this->command->info("📋 Backfilling {$count} payment terms...");

        DB::statement("
            UPDATE student_payment_terms spt
            INNER JOIN students s ON spt.user_id = s.user_id
            SET spt.account_id = s.account_id
            WHERE spt.account_id IS NULL
        ");

        $this->command->info("  ✓ Updated {$count} payment terms");
    }

    protected function backfillAssessments(): void
    {
        $count = DB::table('student_assessments')
            ->whereNull('account_id')
            ->count();

        if ($count === 0) {
            $this->command->info('✓ Assessments already have account_id');
            return;
        }

        $this->command->info("📋 Backfilling {$count} assessments...");

        DB::statement("
            UPDATE student_assessments sa
            INNER JOIN students s ON sa.user_id = s.user_id
            SET sa.account_id = s.account_id
            WHERE sa.account_id IS NULL
        ");

        $this->command->info("  ✓ Updated {$count} assessments");
    }

    protected function backfillTransactions(): void
    {
        $count = DB::table('transactions')
            ->whereNull('account_id')
            ->count();

        if ($count === 0) {
            $this->command->info('✓ Transactions already have account_id');
            return;
        }

        $this->command->info("📋 Backfilling {$count} transactions...");

        DB::statement("
            UPDATE transactions t
            INNER JOIN students s ON t.user_id = s.user_id
            SET t.account_id = s.account_id
            WHERE t.account_id IS NULL
        ");

        $this->command->info("  ✓ Updated {$count} transactions");
    }

    protected function backfillPayments(): void
    {
        $count = DB::table('payments')
            ->whereNull('account_id')
            ->count();

        if ($count === 0) {
            $this->command->info('✓ Payments already have account_id');
            return;
        }

        $this->command->info("📋 Backfilling {$count} payments...");

        DB::statement("
            UPDATE payments p
            INNER JOIN students s ON p.student_id = s.id
            SET p.account_id = s.account_id
            WHERE p.account_id IS NULL
        ");

        $this->command->info("  ✓ Updated {$count} payments");
    }

    protected function getNextAccountCounter(string $date): int
    {
        $prefix = "ACC-{$date}-";
        
        $lastStudent = DB::table('students')
            ->where('account_id', 'like', "{$prefix}%")
            ->orderByRaw('CAST(SUBSTRING(account_id, 14) AS UNSIGNED) DESC')
            ->first();

        if ($lastStudent) {
            return intval(substr($lastStudent->account_id, -4)) + 1;
        }

        return 1;
    }
};