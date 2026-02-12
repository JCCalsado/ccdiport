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
        User::where('email', 'like', '%@demo.test')->delete();
        echo "   ✓ No demo students to clean\n";
        
        // Seed admin and accounting staff
        echo "👤 Seeding admin and accounting staff...\n";
        
        // Create Admin User
        $admin = User::firstOrCreate(
            ['email' => 'admin@ccdi.edu'],
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
            ['email' => 'accounting@ccdi.edu'],
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
        
        // Seed student users
        echo "\n👨‍🎓 Seeding student users...\n";
        
        $students = [
            [
                'student_id' => '2021-00001',  // Changed from student_number
                'first_name' => 'Juan',
                'middle_initial' => 'D',  // Changed from middle_name
                'last_name' => 'Cruz',
                'email' => 'juan.delacruz@ccdi.edu',
                'course' => 'BSCS',
                'year_level' => '4',
                'semester' => '2',
            ],
            [
                'student_id' => '2021-00002',  // Changed from student_number
                'first_name' => 'Maria',
                'middle_initial' => 'S',  // Changed from middle_name
                'last_name' => 'Garcia',
                'email' => 'maria.garcia@ccdi.edu',
                'course' => 'BSIT',
                'year_level' => '3',
                'semester' => '2',
            ],
            [
                'student_id' => '2022-00001',  // Changed from student_number
                'first_name' => 'Pedro',
                'middle_initial' => 'R',  // Changed from middle_name
                'last_name' => 'Lopez',
                'email' => 'pedro.lopez@ccdi.edu',
                'course' => 'BSCS',
                'year_level' => '2',
                'semester' => '2',
            ],
        ];
        
        foreach ($students as $studentData) {
            // Create User
            $user = User::firstOrCreate(
                ['email' => $studentData['email']],
                [
                    'first_name' => $studentData['first_name'],
                    'middle_initial' => $studentData['middle_initial'],  // Changed
                    'last_name' => $studentData['last_name'],
                    'password' => Hash::make('password'),
                    'role' => 'student',
                    'student_id' => $studentData['student_id'],  // Changed
                    'course' => $studentData['course'],
                    'year_level' => $studentData['year_level'],
                    'semester' => $studentData['semester'] ?? null,  // Added null fallback
                    'status' => 'active',
                ]
            );
            
            // Create Account for Student
            if (!$user->account) {
                $account = Account::create([
                    'user_id' => $user->id,
                    'account_number' => 'ACC-STU-' . str_pad($user->id, 6, '0', STR_PAD_LEFT),
                    'balance' => 0,
                    'status' => 'active',
                ]);
                
                // Create Student Record
                Student::firstOrCreate(
                    ['email' => $studentData['email']],
                    [
                        'student_number' => $studentData['student_id'],  // Students table uses student_number
                        'first_name' => $studentData['first_name'],
                        'middle_name' => $studentData['middle_initial'],  // Students table uses middle_name
                        'last_name' => $studentData['last_name'],
                        'course' => $studentData['course'],
                        'year_level' => $studentData['year_level'],
                        'status' => 'active',
                        'account_id' => $account->id,
                    ]
                );
            }
            
            echo "   ✓ Student created: {$user->email} ({$studentData['student_id']})\n";
        }
        
        echo "\n✅ Comprehensive user seeding completed!\n";
        echo "   Total users: " . User::count() . "\n";
        echo "   Total accounts: " . Account::count() . "\n";
        echo "   Total students: " . Student::count() . "\n";
    }
}