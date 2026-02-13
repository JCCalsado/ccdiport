<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Account;
use App\Models\Student;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ComprehensiveUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        echo "🚀 Starting comprehensive user seeding...\n";
        
        // Clean up existing demo data
        echo "🧹 Cleaning up existing demo students...\n";
        Student::where('student_number', 'like', 'DEMO%')->delete();
        Student::where('email', 'like', 'student%@ccdi.edu.ph')->delete();
        User::where('email', 'like', '%@demo.test')->delete();
        User::where('email', 'like', 'student%@ccdi.edu.ph')->delete();
        echo "   ✓ Cleanup completed\n";
        
        // Seed admin and accounting staff
        echo "👤 Seeding admin and accounting staff...\n";
        
        // Create Admin User
        $admin = User::firstOrCreate(
            ['email' => 'admin@ccdi.edu.ph'],
            [
                'first_name' => 'Admin',
                'last_name' => 'User',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'status' => 'active',
            ]
        );
        
        // Create Account for Admin
        if (!$admin->account) {
            Account::create([
                'user_id' => $admin->id,
                'account_number' => 'ACC-ADMIN-' . str_pad($admin->id, 6, '0', STR_PAD_LEFT),
                'balance' => 0,
                'status' => 'active',
            ]);
        }
        
        echo "   ✓ Admin user created: {$admin->email}\n";
        
        // Create Accounting Staff
        $accounting = User::firstOrCreate(
            ['email' => 'accounting@ccdi.edu.ph'],
            [
                'first_name' => 'Accounting',
                'last_name' => 'Staff',
                'password' => Hash::make('password'),
                'role' => 'accounting',
                'status' => 'active',
            ]
        );
        
        // Create Account for Accounting Staff
        if (!$accounting->account) {
            Account::create([
                'user_id' => $accounting->id,
                'account_number' => 'ACC-ACCT-' . str_pad($accounting->id, 6, '0', STR_PAD_LEFT),
                'balance' => 0,
                'status' => 'active',
            ]);
        }
        
        echo "   ✓ Accounting staff created: {$accounting->email}\n";
        
        // Seed 100 student users
        echo "\n👨‍🎓 Seeding 100 student users...\n";
        
        // Filipino names
        $firstNames = [
            'Juan', 'Jose', 'Pedro', 'Miguel', 'Carlos',
            'Maria', 'Ana', 'Carmen', 'Rosa', 'Teresa',
            'Antonio', 'Manuel', 'Francisco', 'Rafael', 'Eduardo',
            'Elena', 'Isabel', 'Lucia', 'Sofia', 'Patricia',
            'Ricardo', 'Fernando', 'Roberto', 'Andres', 'Javier',
            'Angela', 'Monica', 'Gloria', 'Diana', 'Cristina'
        ];
        
        $lastNames = [
            'Dela Cruz', 'Santos', 'Reyes', 'Garcia', 'Ramos',
            'Mendoza', 'Torres', 'Flores', 'Gonzales', 'Castro',
            'Rivera', 'Bautista', 'Santiago', 'Fernandez', 'Lopez',
            'Morales', 'Aquino', 'Villanueva', 'Cruz', 'Jimenez'
        ];
        
        $middleInitials = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'J', 'K', 'L', 'M', 'N', 'P', 'R', 'S', 'T', 'V'];
        
        $courses = [
            'BS Computer Science',
            'BS Information Technology',
            'BS Electrical Engineering',
            'BS Electronics Engineering'
        ];
        
        $yearLevels = ['1', '2', '3', '4'];
        $semesters = ['1', '2'];
        $statuses = ['enrolled', 'enrolled', 'enrolled', 'enrolled', 'inactive']; // 80% enrolled
        
        for ($i = 1; $i <= 100; $i++) {
            $email = "student{$i}@ccdi.edu.ph";
            $studentNumber = '2025-' . str_pad($i, 4, '0', STR_PAD_LEFT);
            $firstName = $firstNames[array_rand($firstNames)];
            $lastName = $lastNames[array_rand($lastNames)];
            $middleInitial = $middleInitials[array_rand($middleInitials)];
            $course = $courses[array_rand($courses)];
            $yearLevel = $yearLevels[array_rand($yearLevels)];
            $semester = $semesters[array_rand($semesters)];
            $status = $statuses[array_rand($statuses)];
            
            // Create User
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'first_name' => $firstName,
                    'middle_initial' => $middleInitial,
                    'last_name' => $lastName,
                    'password' => Hash::make('password'),
                    'role' => 'student',
                    'student_id' => $studentNumber, // Users table has student_id
                    'course' => $course,
                    'year_level' => $yearLevel,
                    'semester' => $semester,
                    'status' => 'active',
                ]
            );
            
            // Create Account for Student
            if (!$user->account) {
                $account = Account::create([
                    'user_id' => $user->id,
                    'account_number' => 'ACC-STU-' . str_pad($user->id, 6, '0', STR_PAD_LEFT),
                    'balance' => -rand(5000, 25000), // Negative balance = student owes money
                    'status' => 'active',
                ]);
                
                // Create Student Record
                // ✅ ONLY use columns that exist in students table
                Student::firstOrCreate(
                    ['email' => $email],
                    [
                        'account_id' => $account->id,
                        'student_number' => $studentNumber,  // ✅ Use student_number (not student_id)
                        'first_name' => $firstName,
                        'middle_name' => $middleInitial,
                        // ✅ REMOVED: 'middle_initial' - doesn't exist in students table
                        // ✅ REMOVED: 'student_id' - doesn't exist in students table
                        'last_name' => $lastName,
                        'email' => $email,
                        'course' => $course,
                        'year_level' => $yearLevel,
                        'semester' => $semester,
                        'status' => $status,
                    ]
                );
            }
            
            if ($i % 20 === 0) {
                echo "   ✓ Created {$i}/100 students...\n";
            }
        }
        
        echo "\n✅ Comprehensive user seeding completed!\n";
        echo "   Total users: " . User::count() . "\n";
        echo "   Total accounts: " . Account::count() . "\n";
        echo "   Total students: " . Student::count() . "\n";
        echo "\n📝 Login credentials:\n";
        echo "   Admin: admin@ccdi.edu.ph / password\n";
        echo "   Accounting: accounting@ccdi.edu.ph / password\n";
        echo "   Students: student1@ccdi.edu.ph - student100@ccdi.edu.ph / password\n";
    }
}