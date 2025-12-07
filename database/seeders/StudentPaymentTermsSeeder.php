<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Student;
use App\Models\StudentPaymentTerm;
use App\Models\StudentAssessment;
use Carbon\Carbon;

class StudentPaymentTermsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('📋 Generating payment terms for students...');

        // ✅ Get students who don't have payment terms yet (through student relationship)
        $students = Student::whereNotNull('account_id')
            ->whereDoesntHave('paymentTerms')
            ->with('user')
            ->get();

        if ($students->isEmpty()) {
            $this->command->warn('⚠️  No students found without payment terms.');
            return;
        }

        $created = 0;
        $schoolYear = '2025-2026';
        $semester = '1st Sem';
        $startDate = Carbon::parse('2025-08-01');

        foreach ($students as $student) {
            if (!$student->user) {
                $this->command->warn("⚠️  Student {$student->account_id} has no user record, skipping...");
                continue;
            }

            // Get latest assessment or use default amounts
            $assessment = StudentAssessment::where('account_id', $student->account_id)
                ->where('status', 'active')
                ->latest()
                ->first();

            $totalAmount = $assessment ? $assessment->total_assessment : 8000.00;
            
            // Divide into 5 terms
            $termAmount = round($totalAmount / 5, 2);
            $lastTermAmount = $totalAmount - ($termAmount * 4); // Adjust last term for rounding

            $terms = [
                ['name' => 'Upon Registration', 'order' => 1, 'weeks' => 0, 'amount' => $termAmount],
                ['name' => 'Prelim', 'order' => 2, 'weeks' => 6, 'amount' => $termAmount],
                ['name' => 'Midterm', 'order' => 3, 'weeks' => 12, 'amount' => $termAmount],
                ['name' => 'Semi-Final', 'order' => 4, 'weeks' => 15, 'amount' => $termAmount],
                ['name' => 'Final', 'order' => 5, 'weeks' => 18, 'amount' => $lastTermAmount],
            ];

            foreach ($terms as $term) {
                StudentPaymentTerm::create([
                    'account_id' => $student->account_id, // ✅ PRIMARY
                    'user_id' => $student->user_id,       // Compatibility
                    'curriculum_id' => $assessment->curriculum_id ?? null,
                    'school_year' => $schoolYear,
                    'semester' => $semester,
                    'term_name' => $term['name'],
                    'term_order' => $term['order'],
                    'amount' => $term['amount'],
                    'due_date' => $startDate->copy()->addWeeks($term['weeks']),
                    'status' => 'pending',
                    'paid_amount' => 0,
                ]);
                $created++;
            }

            $this->command->info("✓ Created payment terms for {$student->full_name}");
        }

        $this->command->info("✅ Created {$created} payment terms for {$students->count()} students!");
    }
}