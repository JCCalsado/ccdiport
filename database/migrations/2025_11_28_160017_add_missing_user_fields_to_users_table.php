<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Check and add name structure fields
            if (!Schema::hasColumn('users', 'last_name')) {
                $table->string('last_name')->after('id');
            }
            if (!Schema::hasColumn('users', 'first_name')) {
                $table->string('first_name')->after('last_name');
            }
            if (!Schema::hasColumn('users', 'middle_initial')) {
                $table->string('middle_initial')->nullable()->after('first_name');
            }

            // Remove old name column if it exists
            if (Schema::hasColumn('users', 'name')) {
                // First, populate new fields from old name if possible
                DB::statement("UPDATE users SET last_name = SUBSTRING_INDEX(name, ' ', -1), first_name = SUBSTRING_INDEX(name, ' ', 1) WHERE last_name IS NULL OR last_name = ''");
                
                // Then drop the old column
                $table->dropColumn('name');
            }

            // Add missing student fields
            if (!Schema::hasColumn('users', 'student_id')) {
                $table->string('student_id')->nullable()->unique()->after('id');
            }
            if (!Schema::hasColumn('users', 'role')) {
                $table->string('role')->default('student')->after('password');
            }
            if (!Schema::hasColumn('users', 'status')) {
                $table->string('status')->default('active')->after('role');
            }

            // Add contact fields
            if (!Schema::hasColumn('users', 'birthday')) {
                $table->date('birthday')->nullable()->after('email_verified_at');
            }
            if (!Schema::hasColumn('users', 'phone')) {
                $table->string('phone')->nullable()->after('birthday');
            }
            if (!Schema::hasColumn('users', 'address')) {
                $table->string('address')->nullable()->after('phone');
            }
            if (!Schema::hasColumn('users', 'profile_picture')) {
                $table->string('profile_picture')->nullable()->after('address');
            }

            // Add academic fields
            if (!Schema::hasColumn('users', 'course')) {
                $table->string('course')->nullable()->after('profile_picture');
            }
            if (!Schema::hasColumn('users', 'year_level')) {
                $table->string('year_level')->nullable()->after('course');
            }
            if (!Schema::hasColumn('users', 'faculty')) {
                $table->string('faculty')->nullable()->after('year_level');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Restore name column
            if (!Schema::hasColumn('users', 'name')) {
                $table->string('name')->after('id');
                // Combine first and last names back
                DB::statement("UPDATE users SET name = CONCAT(first_name, ' ', last_name)");
            }

            // Drop added columns
            $columns = [
                'last_name', 'first_name', 'middle_initial', 'student_id',
                'role', 'status', 'birthday', 'phone', 'address',
                'profile_picture', 'course', 'year_level', 'faculty'
            ];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};