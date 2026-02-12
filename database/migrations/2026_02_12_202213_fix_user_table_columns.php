<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Add middle_name column if it doesn't exist
            if (!Schema::hasColumn('users', 'middle_name')) {
                $table->string('middle_name')->nullable()->after('first_name');
            }
            
            // Add semester column if it doesn't exist
            if (!Schema::hasColumn('users', 'semester')) {
                $table->string('semester')->nullable()->after('year_level');
            }
            
            // Add student_number column if it doesn't exist
            if (!Schema::hasColumn('users', 'student_number')) {
                $table->string('student_number')->nullable()->unique()->after('role');
            }
        });
        
        // Copy data from old columns to new columns
        DB::statement('UPDATE users SET middle_name = middle_initial WHERE middle_initial IS NOT NULL');
        DB::statement('UPDATE users SET student_number = student_id WHERE student_id IS NOT NULL');
        
        // Optionally drop old columns (commented out for safety)
        // Schema::table('users', function (Blueprint $table) {
        //     $table->dropColumn(['middle_initial', 'student_id']);
        // });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['middle_name', 'semester', 'student_number']);
        });
    }
};