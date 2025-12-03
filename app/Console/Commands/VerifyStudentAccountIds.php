<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

class VerifyStudentAccountIds extends Command
{
    protected $signature = 'students:verify-account-ids 
                            {--fix : Automatically fix missing or invalid account_ids}
                            {--show-all : Show all students, not just problems}';

    protected $description = 'Verify all students have valid account_id and optionally fix issues';

    public function handle(): int
    {
        $this->info('🔍 Verifying student account_ids...');
        $this->newLine();

        $issues = [
            'missing' => [],
            'invalid_format' => [],
            'duplicate' => [],
        ];

        // Check for missing account_ids
        $missing = Student::whereNull('account_id')->get();
        if ($missing->isNotEmpty()) {
            $this->warn("❌ Found {$missing->count()} students WITHOUT account_id");
            $issues['missing'] = $missing;
        }

        // Check for invalid formats
        $allStudents = Student::whereNotNull('account_id')->get();
        foreach ($allStudents as $student) {
            if (!Student::isValidAccountId($student->account_id)) {
                $this->warn("❌ Invalid format: {$student->account_id} (Student #{$student->id})");
                $issues['invalid_format'][] = $student;
            }
        }

        // Check for duplicates
        $duplicates = DB::table('students')
            ->select('account_id', DB::raw('COUNT(*) as count'))
            ->whereNotNull('account_id')
            ->groupBy('account_id')
            ->having('count', '>', 1)
            ->get();

        if ($duplicates->isNotEmpty()) {
            $this->error("❌ Found {$duplicates->count()} DUPLICATE account_ids!");
            $issues['duplicate'] = $duplicates;
        }

        // Show summary
        $totalIssues = count($issues['missing']) + count($issues['invalid_format']) + count($issues['duplicate']);

        if ($totalIssues === 0) {
            $this->info("✅ All {$allStudents->count()} students have valid, unique account_ids");
            return self::SUCCESS;
        }

        $this->newLine();
        $this->warn("⚠️  Found {$totalIssues} issues");

        // Fix if requested
        if ($this->option('fix')) {
            $this->newLine();
            $this->info('🔧 Fixing issues...');
            
            if (!empty($issues['missing'])) {
                $this->fixMissingAccountIds($issues['missing']);
            }
            
            if (!empty($issues['invalid_format'])) {
                $this->fixInvalidFormats($issues['invalid_format']);
            }
            
            if (!empty($issues['duplicate'])) {
                $this->fixDuplicates($issues['duplicate']);
            }

            $this->info('✅ All issues fixed!');
        } else {
            $this->newLine();
            $this->warn('Run with --fix to automatically repair issues');
        }

        return $totalIssues > 0 ? self::FAILURE : self::SUCCESS;
    }

    protected function fixMissingAccountIds($students): void
    {
        foreach ($students as $student) {
            $accountId = Student::generateAccountId();
            $student->update(['account_id' => $accountId]);
            $this->info("  ✓ Generated {$accountId} for Student #{$student->id}");
        }
    }

    protected function fixInvalidFormats($students): void
    {
        foreach ($students as $student) {
            $oldId = $student->account_id;
            $newId = Student::generateAccountId();
            $student->update(['account_id' => $newId]);
            $this->info("  ✓ Replaced invalid {$oldId} → {$newId}");
        }
    }

    protected function fixDuplicates($duplicates): void
    {
        foreach ($duplicates as $dup) {
            $students = Student::where('account_id', $dup->account_id)->get();
            $students->skip(1)->each(function ($student) {
                $oldId = $student->account_id;
                $newId = Student::generateAccountId();
                $student->update(['account_id' => $newId]);
                $this->info("  ✓ Fixed duplicate {$oldId} → {$newId}");
            });
        }
    }
}