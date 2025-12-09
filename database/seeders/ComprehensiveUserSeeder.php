<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Student;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class ComprehensiveUserSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🚀 Starting comprehensive user seeding...');

        // ✅ STEP 1: Clean existing demo students (safe cleanup)
        $this->cleanupDemoStudents();

        // ✅ STEP 2: Seed admin and accounting staff
        $this->seedAdminAndAccounting();

        // ✅ STEP 3: Seed 100 diverse students
        $this->seedStudents();

        $this->command->info('✅ Comprehensive user seeding completed!');
    }

    /**
     * ✅ SAFE CLEANUP: Remove only demo students, preserve real data
     */
    protected function cleanupDemoStudents(): void
    {
        $this->command->info('🧹 Cleaning up existing demo students...');

        // Delete only students with demo email pattern
        $demoEmails = User::where('email', 'like', 'student%@ccdi.edu.ph')
            ->where('role', 'student')
            ->pluck('id');

        if ($demoEmails->isNotEmpty()) {
            // Delete related student records first
            Student::whereIn('user_id', $demoEmails)->delete();
            
            // Delete user accounts
            User::whereIn('id', $demoEmails)->delete();
            
            $this->command->info("   ✓ Removed {$demoEmails->count()} demo students");
        } else {
            $this->command->info('   ✓ No demo students to clean');
        }
    }

    /**
     * ✅ SEED ADMIN & ACCOUNTING (Idempotent)
     */
    protected function seedAdminAndAccounting(): void
    {
        $this->command->info('👤 Seeding admin and accounting staff...');

        // Admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@ccdi.edu.ph'],
            [
                'last_name' => 'Rodriguez',
                'first_name' => 'Carlos',
                'middle_initial' => 'M',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'status' => User::STATUS_ACTIVE,
                'faculty' => 'Administration',
                'phone' => '09171234501',
                'address' => 'Sorsogon City',
                'birthday' => '1985-05-15',
            ]
        );
        $admin->account()->firstOrCreate([], ['balance' => 0]);
        $this->command->info('   ✓ Admin created');

        // Accounting
        $accounting = User::firstOrCreate(
            ['email' => 'accounting@ccdi.edu.ph'],
            [
                'last_name' => 'Garcia',
                'first_name' => 'Ana Marie',
                'middle_initial' => 'S',
                'password' => Hash::make('password'),
                'role' => 'accounting',
                'status' => User::STATUS_ACTIVE,
                'faculty' => 'Accounting Department',
                'phone' => '09181234502',
                'address' => 'Legazpi City',
                'birthday' => '1990-08-20',
            ]
        );
        $accounting->account()->firstOrCreate([], ['balance' => 0]);
        $this->command->info('   ✓ Accounting staff created');
    }

    /**
     * ✅ SEED 100 DIVERSE STUDENTS (Idempotent)
     */
    protected function seedStudents(): void
    {
        $this->command->info('🎓 Seeding 100 diverse students...');

        // Filipino name pools
        $lastNames = [
            'Dela Cruz', 'Santos', 'Reyes', 'Garcia', 'Ramos',
            'Mendoza', 'Torres', 'Flores', 'Gonzales', 'Castro',
            'Rivera', 'Bautista', 'Santiago', 'Fernandez', 'Lopez',
            'Morales', 'Aquino', 'Villanueva', 'Cruz', 'Jimenez',
            'Martinez', 'Rodriguez', 'Hernandez', 'Perez', 'Gomez'
        ];

        $firstNames = [
            'Juan', 'Jose', 'Pedro', 'Miguel', 'Carlos',
            'Antonio', 'Manuel', 'Francisco', 'Rafael', 'Eduardo',
            'Ricardo', 'Fernando', 'Roberto', 'Andres', 'Javier',
            'Maria', 'Ana', 'Carmen', 'Rosa', 'Teresa',
            'Elena', 'Isabel', 'Lucia', 'Sofia', 'Patricia',
            'Angela', 'Monica', 'Gloria', 'Diana', 'Cristina'
        ];

        $middleInitials = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'J', 'K', 'L', 'M', 'N', 'P', 'R', 'S', 'T', 'V'];

        $addresses = [
            'Sorsogon City', 'Legazpi City', 'Naga City', 'Daet', 'Iriga City',
            'Tabaco City', 'Ligao City', 'Polangui', 'Daraga', 'Camalig'
        ];

        $courses = [
            'BS Electrical Engineering Technology',
            'BS Electronics Engineering Technology'
        ];

        $yearLevels = ['1st Year', '2nd Year', '3rd Year', '4th Year'];

        // ✅ Student distribution template
        $studentTemplates = array_merge(
            array_fill(0, 40, ['year_level' => '1st Year', 'status' => 'active', 'balance' => rand(5000, 15000)]),
            array_fill(0, 30, ['year_level' => '2nd Year', 'status' => 'active', 'balance' => rand(3000, 12000)]),
            array_fill(0, 10, ['year_level' => '2nd Year', 'status' => 'inactive', 'balance' => rand(5000, 20000)]),
            array_fill(0, 10, ['year_level' => '4th Year', 'status' => 'active', 'balance' => rand(1000, 5000)]),
            array_fill(0, 10, ['year_level' => '4th Year', 'status' => 'graduated', 'balance' => 0])
        );

        shuffle($studentTemplates);

        $statusMap = [
            'active' => User::STATUS_ACTIVE,
            'graduated' => User::STATUS_GRADUATED,
            'inactive' => User::STATUS_DROPPED,
        ];

        $studentStatusMap = [
            'active' => 'enrolled',
            'graduated' => 'graduated',
            'inactive' => 'inactive',
        ];

        $createdCount = 0;

        foreach ($studentTemplates as $index => $template) {
            $studentNumber = $index + 1;
            $email = "student{$studentNumber}@ccdi.edu.ph";

            // ✅ Skip if already exists
            if (User::where('email', $email)->exists()) {
                $this->command->warn("   ⚠️  Skipping {$email} (already exists)");
                continue;
            }

            $lastName = $lastNames[array_rand($lastNames)];
            $firstName = $firstNames[array_rand($firstNames)];
            $middleInitial = $middleInitials[array_rand($middleInitials)];
            $course = $courses[array_rand($courses)];
            $address = $addresses[array_rand($addresses)];

            // ✅ Generate UNIQUE student_id with transaction lock
            $studentId = $this->generateUniqueStudentId();

            $yearLevelNum = (int) substr($template['year_level'], 0, 1);
            $birthYear = 2025 - 18 - $yearLevelNum + 1;
            $birthday = $birthYear . '-' . str_pad(rand(1, 12), 2, '0', STR_PAD_LEFT) . '-' . str_pad(rand(1, 28), 2, '0', STR_PAD_LEFT);

            // ✅ Create User
            $user = User::create([
                'last_name' => $lastName,
                'first_name' => $firstName,
                'middle_initial' => $middleInitial,
                'email' => $email,
                'password' => Hash::make('password'),
                'role' => 'student',
                'student_id' => $studentId,
                'status' => $statusMap[$template['status']],
                'course' => $course,
                'year_level' => $template['year_level'],
                'birthday' => $birthday,
                'phone' => '0917' . rand(1000000, 9999999),
                'address' => $address,
            ]);

            // ✅ Create Account
            $user->account()->create(['balance' => -$template['balance']]);

            // ✅ Create Student Profile (account_id auto-generates)
            $student = Student::create([
                'user_id' => $user->id,
                // account_id auto-generates via model boot
                'student_id' => $studentId,
                'last_name' => $lastName,
                'first_name' => $firstName,
                'middle_initial' => $middleInitial,
                'email' => $email,
                'course' => $course,
                'year_level' => $template['year_level'],
                'status' => $studentStatusMap[$template['status']],
                'birthday' => $birthday,
                'phone' => $user->phone,
                'address' => $address,
                'total_balance' => $template['balance'],
            ]);

            $createdCount++;

            if ($createdCount % 10 === 0) {
                $this->command->info("   ✓ Created {$createdCount}/100 students...");
            }
        }

        $this->command->info("✅ Successfully seeded {$createdCount} students!");
        $this->command->info('  - 70 Active (40 1st year, 30 2nd year)');
        $this->command->info('  - 10 Dropped (2nd year)');
        $this->command->info('  - 20 Graduated (4th year)');
    }

    /**
     * ✅ Generate UNIQUE student_id with DB transaction lock
     */
    protected function generateUniqueStudentId(): string
    {
        return DB::transaction(function () {
            $year = now()->year;

            // ✅ Lock the row to prevent race conditions
            $lastStudent = User::where('student_id', 'like', "{$year}-%")
                ->lockForUpdate()
                ->orderByRaw('CAST(SUBSTRING(student_id, 6) AS UNSIGNED) DESC')
                ->first();

            if ($lastStudent) {
                $lastNumber = intval(substr($lastStudent->student_id, 5));
                $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
            } else {
                $newNumber = '0001';
            }

            $newStudentId = "{$year}-{$newNumber}";

            // ✅ Ensure uniqueness (safety check)
            $attempts = 0;
            while (User::where('student_id', $newStudentId)->exists() && $attempts < 100) {
                $lastNumber++;
                $newNumber = str_pad($lastNumber, 4, '0', STR_PAD_LEFT);
                $newStudentId = "{$year}-{$newNumber}";
                $attempts++;
            }

            if ($attempts >= 100) {
                throw new \Exception('Unable to generate unique student_id after 100 attempts');
            }

            return $newStudentId;
        });
    }
}