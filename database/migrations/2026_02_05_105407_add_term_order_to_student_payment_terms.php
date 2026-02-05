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
        Schema::table('student_payment_terms', function (Blueprint $table) {
            $table->integer('term_order')->default(0)->after('term_name');

            $accountIds = App\Models\StudentPaymentTerm::distinct()->pluck('account_id');

            foreach ($accountIds as $accountId) {
                $terms = App\Models\StudentPaymentTerm::where('account_id', $accountId)
                    ->orderBy('due_date')
                    ->get();
                
                foreach ($terms as $index => $term) {
                    $term->term_order = $index + 1;
                    $term->save();
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_payment_terms', function (Blueprint $table) {
            //
        });
    }
};
