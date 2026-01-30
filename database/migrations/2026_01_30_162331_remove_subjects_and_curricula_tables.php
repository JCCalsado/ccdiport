<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Disable foreign key checks temporarily
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Drop pivot/junction tables first
        Schema::dropIfExists('curriculum_subjects');
        Schema::dropIfExists('curriculum_courses');
        Schema::dropIfExists('student_curricula');
        Schema::dropIfExists('student_enrollments');

        // Drop main tables
        Schema::dropIfExists('curricula');
        Schema::dropIfExists('courses');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('programs');

        // Remove curriculum-related columns from student_assessments
        if (Schema::hasTable('student_assessments')) {
            Schema::table('student_assessments', function (Blueprint $table) {
                // Drop foreign key first if exists
                try {
                    $table->dropForeign(['curriculum_id']);
                } catch (\Exception $e) {
                    // Foreign key might not exist, continue
                }
                
                // Drop columns
                if (Schema::hasColumn('student_assessments', 'curriculum_id')) {
                    $table->dropColumn('curriculum_id');
                }
                if (Schema::hasColumn('student_assessments', 'payment_terms')) {
                    $table->dropColumn('payment_terms');
                }
            });
        }

        // Remove curriculum-related columns from student_payment_terms
        if (Schema::hasTable('student_payment_terms')) {
            Schema::table('student_payment_terms', function (Blueprint $table) {
                // Drop foreign key first if exists
                try {
                    $table->dropForeign(['curriculum_id']);
                } catch (\Exception $e) {
                    // Foreign key might not exist, continue
                }
                
                // Drop column
                if (Schema::hasColumn('student_payment_terms', 'curriculum_id')) {
                    $table->dropColumn('curriculum_id');
                }
            });
        }

        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This is a destructive migration - no rollback
        throw new \Exception('This migration cannot be reversed. Data has been permanently deleted.');
    }
};