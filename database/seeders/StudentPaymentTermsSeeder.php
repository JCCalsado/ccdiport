<?php

namespace Database\Seeders;

use App\Models\Student;
use App\Models\StudentAssessment;
use App\Models\StudentPaymentTerm;
use App\Models\User;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class StudentPaymentTermsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        echo "📋 Generating payment terms for students...\n";

        // Check if there are any assessments
        $assessments = StudentAssessment::all();
        
        if ($assessments->isEmpty()) {
            echo "⚠️  No assessments found. Skipping payment terms generation.\n";
            echo "   Run StudentAssessmentSeeder first to create assessments.\n";
            return;
        }

        echo "   Found " . $assessments->count() . " assessments\n";

        // Generate payment terms for each assessment
        foreach ($assessments as $assessment) {
            // Skip if already has payment terms
            if ($assessment->paymentTerms()->exists()) {
                continue;
            }

            // Get the user
            $user = User::find($assessment->user_id);
            if (!$user) {
                echo "   ⚠️  User not found for assessment #{$assessment->id}\n";
                continue;
            }

            // Calculate payment terms (e.g., 3 terms)
            $totalAmount = $assessment->total_assessment;
            $numberOfTerms = 3;
            $amountPerTerm = round($totalAmount / $numberOfTerms, 2);

            // Adjust last term to account for rounding
            $lastTermAmount = $totalAmount - ($amountPerTerm * ($numberOfTerms - 1));

            $terms = [
                [
                    'term_name' => 'Prelim',
                    'term_order' => 1,
                    'amount' => $amountPerTerm,
                    'due_date' => Carbon::parse($assessment->created_at)->addDays(30),
                ],
                [
                    'term_name' => 'Midterm',
                    'term_order' => 2,
                    'amount' => $amountPerTerm,
                    'due_date' => Carbon::parse($assessment->created_at)->addDays(60),
                ],
                [
                    'term_name' => 'Final',
                    'term_order' => 3,
                    'amount' => $lastTermAmount,
                    'due_date' => Carbon::parse($assessment->created_at)->addDays(90),
                ],
            ];

            foreach ($terms as $termData) {
                StudentPaymentTerm::create([
                    'account_id' => $assessment->account_id,
                    'user_id' => $assessment->user_id,
                    'assessment_id' => $assessment->id,
                    'school_year' => $assessment->school_year,
                    'semester' => $assessment->semester,
                    'term_name' => $termData['term_name'],
                    'term_order' => $termData['term_order'],
                    'amount' => $termData['amount'],
                    'due_date' => $termData['due_date'],
                    'paid_amount' => 0,
                    'balance' => $termData['amount'],
                    'status' => 'pending',
                ]);
            }

            echo "   ✓ Created payment terms for assessment #{$assessment->id}\n";
        }

        echo "✅ Payment terms generation completed!\n";
    }
}