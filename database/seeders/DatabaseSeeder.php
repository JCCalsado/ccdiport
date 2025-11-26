<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('🚀 Starting comprehensive database seeding...');
        $this->command->newLine();

        // Clear existing data (optional - comment out if you want to preserve data)
        $this->command->info('🗑️  Clearing existing data...');
        DB::table('curriculum_courses')->delete();
        DB::table('student_curricula')->delete();
        DB::table('curricula')->delete();
        DB::table('courses')->delete();
        DB::table('programs')->delete();
        DB::table('payments')->delete();
        DB::table('transactions')->delete();
        DB::table('student_assessments')->delete();
        DB::table('students')->delete();
        DB::table('accounts')->delete();
        DB::table('subjects')->delete();
        DB::table('fees')->delete();
        DB::table('notifications')->delete();
        
        $this->command->info('✓ Existing data cleared');
        $this->command->newLine();

        // Seed in correct order
        $this->command->info('📚 Step 1: Seeding OBE Curriculum (Programs, Courses, Curricula)...');
        $this->call(OBECurriculumSeeder::class);
        $this->command->newLine();

        $this->command->info('👥 Step 2: Seeding Users (Admin, Accounting, 100 Students)...');
        $this->call(ComprehensiveUserSeeder::class);
        $this->command->newLine();

        $this->command->info('📖 Step 3: Seeding Legacy Subjects...');
        $this->call(EnhancedSubjectSeeder::class);
        $this->command->newLine();

        $this->command->info('💰 Step 4: Seeding Legacy Fees...');
        $this->call(FeeSeeder::class);
        $this->command->newLine();

        // ⚠️ REMOVED: Auto-assessment generation
        $this->command->warn('⚠️  Step 5: SKIPPED - Automatic assessment generation disabled');
        $this->command->info('   Create assessments manually through:');
        $this->command->info('   • StudentFeeController for individual students');
        $this->command->info('   • CurriculumService for OBE curriculum students');
        $this->command->newLine();

        $this->command->info('🔔 Step 6: Seeding Notifications...');
        $this->call(NotificationSeeder::class);
        $this->command->newLine();

        $this->command->info('✅ Database seeding completed successfully!');
        $this->command->newLine();
        
        $this->displaySummary();
    }

    private function displaySummary(): void
    {
        $this->command->info('📊 SEEDING SUMMARY');
        $this->command->info('═══════════════════════════════════════════════════════');
        
        $userCount = \App\Models\User::count();
        $adminCount = \App\Models\User::where('role', 'admin')->count();
        $accountingCount = \App\Models\User::where('role', 'accounting')->count();
        $studentCount = \App\Models\User::where('role', 'student')->count();
        
        $programCount = \App\Models\Program::count();
        $courseCount = \App\Models\Course::count();
        $curriculumCount = \App\Models\Curriculum::count();
        $assessmentCount = \App\Models\StudentAssessment::count();
        $transactionCount = \App\Models\Transaction::count();
        
        $this->command->table(
            ['Category', 'Count'],
            [
                ['Total Users', $userCount],
                ['├─ Admins', $adminCount],
                ['├─ Accounting Staff', $accountingCount],
                ['└─ Students', $studentCount],
                ['', ''],
                ['OBE Curriculum Data', ''],
                ['├─ Programs', $programCount],
                ['├─ Courses', $courseCount],
                ['└─ Curricula', $curriculumCount],
                ['', ''],
                ['Assessment Data', ''],
                ['├─ Student Assessments', $assessmentCount],
                ['└─ Transactions', $transactionCount],
            ]
        );
        
        $this->command->newLine();
        $this->command->info('🔐 DEFAULT CREDENTIALS');
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->command->table(
            ['Role', 'Email', 'Password'],
            [
                ['Admin', 'admin@ccdi.edu.ph', 'password'],
                ['Accounting', 'accounting@ccdi.edu.ph', 'password'],
                ['Students', 'student1@ccdi.edu.ph to student100@ccdi.edu.ph', 'password'],
            ]
        );
        
        $this->command->newLine();
        $this->command->info('💡 IMPORTANT NOTES');
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->command->warn('• Students have NO automatic charges - all accounts start at ₱0');
        $this->command->info('• Create assessments through: StudentFeeController::create()');
        $this->command->info('• For OBE students: Select program & generate curriculum assessment');
        $this->command->info('• For legacy students: Manually assign subjects and fees');
        $this->command->info('• Run: php artisan db:seed to re-seed all data');
        $this->command->newLine();
    }
}