<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Transaction;
use App\Models\Payment;
use App\Models\StudentAssessment;
use App\Models\Account;
use App\Models\Student;

class CleanFakeFinancialData extends Command
{
    protected $signature = 'finance:clean 
                            {--transactions : Delete all transactions}
                            {--payments : Delete all payments}
                            {--assessments : Delete all assessments}
                            {--balances : Reset all balances to zero}
                            {--all : Delete everything (DANGEROUS)}
                            {--dry-run : Show what would be deleted without deleting}';

    protected $description = 'Remove fake financial data from the system';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $all = $this->option('all');

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - Nothing will be deleted');
            $this->newLine();
        }

        // Confirm if not dry-run and deleting everything
        if (!$dryRun && $all) {
            if (!$this->confirm('⚠️  This will DELETE ALL financial data. Are you ABSOLUTELY sure?', false)) {
                $this->error('Operation cancelled.');
                return self::FAILURE;
            }
        }

        DB::beginTransaction();
        try {
            $stats = [
                'transactions' => 0,
                'payments' => 0,
                'assessments' => 0,
                'accounts_reset' => 0,
                'students_reset' => 0,
            ];

            // Delete transactions
            if ($all || $this->option('transactions')) {
                $count = Transaction::count();
                if (!$dryRun) {
                    Transaction::truncate();
                }
                $stats['transactions'] = $count;
                $this->info("✓ Transactions: {$count} " . ($dryRun ? 'would be deleted' : 'deleted'));
            }

            // Delete payments
            if ($all || $this->option('payments')) {
                $count = Payment::count();
                if (!$dryRun) {
                    Payment::truncate();
                }
                $stats['payments'] = $count;
                $this->info("✓ Payments: {$count} " . ($dryRun ? 'would be deleted' : 'deleted'));
            }

            // Delete assessments
            if ($all || $this->option('assessments')) {
                $count = StudentAssessment::count();
                if (!$dryRun) {
                    StudentAssessment::truncate();
                    DB::table('student_curricula')->truncate();
                    DB::table('student_enrollments')->truncate();
                }
                $stats['assessments'] = $count;
                $this->info("✓ Assessments: {$count} " . ($dryRun ? 'would be deleted' : 'deleted'));
            }

            // Reset balances
            if ($all || $this->option('balances')) {
                $accountCount = Account::where('balance', '!=', 0)->count();
                $studentCount = Student::where('total_balance', '!=', 0)->count();
                
                if (!$dryRun) {
                    Account::query()->update(['balance' => 0]);
                    Student::query()->update(['total_balance' => 0]);
                }
                
                $stats['accounts_reset'] = $accountCount;
                $stats['students_reset'] = $studentCount;
                
                $this->info("✓ Account balances: {$accountCount} " . ($dryRun ? 'would be reset' : 'reset'));
                $this->info("✓ Student balances: {$studentCount} " . ($dryRun ? 'would be reset' : 'reset'));
            }

            if (!$dryRun) {
                DB::commit();
            } else {
                DB::rollBack();
            }

            $this->newLine();
            $this->displaySummary($stats, $dryRun);

            return self::SUCCESS;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('Error: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function displaySummary(array $stats, bool $dryRun): void
    {
        $this->info('═══════════════════════════════════════');
        $this->info($dryRun ? '🔍 DRY RUN SUMMARY' : '✅ CLEANUP COMPLETE!');
        $this->info('═══════════════════════════════════════');
        
        $this->table(
            ['Item', 'Count'],
            [
                ['Transactions ' . ($dryRun ? '(would delete)' : '(deleted)'), $stats['transactions']],
                ['Payments ' . ($dryRun ? '(would delete)' : '(deleted)'), $stats['payments']],
                ['Assessments ' . ($dryRun ? '(would delete)' : '(deleted)'), $stats['assessments']],
                ['Accounts ' . ($dryRun ? '(would reset)' : '(reset)'), $stats['accounts_reset']],
                ['Students ' . ($dryRun ? '(would reset)' : '(reset)'), $stats['students_reset']],
            ]
        );

        if (!$dryRun) {
            $this->newLine();
            $this->info('Next steps:');
            $this->info('• Create new assessments: /student-fees/create');
            $this->info('• Assign fees manually through admin panel');
        } else {
            $this->newLine();
            $this->warn('To actually delete data, run without --dry-run flag');
        }
    }
}