<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Disable foreign key checks temporarily
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Drop pivot/junction tables first (if they exist)
        Schema::dropIfExists('curriculum_subjects');
        Schema::dropIfExists('curriculum_courses');
        Schema::dropIfExists('student_curricula');
        Schema::dropIfExists('student_enrollments');

        // Drop main tables (if they exist)
        Schema::dropIfExists('curricula');
        Schema::dropIfExists('courses');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('programs');

        // Remove curriculum-related columns from student_assessments
        if (Schema::hasTable('student_assessments')) {
            Schema::table('student_assessments', function (Blueprint $table) {
                // Check and drop foreign key safely
                if (Schema::hasColumn('student_assessments', 'curriculum_id')) {
                    try {
                        // Get all foreign keys
                        $foreignKeys = DB::select("
                            SELECT CONSTRAINT_NAME 
                            FROM information_schema.KEY_COLUMN_USAGE 
                            WHERE TABLE_NAME = 'student_assessments' 
                            AND COLUMN_NAME = 'curriculum_id' 
                            AND CONSTRAINT_SCHEMA = DATABASE()
                        ");
                        
                        // Drop each foreign key found
                        foreach ($foreignKeys as $fk) {
                            DB::statement("ALTER TABLE student_assessments DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
                        }
                    } catch (\Exception $e) {
                        // Foreign key might not exist, continue
                    }
                    
                    // Now drop the column
                    $table->dropColumn('curriculum_id');
                }
                
                // Drop payment_terms column if it exists
                if (Schema::hasColumn('student_assessments', 'payment_terms')) {
                    $table->dropColumn('payment_terms');
                }
            });
        }

        // Remove curriculum_id from student_payment_terms
        if (Schema::hasTable('student_payment_terms')) {
            Schema::table('student_payment_terms', function (Blueprint $table) {
                if (Schema::hasColumn('student_payment_terms', 'curriculum_id')) {
                    try {
                        $foreignKeys = DB::select("
                            SELECT CONSTRAINT_NAME 
                            FROM information_schema.KEY_COLUMN_USAGE 
                            WHERE TABLE_NAME = 'student_payment_terms' 
                            AND COLUMN_NAME = 'curriculum_id' 
                            AND CONSTRAINT_SCHEMA = DATABASE()
                        ");
                        
                        foreach ($foreignKeys as $fk) {
                            DB::statement("ALTER TABLE student_payment_terms DROP FOREIGN KEY `{$fk->CONSTRAINT_NAME}`");
                        }
                    } catch (\Exception $e) {
                        // Continue
                    }
                    
                    $table->dropColumn('curriculum_id');
                }
            });
        }

        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    public function down(): void
    {
        // This is a destructive migration - no rollback
        throw new \Exception('This migration cannot be reversed. Data has been permanently deleted.');
    }
};