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

            $totalAmount = $assessment->total_assessment;

            // Calculate payment breakdown based on percentages
            $uponRegistration = round($totalAmount * 0.4215, 2); // 42.15%
            $prelim = round($totalAmount * 0.1786, 2);           // 17.86%
            $midterm = round($totalAmount * 0.1786, 2);          // 17.86%
            $semiFinal = round($totalAmount * 0.1488, 2);        // 14.88%
            
            // Final gets the remaining amount to ensure exact total
            $final = $totalAmount - ($uponRegistration + $prelim + $midterm + $semiFinal);

            // Create payment terms with new structure
            $terms = [
                [
                    'name' => 'Upon Registration',
                    'amount' => $uponRegistration,
                    'months_offset' => 0, // Due immediately
                ],
                [
                    'name' => 'Prelim',
                    'amount' => $prelim,
                    'months_offset' => 1,
                ],
                [
                    'name' => 'Midterm',
                    'amount' => $midterm,
                    'months_offset' => 2,
                ],
                [
                    'name' => 'Semi-Final',
                    'amount' => $semiFinal,
                    'months_offset' => 3,
                ],
                [
                    'name' => 'Final',
                    'amount' => $final,
                    'months_offset' => 4,
                ],
            ];

            $currentDate = Carbon::now();

            foreach ($terms as $term) {
                $dueDate = $currentDate->copy()->addMonths($term['months_offset']);

                // Randomly assign status (70% pending, 20% paid, 10% overdue)
                $random = rand(1, 100);
                if ($random <= 70) {
                    $status = 'pending';
                    $paidAmount = 0;
                    $paymentDate = null;
                } elseif ($random <= 90) {
                    $status = 'paid';
                    $paidAmount = $term['amount'];
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
                    'amount' => $term['amount'],
                    'status' => $status,
                    'paid_amount' => $paidAmount,
                    'balance' => $term['amount'] - $paidAmount,
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