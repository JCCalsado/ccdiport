<?php

namespace App\Services;

use App\Models\StudentPaymentTerm;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

class PaymentCarryoverService
{
    /**
     * Apply payment and carry over remaining balance to next terms
     *
     * @param StudentPaymentTerm $paymentTerm
     * @param float $paymentAmount
     * @return array
     */
    public function applyPayment(StudentPaymentTerm $paymentTerm, float $paymentAmount): array
    {
        DB::beginTransaction();

        try {
            $remainingPayment = $paymentAmount;
            $affectedTerms = [];

            // Get all pending/partial terms for this student's assessment in order
            $terms = StudentPaymentTerm::where('account_id', $paymentTerm->account_id)
                ->where('assessment_id', $paymentTerm->assessment_id)
                ->whereIn('status', ['pending', 'partial', 'overdue'])
                ->orderBy('due_date')
                ->get();

            foreach ($terms as $term) {
                if ($remainingPayment <= 0) {
                    break;
                }

                $termBalance = $term->balance;

                if ($remainingPayment >= $termBalance) {
                    // Full payment for this term
                    $term->paid_amount = $term->amount;
                    $term->balance = 0;
                    $term->status = 'paid';
                    $term->payment_date = now();
                    $term->reference_number = 'REF-' . strtoupper(uniqid());

                    $remainingPayment -= $termBalance;
                } else {
                    // Partial payment
                    $term->paid_amount += $remainingPayment;
                    $term->balance -= $remainingPayment;
                    $term->status = 'partial';

                    $remainingPayment = 0;
                }

                $term->save();
                $affectedTerms[] = $term;
            }

            // Update student's total balance
            $this->updateStudentBalance($paymentTerm->account_id);

            DB::commit();

            return [
                'success' => true,
                'amount_applied' => $paymentAmount - $remainingPayment,
                'remaining_amount' => $remainingPayment,
                'affected_terms' => $affectedTerms,
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Update student's total balance across all assessments
     *
     * @param string $accountId
     * @return void
     */
    private function updateStudentBalance(string $accountId): void
    {
        $totalBalance = StudentPaymentTerm::where('account_id', $accountId)
            ->sum('balance');

        Student::where('account_id', $accountId)
            ->update(['total_balance' => $totalBalance]);
    }

    /**
     * Get payment summary for a student
     *
     * @param string $accountId
     * @return array
     */
    public function getPaymentSummary(string $accountId): array
    {
        $terms = StudentPaymentTerm::where('account_id', $accountId)
            ->orderBy('due_date')
            ->get();

        return [
            'total_assessment' => $terms->sum('amount'),
            'total_paid' => $terms->sum('paid_amount'),
            'total_balance' => $terms->sum('balance'),
            'paid_terms' => $terms->where('status', 'paid')->count(),
            'pending_terms' => $terms->whereIn('status', ['pending', 'overdue'])->count(),
            'partial_terms' => $terms->where('status', 'partial')->count(),
        ];
    }
}