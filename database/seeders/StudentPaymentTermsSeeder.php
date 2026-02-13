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

            // Calculate payment terms (5 terms with percentages)
            // Upon Registration: 42.15%, Prelim: 17.86%, Midterm: 17.86%, Semi-Final: 14.88%, Final: 7.26%
            $totalAmount = $assessment->total_assessment;
            
            try {
                $startDate = Carbon::parse(explode('-', $assessment->school_year)[0] . '-08-01');
            } catch (\Exception $e) {
                $startDate = Carbon::parse($assessment->created_at);
            }

            // Percentages to match the new 5-term structure
            $percentages = [
                ['name' => 'Upon Registration', 'percentage' => 42.15, 'weeks' => 0],
                ['name' => 'Prelim', 'percentage' => 17.86, 'weeks' => 6],
                ['name' => 'Midterm', 'percentage' => 17.86, 'weeks' => 12],
                ['name' => 'Semi-Final', 'percentage' => 14.88, 'weeks' => 15],
                ['name' => 'Final', 'percentage' => 7.26, 'weeks' => 18],
            ];

            $terms = [];
            $totalCalculated = 0;

            for ($i = 0; $i < count($percentages); $i++) {
                $termData = $percentages[$i];
                
                if ($i === count($percentages) - 1) {
                    // Last term gets the remainder to avoid rounding issues
                    $amount = $totalAmount - $totalCalculated;
                } else {
                    $amount = round($totalAmount * ($termData['percentage'] / 100), 2);
                    $totalCalculated += $amount;
                }

                $terms[] = [
                    'term_name' => $termData['name'],
                    'term_order' => $i + 1,
                    'amount' => $amount,
                    'due_date' => $startDate->copy()->addWeeks($termData['weeks']),
                ];
            }

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