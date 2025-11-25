<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_assessments', function (Blueprint $table) {
            // Check if these columns don't already exist
            if (!Schema::hasColumn('student_assessments', 'curriculum_id')) {
                $table->foreignId('curriculum_id')->nullable()->after('user_id')->constrained()->onDelete('set null');
            }
            if (!Schema::hasColumn('student_assessments', 'registration_fee')) {
                $table->decimal('registration_fee', 12, 2)->default(0)->after('other_fees');
            }
            if (!Schema::hasColumn('student_assessments', 'payment_terms')) {
                $table->json('payment_terms')->nullable()->after('fee_breakdown');
            }
        });
    }

    public function down(): void
    {
        Schema::table('student_assessments', function (Blueprint $table) {
            if (Schema::hasColumn('student_assessments', 'curriculum_id')) {
                $table->dropForeign(['curriculum_id']);
                $table->dropColumn(['curriculum_id', 'registration_fee', 'payment_terms']);
            }
        });
    }
};