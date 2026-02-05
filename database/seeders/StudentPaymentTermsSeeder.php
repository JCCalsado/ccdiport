<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
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

        // Get all students with their assessments
        $students = Student::with('assessments')->get();

        if ($students->isEmpty()) {
            $this->command->warn('⚠️  No students found. Please run StudentsSeeder first.');
            return;
        }

        $termsCreated = 0;

        foreach ($students as $student) {
            // Get the student's latest assessment
            $assessment = $student->assessments()->latest()->first();

            if (!$assessment) {
                $this->command->warn("⚠️  No assessment found for student: {$student->first_name} {$student->last_name} ({$student->account_id})");
                continue;
            }

            // Delete existing payment terms for this assessment
            StudentPaymentTerm::where('account_id', $student->account_id)
                ->where('assessment_id', $assessment->id)
                ->delete();

            // Create 5 payment terms (Prelim, Midterm, Semi-Final, Final, Clearance)
            $terms = [
                ['name' => 'Prelim', 'percentage' => 0.20],
                ['name' => 'Midterm', 'percentage' => 0.20],
                ['name' => 'Semi-Final', 'percentage' => 0.20],
                ['name' => 'Final', 'percentage' => 0.20],
                ['name' => 'Clearance', 'percentage' => 0.20],
            ];

            $totalAmount = $assessment->total_assessment; // ← Fixed column name
            $currentDate = Carbon::now();

            foreach ($terms as $index => $term) {
                $amount = round($totalAmount * $term['percentage'], 2);
                $dueDate = $currentDate->copy()->addMonths($index + 1);

                // Randomly assign status (70% pending, 20% paid, 10% overdue)
                $random = rand(1, 100);
                if ($random <= 70) {
                    $status = 'pending';
                    $paidAmount = 0;
                    $paymentDate = null;
                } elseif ($random <= 90) {
                    $status = 'paid';
                    $paidAmount = $amount;
                    $paymentDate = $dueDate->copy()->subDays(rand(1, 7));
                } else {
                    $status = 'overdue';
                    $paidAmount = 0;
                    $paymentDate = null;
                    $dueDate = $currentDate->copy()->subDays(rand(1, 30));
                }

                StudentPaymentTerm::create([
                    'account_id' => $student->account_id,
                    'assessment_id' => $assessment->id,
                    'term_name' => $term['name'],
                    'due_date' => $dueDate,
                    'amount' => $amount,
                    'status' => $status,
                    'paid_amount' => $paidAmount,
                    'balance' => $amount - $paidAmount,
                    'payment_date' => $paymentDate,
                    'reference_number' => $status === 'paid' ? 'REF-' . strtoupper(uniqid()) : null,
                    'remarks' => null,
                ]);

                $termsCreated++;
            }

            $this->command->info("✓ Created payment terms for {$student->first_name} {$student->last_name} ({$student->account_id})");
        }

        $this->command->info("✅ Created {$termsCreated} payment terms for {$students->count()} students!");
    }
}