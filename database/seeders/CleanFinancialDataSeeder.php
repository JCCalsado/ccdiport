<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Transaction;
use App\Models\Payment;
use App\Models\StudentAssessment;
use App\Models\Account;
use App\Models\Student;
use App\Models\User;

class CleanFinancialDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🧹 Starting financial data cleanup...');
        $this->command->newLine();

        // ✅ REMOVED: DB::beginTransaction() - Let Laravel handle it automatically

        try {
            // Step 1: Delete all transactions
            $this->command->info('📋 Step 1: Deleting all transactions...');
            $transactionCount = Transaction::count();
            Transaction::query()->delete();
            $this->command->info("   ✓ Deleted {$transactionCount} transactions");
            $this->command->newLine();

            // Step 2: Delete all payments
            $this->command->info('💳 Step 2: Deleting all payments...');
            $paymentCount = Payment::count();
            Payment::query()->delete();
            $this->command->info("   ✓ Deleted {$paymentCount} payments");
            $this->command->newLine();

            // Step 3: Delete all student assessments
            $this->command->info('📊 Step 3: Deleting all student assessments...');
            $assessmentCount = StudentAssessment::count();
            StudentAssessment::query()->delete();
            $this->command->info("   ✓ Deleted {$assessmentCount} assessments");
            $this->command->newLine();

            // Step 4: Reset all account balances
            $this->command->info('💰 Step 4: Resetting all account balances to ₱0.00...');
            $accountCount = Account::where('balance', '!=', 0)->count();
            Account::query()->update(['balance' => 0]);
            $this->command->info("   ✓ Reset {$accountCount} account balances");
            $this->command->newLine();

            // Step 5: Reset all student balances
            $this->command->info('🎓 Step 5: Resetting all student balances to ₱0.00...');
            $studentCount = Student::where('total_balance', '!=', 0)->count();
            Student::query()->update(['total_balance' => 0]);
            $this->command->info("   ✓ Reset {$studentCount} student balances");
            $this->command->newLine();

            // Step 6: Ensure all students have accounts
            $this->command->info('🔧 Step 6: Ensuring all students have account records...');
            $students = User::where('role', 'student')->doesntHave('account')->get();
            $createdAccounts = 0;
            
            foreach ($students as $student) {
                $student->account()->create(['balance' => 0]);
                $createdAccounts++;
            }
            
            if ($createdAccounts > 0) {
                $this->command->info("   ✓ Created {$createdAccounts} missing account records");
            } else {
                $this->command->info('   ✓ All students already have account records');
            }
            $this->command->newLine();

            // ✅ REMOVED: DB::commit() - Not needed

            // Verification
            $this->command->info('✅ Step 7: Verifying cleanup...');
            $this->verifyCleanup();
            
        } catch (\Exception $e) {
            // ✅ REMOVED: DB::rollBack() - Not needed
            $this->command->error('❌ Cleanup failed: ' . $e->getMessage());
            $this->command->error('   Stack trace: ' . $e->getTraceAsString());
            throw $e;
        }

        $this->command->newLine();
        $this->command->info('✅ Financial data cleanup completed successfully!');
        $this->command->info('   All student accounts are now at ₱0.00 balance.');
        $this->command->info('   Ready for fresh assessment generation.');
    }

    /**
     * Verify that all data has been cleaned up
     */
    protected function verifyCleanup(): void
    {
        $transactionCount = Transaction::count();
        $paymentCount = Payment::count();
        $assessmentCount = StudentAssessment::count();
        $nonZeroAccounts = Account::where('balance', '!=', 0)->count();
        $nonZeroStudents = Student::where('total_balance', '!=', 0)->count();

        $this->command->info("   ✓ Transactions: {$transactionCount}");
        $this->command->info("   ✓ Payments: {$paymentCount}");
        $this->command->info("   ✓ Assessments: {$assessmentCount}");
        $this->command->info("   ✓ Accounts with non-zero balance: {$nonZeroAccounts}");
        $this->command->info("   ✓ Students with non-zero balance: {$nonZeroStudents}");

        if ($transactionCount > 0 || $paymentCount > 0 || $assessmentCount > 0 || $nonZeroAccounts > 0 || $nonZeroStudents > 0) {
            $this->command->warn('   ⚠️  Warning: Some data was not fully cleaned up!');
        } else {
            $this->command->info('   ✓ All financial data successfully cleaned!');
        }
        
        $this->command->newLine();
    }
}