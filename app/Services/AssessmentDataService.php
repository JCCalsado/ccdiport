<?php

namespace App\Services;

use App\Models\User;
use App\Models\StudentAssessment;
use App\Models\Transaction;
use App\Models\Payment;
use Illuminate\Support\Collection;

class AssessmentDataService
{
    /**
     * Generate unified assessment data for AccountOverview.vue
     * 
     * @param User $user
     * @return array
     */
    public static function getUnifiedAssessmentData(User $user): array
    {
        // Load relationships
        $user->load(['student', 'account']);
        
        // Get latest assessment
        $latestAssessment = StudentAssessment::where('user_id', $user->id)
            ->where('status', 'active')
            ->latest()
            ->first();

        // Get all transactions
        $transactions = Transaction::where('user_id', $user->id)
            ->with('fee')
            ->orderBy('created_at', 'desc')
            ->get();

        // Get payments
        $payments = $user->student 
            ? Payment::where('student_id', $user->student->id)
                ->orderBy('paid_at', 'desc')
                ->get()
            : collect([]);

        // Build unified response
        return [
            // Student & Account Info
            'student' => self::formatStudent($user),
            'account' => self::formatAccount($user->account),

            // Assessment Summary
            'assessment' => self::formatAssessment($latestAssessment, $transactions),

            // Per-Subject Breakdown
            'assessmentLines' => self::formatAssessmentLines($latestAssessment),

            // Terms of Payment
            'termsOfPayment' => self::formatTermsOfPayment($latestAssessment),

            // Fees List (for fee breakdown display)
            'fees' => self::formatFees($transactions),

            // All Transactions
            'transactions' => self::formatTransactions($transactions),

            // Summary Stats
            'stats' => self::calculateStats($transactions),

            // Current Term Info
            'currentTerm' => self::getCurrentTerm($latestAssessment),
        ];
    }

    /**
     * Format student profile
     */
    protected static function formatStudent(User $user): array
    {
        return [
            'id' => $user->id,
            'student_id' => $user->student_id,
            'name' => $user->name,
            'full_name' => $user->name,
            'email' => $user->email,
            'course' => $user->course,
            'year_level' => $user->year_level,
            'status' => $user->status,
            'birthday' => $user->birthday?->format('Y-m-d'),
            'phone' => $user->phone,
            'address' => $user->address,
        ];
    }

    /**
     * Format account
     */
    protected static function formatAccount($account): ?array
    {
        if (!$account) return null;

        return [
            'id' => $account->id,
            'balance' => (float) $account->balance,
            'created_at' => $account->created_at?->toISOString(),
            'updated_at' => $account->updated_at?->toISOString(),
        ];
    }

    /**
     * Format assessment summary
     */
    protected static function formatAssessment($assessment, Collection $transactions): array
    {
        if (!$assessment) {
            // Fallback: calculate from transactions
            $charges = $transactions->where('kind', 'charge');
            
            return [
                'assessment_number' => 'N/A',
                'school_year' => now()->year . '-' . (now()->year + 1),
                'semester' => now()->month >= 6 && now()->month <= 10 ? '1st Sem' : '2nd Sem',
                'year_level' => 'N/A',
                'total_units' => 0,
                'tuition_fee' => (float) $charges->where('type', 'Tuition')->sum('amount'),
                'lab_fee' => 0,
                'misc_fee' => (float) $charges->whereIn('type', ['Miscellaneous', 'Library', 'Athletic'])->sum('amount'),
                'registration_fee' => (float) $charges->where('type', 'Registration')->sum('amount'),
                'other_fees' => (float) $charges->whereNotIn('type', ['Tuition', 'Registration'])->sum('amount'),
                'total_assessment' => (float) $charges->sum('amount'),
                'status' => 'active',
            ];
        }

        return [
            'id' => $assessment->id,
            'assessment_number' => $assessment->assessment_number,
            'school_year' => $assessment->school_year,
            'semester' => $assessment->semester,
            'year_level' => $assessment->year_level,
            'total_units' => (int) ($assessment->total_units ?? 0),
            'tuition_fee' => (float) $assessment->tuition_fee,
            'lab_fee' => (float) ($assessment->lab_fee ?? 0),
            'misc_fee' => (float) ($assessment->misc_fee ?? 0),
            'registration_fee' => (float) ($assessment->registration_fee ?? 0),
            'other_fees' => (float) $assessment->other_fees,
            'total_assessment' => (float) $assessment->total_assessment,
            'status' => $assessment->status,
            'created_at' => $assessment->created_at?->toISOString(),
        ];
    }

    /**
     * Format per-subject breakdown
     */
    protected static function formatAssessmentLines($assessment): array
    {
        if (!$assessment || !isset($assessment->subjects)) {
            return [];
        }

        return collect($assessment->subjects)->map(function ($subject) {
            return [
                'id' => $subject['id'] ?? null,
                'subject_code' => $subject['subject_code'] ?? $subject['code'] ?? '',
                'code' => $subject['code'] ?? $subject['subject_code'] ?? '',
                'description' => $subject['description'] ?? $subject['title'] ?? $subject['name'] ?? '',
                'title' => $subject['title'] ?? $subject['description'] ?? '',
                'name' => $subject['name'] ?? $subject['description'] ?? '',
                'units' => (int) ($subject['units'] ?? $subject['total_units'] ?? 0),
                'total_units' => (int) ($subject['total_units'] ?? $subject['units'] ?? 0),
                'lec_units' => (int) ($subject['lec_units'] ?? 0),
                'lab_units' => (int) ($subject['lab_units'] ?? 0),
                'tuition' => (float) ($subject['tuition'] ?? 0),
                'lab_fee' => (float) ($subject['lab_fee'] ?? 0),
                'misc_fee' => (float) ($subject['misc_fee'] ?? 0),
                'total' => (float) ($subject['total'] ?? 
                    ($subject['tuition'] ?? 0) + 
                    ($subject['lab_fee'] ?? 0) + 
                    ($subject['misc_fee'] ?? 0)
                ),
            ];
        })->values()->toArray();
    }

    /**
     * Format terms of payment
     */
    protected static function formatTermsOfPayment($assessment): ?array
    {
        if (!$assessment) return null;

        $terms = $assessment->payment_terms ?? [];

        return [
            'upon_registration' => (float) ($terms['upon_registration'] ?? 
                $assessment->upon_registration ?? 
                $assessment->registration ?? 
                0),
            'prelim' => (float) ($terms['prelim'] ?? $assessment->prelim ?? 0),
            'midterm' => (float) ($terms['midterm'] ?? $assessment->midterm ?? 0),
            'semi_final' => (float) ($terms['semi_final'] ?? $assessment->semi_final ?? 0),
            'final' => (float) ($terms['final'] ?? $assessment->final ?? 0),
        ];
    }

    /**
     * Format fees list
     */
    protected static function formatFees(Collection $transactions): array
    {
        $charges = $transactions->where('kind', 'charge');

        // Group by category and sum amounts
        $grouped = $charges->groupBy('type')->map(function ($group) {
            return [
                'name' => $group->first()->type,
                'category' => $group->first()->type,
                'amount' => (float) $group->sum('amount'),
            ];
        });

        return $grouped->values()->toArray();
    }

    /**
     * Format transactions
     */
    protected static function formatTransactions(Collection $transactions): array
    {
        return $transactions->map(function ($txn) {
            return [
                'id' => $txn->id,
                'reference' => $txn->reference,
                'kind' => $txn->kind,
                'type' => $txn->type,
                'amount' => (float) $txn->amount,
                'status' => $txn->status,
                'payment_channel' => $txn->payment_channel,
                'paid_at' => $txn->paid_at?->toISOString(),
                'created_at' => $txn->created_at->toISOString(),
                'meta' => $txn->meta,
                'fee' => $txn->fee ? [
                    'id' => $txn->fee->id,
                    'name' => $txn->fee->name,
                    'category' => $txn->fee->category,
                ] : null,
            ];
        })->toArray();
    }

    /**
     * Calculate summary stats
     */
    protected static function calculateStats(Collection $transactions): array
    {
        $charges = $transactions->where('kind', 'charge')->sum('amount');
        $payments = $transactions->where('kind', 'payment')
            ->where('status', 'paid')
            ->sum('amount');
        
        $balance = $charges - $payments;
        $percentPaid = $charges > 0 ? round(($payments / $charges) * 100, 2) : 0;

        return [
            'total_fees' => (float) $charges,
            'charge_total' => (float) $charges,
            'total_paid' => (float) $payments,
            'payment_total' => (float) $payments,
            'remaining_balance' => (float) max(0, $balance),
            'balance' => (float) $balance,
            'percent_paid' => (float) $percentPaid,
            'pending_charges_count' => $transactions->where('kind', 'charge')
                ->where('status', 'pending')
                ->count(),
        ];
    }

    /**
     * Get current term info
     */
    protected static function getCurrentTerm($assessment): array
    {
        if ($assessment) {
            return [
                'year' => (int) explode('-', $assessment->school_year)[0],
                'semester' => $assessment->semester,
            ];
        }

        $year = now()->year;
        $month = now()->month;
        
        $semester = match(true) {
            $month >= 6 && $month <= 10 => '1st Sem',
            $month >= 11 || $month <= 3 => '2nd Sem',
            default => 'Summer',
        };

        return [
            'year' => $year,
            'semester' => $semester,
        ];
    }
}