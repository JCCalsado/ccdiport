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
     * Generate unified assessment data for AccountOverview.vue and StudentFees/Show.vue
     */
    public static function getUnifiedAssessmentData(User $user): array
    {
        // Load relationships
        $user->load(['student', 'account']);
        
        // Get latest assessment
        $latestAssessment = StudentAssessment::where('user_id', $user->id)
            ->with('curriculum.program')
            ->where('status', 'active')
            ->latest()
            ->first();

        // Get all transactions
        $transactions = Transaction::where('user_id', $user->id)
            ->with('fee')
            ->orderBy('created_at', 'desc')
            ->get();

        // Get payments (from payments table)
        $payments = $user->student 
            ? Payment::where('student_id', $user->student->id)
                ->orderBy('paid_at', 'desc')
                ->get()
            : collect([]);

        // Build unified response
        $data = [
            'student' => self::formatStudent($user),
            'account' => self::formatAccount($user->account),
            'assessment' => self::formatAssessment($latestAssessment, $transactions),
            'assessmentLines' => self::formatAssessmentLines($latestAssessment),
            'termsOfPayment' => self::formatTermsOfPayment($latestAssessment),
            'fees' => self::formatFees($transactions),
            'transactions' => self::formatTransactions($transactions),
            'payments' => self::formatPayments($payments),
            'stats' => self::calculateStats($transactions, $payments),
            'currentTerm' => self::getCurrentTerm($latestAssessment),
            'feeBreakdown' => self::formatFeeBreakdown($latestAssessment, $transactions),
        ];

        // Validate structure
        self::validateDataStructure($data);

        return $data;
    }

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
                'curriculum' => null,
            ];
        }

        // Calculate total units from subjects
        $totalUnits = 0;
        if (isset($assessment->subjects) && is_array($assessment->subjects)) {
            $totalUnits = collect($assessment->subjects)->sum(function ($subject) {
                return $subject['total_units'] ?? $subject['units'] ?? 0;
            });
        }

        return [
            'id' => $assessment->id,
            'assessment_number' => $assessment->assessment_number,
            'school_year' => $assessment->school_year,
            'semester' => $assessment->semester,
            'year_level' => $assessment->year_level,
            'total_units' => (int) $totalUnits,
            'tuition_fee' => (float) $assessment->tuition_fee,
            'lab_fee' => (float) ($assessment->lab_fee ?? 0),
            'misc_fee' => (float) ($assessment->misc_fee ?? 0),
            'registration_fee' => (float) ($assessment->registration_fee ?? 0),
            'other_fees' => (float) $assessment->other_fees,
            'total_assessment' => (float) $assessment->total_assessment,
            'status' => $assessment->status,
            'created_at' => $assessment->created_at?->toISOString(),
            'curriculum' => $assessment->curriculum ? [
                'id' => $assessment->curriculum->id,
                'program' => [
                    'name' => $assessment->curriculum->program->name,
                    'major' => $assessment->curriculum->program->major,
                ],
            ] : null,
        ];
    }

    protected static function formatAssessmentLines($assessment): array
    {
        if (!$assessment || !isset($assessment->subjects)) {
            return [];
        }

        return collect($assessment->subjects)->map(function ($subject) {
            // Map all possible field names
            $code = $subject['subject_code'] ?? $subject['code'] ?? $subject['course_code'] ?? '';
            $description = $subject['description'] ?? $subject['title'] ?? $subject['name'] ?? '';
            $units = (int) ($subject['units'] ?? $subject['total_units'] ?? 0);
            $lecUnits = (int) ($subject['lec_units'] ?? 0);
            $labUnits = (int) ($subject['lab_units'] ?? 0);
            
            // Calculate fees
            $tuition = (float) ($subject['tuition'] ?? 0);
            $labFee = (float) ($subject['lab_fee'] ?? 0);
            $miscFee = (float) ($subject['misc_fee'] ?? 0);
            $total = (float) ($subject['total'] ?? ($tuition + $labFee + $miscFee));

            return [
                // Code fields (all variations)
                'subject_code' => $code,
                'code' => $code,
                'course_code' => $code,
                
                // Description fields (all variations)
                'description' => $description,
                'title' => $description,
                'name' => $description,
                'subject_name' => $description,
                
                // Unit fields
                'units' => $units,
                'total_units' => $units,
                'lec_units' => $lecUnits,
                'lab_units' => $labUnits,
                
                // Fee fields
                'tuition' => $tuition,
                'lab_fee' => $labFee,
                'misc_fee' => $miscFee,
                'total' => $total,
                
                // Schedule fields (with fallbacks)
                'time' => $subject['time'] ?? $subject['schedule_time'] ?? '08:00 AM - 10:00 AM',
                'day' => $subject['day'] ?? $subject['schedule_day'] ?? 'MTWTHF',
                'semester' => $subject['semester'] ?? null,
            ];
        })->values()->toArray();
    }

    protected static function formatTermsOfPayment($assessment): ?array
    {
        if (!$assessment) return null;

        $terms = $assessment->payment_terms ?? [];

        return [
            'upon_registration' => (float) ($terms['upon_registration'] ?? 
                $assessment->upon_registration ?? 
                $assessment->registration ?? 
                $assessment->registration_fee ?? 
                0),
            'prelim' => (float) ($terms['prelim'] ?? $assessment->prelim ?? 0),
            'midterm' => (float) ($terms['midterm'] ?? $assessment->midterm ?? 0),
            'semi_final' => (float) ($terms['semi_final'] ?? $assessment->semi_final ?? 0),
            'final' => (float) ($terms['final'] ?? $assessment->final ?? 0),
        ];
    }

    protected static function formatFees(Collection $transactions): array
    {
        $charges = $transactions->where('kind', 'charge');

        return $charges->groupBy('type')->map(function ($group) {
            return [
                'name' => $group->first()->type,
                'category' => $group->first()->type,
                'amount' => (float) $group->sum('amount'),
            ];
        })->values()->toArray();
    }

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

    protected static function formatPayments(Collection $payments): array
    {
        return $payments->map(function ($payment) {
            return [
                'id' => $payment->id,
                'student_id' => $payment->student_id,
                'amount' => (float) $payment->amount,
                'description' => $payment->description,
                'payment_method' => $payment->payment_method,
                'reference_number' => $payment->reference_number,
                'status' => $payment->status,
                'paid_at' => $payment->paid_at?->toISOString(),
                'created_at' => $payment->created_at?->toISOString(),
            ];
        })->toArray();
    }

    protected static function calculateStats(Collection $transactions, Collection $payments): array
    {
        $charges = $transactions->where('kind', 'charge')->sum('amount');
        $paymentsFromTransactions = $transactions->where('kind', 'payment')
            ->where('status', 'paid')
            ->sum('amount');
        $paymentsFromTable = $payments->where('status', Payment::STATUS_COMPLETED)->sum('amount');
        
        // Use the higher of the two payment totals
        $totalPayments = max($paymentsFromTransactions, $paymentsFromTable);
        
        $balance = $charges - $totalPayments;
        $percentPaid = $charges > 0 ? round(($totalPayments / $charges) * 100, 2) : 0;

        return [
            'total_fees' => (float) $charges,
            'charge_total' => (float) $charges,
            'total_paid' => (float) $totalPayments,
            'payment_total' => (float) $totalPayments,
            'remaining_balance' => (float) max(0, $balance),
            'balance' => (float) $balance,
            'percent_paid' => (float) $percentPaid,
            'pending_charges_count' => $transactions->where('kind', 'charge')
                ->where('status', 'pending')
                ->count(),
        ];
    }

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

    protected static function formatFeeBreakdown($assessment, Collection $transactions): array
    {
        if (!$assessment) {
            $charges = $transactions->where('kind', 'charge');
            
            return $charges->groupBy('type')->map(function ($group, $category) {
                return [
                    'category' => $category,
                    'total' => (float) $group->sum('amount'),
                    'items' => $group->count(),
                ];
            })->values()->toArray();
        }

        // Group by category from fee_breakdown
        $breakdown = [];
        
        if (isset($assessment->fee_breakdown) && is_array($assessment->fee_breakdown)) {
            foreach ($assessment->fee_breakdown as $fee) {
                $category = $fee['category'] ?? $fee['name'] ?? 'Other';
                
                if (!isset($breakdown[$category])) {
                    $breakdown[$category] = [
                        'category' => $category,
                        'total' => 0,
                        'items' => 0,
                    ];
                }
                
                $breakdown[$category]['total'] += (float) ($fee['amount'] ?? 0);
                $breakdown[$category]['items']++;
            }
        }

        return array_values($breakdown);
    }

    protected static function validateDataStructure(array $data): void
    {
        $requiredKeys = [
            'student',
            'account',
            'assessment',
            'assessmentLines',
            'termsOfPayment',
            'fees',
            'transactions',
            'payments',
            'stats',
            'currentTerm',
            'feeBreakdown',
        ];

        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $data)) {
                throw new \Exception("Missing required key in unified data: {$key}");
            }
        }

        // Validate assessment structure
        if (is_array($data['assessment'])) {
            $requiredAssessmentKeys = [
                'assessment_number',
                'school_year',
                'semester',
                'tuition_fee',
                'total_assessment',
            ];

            foreach ($requiredAssessmentKeys as $key) {
                if (!array_key_exists($key, $data['assessment'])) {
                    \Log::warning("Missing assessment key: {$key}");
                }
            }
        }

        // Validate stats structure
        $requiredStatsKeys = ['total_fees', 'total_paid', 'remaining_balance'];
        foreach ($requiredStatsKeys as $key) {
            if (!isset($data['stats'][$key])) {
                throw new \Exception("Missing stats key: {$key}");
            }
        }
    }
}