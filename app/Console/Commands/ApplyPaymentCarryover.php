<?php

namespace App\Console\Commands;

use App\Models\StudentPaymentTerm;
use App\Models\StudentAssessment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ApplyPaymentCarryover extends Command
{
    protected $signature = 'payment-terms:apply-carryover {--dry-run : Show what would be changed without making changes}';
    protected $description = 'Apply payment carryover logic - unpaid balances from one term carry to the next until fully paid';

    public function handle()
    {
        $dryRun = $this->option('dry-run');

        $this->info('📋 Applying payment carryover logic...');
        $this->line('Unpaid balances will carry forward to the next term automatically.');
        $this->newLine();

        if ($dryRun) {
            $this->warn('⚠️  DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        // Get all assessments with payment terms
        $assessments = StudentAssessment::whereHas('paymentTerms')->get();

        if ($assessments->isEmpty()) {
            $this->error('❌ No assessments with payment terms found.');
            return 1;
        }

        $this->info("Found {$assessments->count()} assessments to process\n");

        $totalTermsProcessed = 0;
        $totalCarriedOver = 0;
        $bar = $this->output->createProgressBar($assessments->count());
        $bar->start();

        try {
            if (!$dryRun) {
                DB::beginTransaction();
            }

            foreach ($assessments as $assessment) {
                // Get all terms for this assessment, ordered by due date
                $terms = StudentPaymentTerm::where('assessment_id', $assessment->id)
                    ->orderBy('term_order')
                    ->get();

                if ($terms->count() > 0) {
                    $carryover = 0;

                    foreach ($terms as $index => $term) {
                        $originalBalance = $term->balance;

                        // Add carryover from previous term to current term's balance
                        if ($carryover > 0) {
                            $term->balance += $carryover;
                            $totalCarriedOver += $carryover;

                            if ($dryRun) {
                                $this->line("  [DRY] {$term->term_name} (Assessment #{$assessment->id}): ₱" . number_format($carryover, 2) . " carried over");
                            } else {
                                $term->save();
                            }
                        }

                        // Calculate carryover for next term
                        // If this term is not fully paid, the remaining balance carries to next term
                        if ($term->status !== 'paid' && $term->remaining_balance > 0) {
                            $carryover = $term->remaining_balance;

                            // Mark this term as having carryover
                            if (!$dryRun && $index < $terms->count() - 1) {
                                $term->remarks = 'Balance carries to next term';
                                $term->save();
                            }
                        } else {
                            $carryover = 0;
                        }

                        $totalTermsProcessed++;
                    }
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);

            if (!$dryRun) {
                DB::commit();
                $this->info("✅ Successfully applied carryover logic to {$assessments->count()} assessments");
                $this->info("📊 Terms processed: {$totalTermsProcessed}");
                $this->info("💰 Total balance carried over: ₱" . number_format($totalCarriedOver, 2));
            } else {
                $this->info("✅ DRY RUN COMPLETE");
                $this->info("📊 Would process {$totalTermsProcessed} terms");
                $this->info("💰 Would carry over: ₱" . number_format($totalCarriedOver, 2));
                $this->line("\nRun without --dry-run flag to apply carryover:");
                $this->line("<comment>php artisan payment-terms:apply-carryover</comment>");
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
