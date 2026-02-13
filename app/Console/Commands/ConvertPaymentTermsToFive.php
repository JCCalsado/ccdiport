<?php

namespace App\Console\Commands;

use App\Models\StudentPaymentTerm;
use App\Models\StudentAssessment;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ConvertPaymentTermsToFive extends Command
{
    protected $signature = 'payment-terms:convert-to-five {--dry-run : Show what would be changed without making changes}';
    protected $description = 'Convert student payment terms to 5-term structure with percentages (Upon Registration: 42.15%, Prelim: 17.86%, Midterm: 17.86%, Semi-Final: 14.88%, Final: 7.26%)';

    /**
     * Payment term percentages
     */
    private const PAYMENT_PERCENTAGES = [
        ['name' => 'Upon Registration', 'order' => 1, 'percentage' => 42.15, 'weeks' => 0],
        ['name' => 'Prelim', 'order' => 2, 'percentage' => 17.86, 'weeks' => 6],
        ['name' => 'Midterm', 'order' => 3, 'percentage' => 17.86, 'weeks' => 12],
        ['name' => 'Semi-Final', 'order' => 4, 'percentage' => 14.88, 'weeks' => 15],
        ['name' => 'Final', 'order' => 5, 'percentage' => 7.26, 'weeks' => 18],
    ];

    public function handle()
    {
        $dryRun = $this->option('dry-run');

        $this->info('📋 Converting payment terms to 5-term structure...');
        $this->line('Percentages: Upon Registration (42.15%), Prelim (17.86%), Midterm (17.86%), Semi-Final (14.88%), Final (7.26%)');
        $this->newLine();

        if ($dryRun) {
            $this->warn('⚠️  DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        // Get all assessments
        $assessments = StudentAssessment::all();

        if ($assessments->isEmpty()) {
            $this->error('❌ No assessments found.');
            return 1;
        }

        $this->info("Found {$assessments->count()} assessments to process\n");

        $totalTermsConverted = 0;
        $bar = $this->output->createProgressBar($assessments->count());
        $bar->start();

        try {
            if (!$dryRun) {
                DB::beginTransaction();
            }

            foreach ($assessments as $assessment) {
                // Delete existing payment terms for this assessment
                $existingTerms = StudentPaymentTerm::where('assessment_id', $assessment->id)->get();
                
                if ($existingTerms->count() > 0) {
                    if ($dryRun) {
                        $this->line("  Deleting {$existingTerms->count()} existing terms for assessment #{$assessment->id}");
                    } else {
                        StudentPaymentTerm::where('assessment_id', $assessment->id)->delete();
                    }
                }

                // Create new 5-term payment structure
                $totalAmount = $assessment->total_assessment;
                $startDate = null;

                try {
                    if ($assessment->school_year) {
                        // Extract year from school_year (e.g., "2025-2026" -> 2025)
                        $year = explode('-', $assessment->school_year)[0];
                        $startDate = Carbon::parse("{$year}-08-01");
                    } else {
                        $startDate = Carbon::now();
                    }
                } catch (\Exception $e) {
                    $startDate = Carbon::now();
                }

                // Calculate amounts based on percentages
                $termAmounts = [];
                $totalCalculated = 0;

                for ($i = 0; $i < count(self::PAYMENT_PERCENTAGES); $i++) {
                    $term = self::PAYMENT_PERCENTAGES[$i];
                    
                    if ($i === count(self::PAYMENT_PERCENTAGES) - 1) {
                        // Last term gets the remainder to avoid rounding issues
                        $amount = $totalAmount - $totalCalculated;
                    } else {
                        $amount = round($totalAmount * ($term['percentage'] / 100), 2);
                        $totalCalculated += $amount;
                    }

                    $termAmounts[] = [
                        'name' => $term['name'],
                        'order' => $term['order'],
                        'percentage' => $term['percentage'],
                        'amount' => $amount,
                        'weeks' => $term['weeks'],
                        'due_date' => $startDate->copy()->addWeeks($term['weeks']),
                    ];
                }

                // Create new payment terms
                foreach ($termAmounts as $termData) {
                    if ($dryRun) {
                        $this->line("  [DRY] Creating term: {$termData['name']} ({$termData['percentage']}%) = ₱" . number_format($termData['amount'], 2));
                    } else {
                        StudentPaymentTerm::create([
                            'account_id' => $assessment->account_id,
                            'user_id' => $assessment->user_id,
                            'assessment_id' => $assessment->id,
                            'school_year' => $assessment->school_year,
                            'semester' => $assessment->semester,
                            'term_name' => $termData['name'],
                            'term_order' => $termData['order'],
                            'amount' => $termData['amount'],
                            'due_date' => $termData['due_date'],
                            'paid_amount' => 0,
                            'balance' => $termData['amount'],
                            'status' => 'pending',
                        ]);
                    }
                }

                $totalTermsConverted += 5;
                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);

            if (!$dryRun) {
                DB::commit();
                $this->info("✅ Successfully converted {$assessments->count()} assessments to 5-term payment structure");
                $this->info("📊 Total payment terms created: {$totalTermsConverted}");
            } else {
                $this->info("✅ DRY RUN COMPLETE - Would convert {$assessments->count()} assessments");
                $this->info("📊 Would create {$totalTermsConverted} payment terms");
                $this->line("\nRun without --dry-run flag to apply changes:");
                $this->line("<comment>php artisan payment-terms:convert-to-five</comment>");
            }

            return 0;

        } catch (\Exception $e) {
            if (!$dryRun) {
                DB::rollBack();
            }
            $bar->finish();
            $this->newLine();
            $this->error("❌ Error: {$e->getMessage()}");
            return 1;
        }
    }
}
