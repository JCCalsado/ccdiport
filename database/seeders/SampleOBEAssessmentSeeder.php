<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Program;
use App\Models\Curriculum;
use App\Services\CurriculumService;
use Illuminate\Support\Facades\Hash;

class SampleOBEAssessmentSeeder extends Seeder
{
    protected $curriculumService;

    public function __construct()
    {
        $this->curriculumService = new CurriculumService();
    }

    public function run(): void
    {
        $this->command->info('Creating sample OBE assessments...');

        // Get programs
        $electricalProgram = Program::where('code', 'BSET-EET')->first();
        $electronicsProgram = Program::where('code', 'BSET-ECET')->first();

        if (!$electricalProgram || !$electronicsProgram) {
            $this->command->warn('⚠️  Programs not found. Run OBECurriculumSeeder first.');
            return;
        }

        // Get curricula
        $electricalCurriculum = Curriculum::where('program_id', $electricalProgram->id)
            ->where('year_level', '1st Year')
            ->where('semester', '1st Sem')
            ->where('school_year', '2025-2026')
            ->first();

        $electronicsCurriculum = Curriculum::where('program_id', $electronicsProgram->id)
            ->where('year_level', '1st Year')
            ->where('semester', '1st Sem')
            ->where('school_year', '2025-2026')
            ->first();

        if (!$electricalCurriculum || !$electronicsCurriculum) {
            $this->command->warn('⚠️  Curricula not found. Run OBECurriculumSeeder first.');
            return;
        }

        // Create 10 sample students with OBE assessments
        $sampleStudents = [
            [
                'last_name' => 'Torres',
                'first_name' => 'Miguel',
                'middle_initial' => 'A',
                'email' => 'miguel.torres@ccdi.edu.ph',
                'program' => $electricalProgram,
                'curriculum' => $electricalCurriculum,
            ],
            [
                'last_name' => 'Reyes',
                'first_name' => 'Isabella',
                'middle_initial' => 'B',
                'email' => 'isabella.reyes@ccdi.edu.ph',
                'program' => $electronicsProgram,
                'curriculum' => $electronicsCurriculum,
            ],
            [
                'last_name' => 'Fernandez',
                'first_name' => 'Lucas',
                'middle_initial' => 'C',
                'email' => 'lucas.fernandez@ccdi.edu.ph',
                'program' => $electricalProgram,
                'curriculum' => $electricalCurriculum,
            ],
            [
                'last_name' => 'Martinez',
                'first_name' => 'Sofia',
                'middle_initial' => 'D',
                'email' => 'sofia.martinez@ccdi.edu.ph',
                'program' => $electronicsProgram,
                'curriculum' => $electronicsCurriculum,
            ],
            [
                'last_name' => 'Gonzalez',
                'first_name' => 'Gabriel',
                'middle_initial' => 'E',
                'email' => 'gabriel.gonzalez@ccdi.edu.ph',
                'program' => $electricalProgram,
                'curriculum' => $electricalCurriculum,
            ],
        ];

        $created = 0;
        foreach ($sampleStudents as $studentData) {
            // Create user
            $user = User::create([
                'last_name' => $studentData['last_name'],
                'first_name' => $studentData['first_name'],
                'middle_initial' => $studentData['middle_initial'],
                'email' => $studentData['email'],
                'password' => Hash::make('password'),
                'role' => 'student',
                'student_id' => $this->generateStudentId(),
                'status' => User::STATUS_ACTIVE,
                'course' => $studentData['program']->full_name,
                'year_level' => '1st Year',
                'birthday' => '2005-' . str_pad(rand(1, 12), 2, '0', STR_PAD_LEFT) . '-' . str_pad(rand(1, 28), 2, '0', STR_PAD_LEFT),
                'phone' => '0917' . rand(1000000, 9999999),
                'address' => 'Sorsogon City',
            ]);

            // Create student profile
            \App\Models\Student::create([
                'user_id' => $user->id,
                'student_id' => $user->student_id,
                'last_name' => $user->last_name,
                'first_name' => $user->first_name,
                'middle_initial' => $user->middle_initial,
                'email' => $user->email,
                'course' => $user->course,
                'year_level' => $user->year_level,
                'status' => 'enrolled',
                'birthday' => $user->birthday,
                'phone' => $user->phone,
                'address' => $user->address,
                'total_balance' => 0,
            ]);

            // Create account
            $user->account()->create(['balance' => 0]);

            // Generate OBE assessment
            try {
                $assessment = $this->curriculumService->generateAssessment($user, $studentData['curriculum']);
                $created++;
                $this->command->info("✓ Created OBE assessment for {$user->name} ({$studentData['program']->name})");
            } catch (\Exception $e) {
                $this->command->error("✗ Failed to create assessment for {$user->name}: {$e->getMessage()}");
            }
        }

        $this->command->info("✅ Created {$created} OBE assessments successfully!");
    }

    private function generateStudentId(): string
    {
        $year = now()->year;
        $lastStudent = User::where('student_id', 'like', "{$year}-%")
            ->orderByRaw('CAST(SUBSTRING(student_id, 6) AS UNSIGNED) DESC')
            ->first();

        if ($lastStudent) {
            $lastNumber = intval(substr($lastStudent->student_id, -4));
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }

        return "{$year}-{$newNumber}";
    }
}