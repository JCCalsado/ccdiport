<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Student;
use App\Models\StudentAssessment;
use Illuminate\Support\Facades\DB;

class StudentAssessmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('📋 Creating student assessments...');

        // Get all students from the students table
        $students = Student::all();

        if ($students->isEmpty()) {
            $this->command->warn('⚠️  No students found. Please run StudentsSeeder first.');
            return;
        }

        $schoolYear = '2025-2026';
        $semester = '1st Sem';
        $assessmentsCreated = 0;

        foreach ($students as $student) {
            // Check if assessment already exists for this student
            $existingAssessment = StudentAssessment::where('account_id', $student->account_id)
                ->where('school_year', $schoolYear)
                ->where('semester', $semester)
                ->first();

            if ($existingAssessment) {
                $this->command->warn("⚠️  Assessment already exists for {$student->first_name} {$student->last_name}");
                continue;
            }

            // Define fee breakdown (4 items only)
            $registrationFee = 0.00;
            $tuitionFee = 5000.00;
            $labFee = 2000.00;
            $miscFee = 1048.00;
            $totalAssessment = $registrationFee + $tuitionFee + $labFee + $miscFee;

            // Create assessment
            StudentAssessment::create([
                'user_id' => $student->user_id,
                'account_id' => $student->account_id,
                'assessment_number' => $this->generateAssessmentNumber(),
                'year_level' => $student->year_level,
                'semester' => $semester,
                'school_year' => $schoolYear,
                'tuition_fee' => $tuitionFee,
                'other_fees' => $registrationFee + $labFee + $miscFee,
                'total_assessment' => $totalAssessment, // ← Fixed column name
                'subjects' => json_encode([]), // Empty array for now
                'fee_breakdown' => json_encode([
                    ['name' => 'Registration Fee', 'amount' => $registrationFee],
                    ['name' => 'Tuition Fee', 'amount' => $tuitionFee],
                    ['name' => 'Lab Fee', 'amount' => $labFee],
                    ['name' => 'Misc Fee', 'amount' => $miscFee],
                ]),
                'status' => 'active',
                'created_by' => 1,
            ]);

            $assessmentsCreated++;
            $this->command->info("✓ Created assessment for {$student->first_name} {$student->last_name} ({$student->account_id}) - ₱{$totalAssessment}");
        }

        $this->command->info("✅ Created {$assessmentsCreated} student assessments!");
    }

    /**
     * Generate unique assessment number
     */
    private function generateAssessmentNumber(): string
    {
        $year = date('Y');
        $lastAssessment = StudentAssessment::whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastAssessment ? ((int) substr($lastAssessment->assessment_number, -4)) + 1 : 1;

        return 'ASSESS-' . $year . '-' . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }
}