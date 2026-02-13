<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Account;
use App\Models\Student;
use App\Models\Transaction;
use App\Models\Payment;
use App\Models\StudentAssessment;
use App\Models\StudentPaymentTerm;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AccountingDashboardController extends Controller
{
    public function index(Request $request)
    {
        // ============================================
        // OVERVIEW STATISTICS
        // ============================================
        
        $totalStudents = Student::count();
        $activeStudents = Student::where('status', 'enrolled')->count();
        
        // ============================================
        // FINANCIAL STATISTICS
        // ============================================
        
        // Get total outstanding from student accounts (negative balance = debt)
        $totalOutstanding = abs(Account::whereHas('user', function($query) {
            $query->where('role', 'student');
        })->sum('balance'));

        // ✅ FLEXIBLE PAYMENT QUERIES - Check which column exists
        $paymentColumns = Schema::getColumnListing('payments');
        $hasAccountId = in_array('account_id', $paymentColumns);
        $hasStudentId = in_array('student_id', $paymentColumns);
        $hasUserId = in_array('user_id', $paymentColumns);

        // Build payment query based on available columns
        $paymentQuery = Payment::query();
        if ($hasAccountId) {
            $paymentQuery->whereNotNull('account_id');
        } elseif ($hasStudentId) {
            $paymentQuery->whereNotNull('student_id');
        } elseif ($hasUserId) {
            $paymentQuery->whereNotNull('user_id');
        }

        // Recent payments in last 30 days
        $recentPayments30d = (clone $paymentQuery)
            ->where('created_at', '>=', now()->subDays(30))
            ->where('status', Payment::STATUS_COMPLETED)
            ->sum('amount');

        // Today's payments
        $todayPayments = (clone $paymentQuery)
            ->whereDate('created_at', today())
            ->where('status', Payment::STATUS_COMPLETED)
            ->sum('amount');

        // Total revenue
        $totalRevenue = (clone $paymentQuery)
            ->where('status', Payment::STATUS_COMPLETED)
            ->sum('amount');
        
        // ✅ FLEXIBLE TRANSACTION QUERIES
        $transactionColumns = Schema::getColumnListing('transactions');
        $txnHasAccountId = in_array('account_id', $transactionColumns);

        $transactionQuery = Transaction::query();
        if ($txnHasAccountId) {
            $transactionQuery->whereNotNull('account_id');
        }

        // Pending charges
        $pendingCharges = (clone $transactionQuery)
            ->where('kind', 'charge')
            ->where('status', 'pending')
            ->sum('amount');
        
        // ============================================
        // RECENT TRANSACTIONS
        // ============================================
        
        $recentTransactions = (clone $transactionQuery)
            ->with(['student' => function($query) {
                $query->select('id', 'student_id', 'first_name', 'last_name', 'middle_initial');
            }])
            ->latest('created_at')
            ->take(15)
            ->get()
            ->map(function ($txn) use ($txnHasAccountId) {
                $data = [
                    'id' => $txn->id,
                    'reference' => $txn->reference,
                    'kind' => $txn->kind,
                    'type' => $txn->type,
                    'amount' => (float) $txn->amount,
                    'status' => $txn->status,
                    'created_at' => $txn->created_at->toISOString(),
                    'student' => null,
                ];

                if ($txnHasAccountId) {
                    $data['account_id'] = $txn->account_id;
                }

                if ($txn->student) {
                    $data['student'] = [
                        'student_id' => $txn->student->student_id,
                        'name' => $txn->student->full_name,
                    ];
                }

                return $data;
            });
        
        // ============================================
        // STUDENTS WITH OVERDUE PAYMENTS
        // ============================================
        
        $overdueStudents = Student::whereHas('paymentTerms', function($query) {
                $query->where('due_date', '<', now())
                    ->where('status', '!=', 'paid')
                    ->whereRaw('paid_amount < amount');
            })
            ->with(['paymentTerms' => function($query) {
                $query->where('due_date', '<', now())
                    ->where('status', '!=', 'paid')
                    ->whereRaw('paid_amount < amount')
                    ->orderBy('due_date');
            }])
            ->take(10)
            ->get()
            ->map(function ($student) {
                $overdueTerms = $student->paymentTerms;
                $totalOverdue = $overdueTerms->sum(fn($term) => $term->amount - $term->paid_amount);
                $daysPastDue = $overdueTerms->first() 
                    ? now()->diffInDays($overdueTerms->first()->due_date) 
                    : 0;
                
                return [
                    'student_id' => $student->student_id,
                    'name' => $student->full_name,
                    'course' => $student->course,
                    'year_level' => $student->year_level,
                    'total_overdue' => (float) $totalOverdue,
                    'overdue_terms_count' => $overdueTerms->count(),
                    'oldest_due_date' => $overdueTerms->first()?->due_date?->format('Y-m-d'),
                    'days_past_due' => $daysPastDue,
                ];
            });
        
        // ============================================
        // RECENT ASSESSMENTS
        // ============================================
        
        $assessmentColumns = Schema::getColumnListing('student_assessments');
        $assessmentHasAccountId = in_array('account_id', $assessmentColumns);

        $assessmentQuery = StudentAssessment::query();
        if ($assessmentHasAccountId) {
            $assessmentQuery->whereNotNull('account_id');
        }

        $recentAssessments = (clone $assessmentQuery)
            ->with(['student' => function($query) {
                $query->select('id', 'student_id', 'first_name', 'last_name', 'middle_initial', 'course');
            }])
            ->where('status', 'active')
            ->latest('created_at')
            ->take(5)
            ->get()
            ->map(function ($assessment) use ($assessmentHasAccountId) {
                $data = [
                    'id' => $assessment->id,
                    'assessment_number' => $assessment->assessment_number,
                    'total_assessment' => (float) $assessment->total_assessment,
                    'created_at' => $assessment->created_at->toISOString(),
                    'student' => null,
                ];

                if ($assessmentHasAccountId) {
                    $data['account_id'] = $assessment->account_id;
                }

                if ($assessment->student) {
                    $data['student'] = [
                        'name' => $assessment->student->full_name,
                        'program' => $assessment->student->course,
                    ];
                }

                return $data;
            });

        // ============================================
        // PAYMENT BREAKDOWN BY METHOD (Last 30 days)
        // ============================================
        
        $paymentByMethod = (clone $paymentQuery)
            ->where('status', Payment::STATUS_COMPLETED)
            ->where('created_at', '>=', now()->subDays(30))
            ->select('payment_method', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as total'))
            ->groupBy('payment_method')
            ->get()
            ->map(function ($item) {
                return [
                    'method' => ucfirst(str_replace('_', ' ', $item->payment_method ?? 'Unknown')),
                    'count' => $item->count,
                    'total' => (float) $item->total,
                ];
            });

        // ============================================
        // STUDENTS BY YEAR LEVEL
        // ============================================
        
        $studentsByYearLevel = Student::where('status', 'enrolled')
            ->select('year_level', DB::raw('COUNT(*) as count'))
            ->groupBy('year_level')
            ->get()
            ->map(function ($item) {
                return [
                    'year_level' => $item->year_level,
                    'count' => $item->count,
                ];
            });

        // ============================================
        // CURRENT TERM
        // ============================================
        
        $currentYear = now()->year;
        $currentMonth = now()->month;
        $currentSemester = match(true) {
            $currentMonth >= 6 && $currentMonth <= 10 => '1st Sem',
            $currentMonth >= 11 || $currentMonth <= 3 => '2nd Sem',
            default => 'Summer',
        };

        $currentTerm = [
            'year' => $currentYear,
            'semester' => $currentSemester,
            'school_year' => $currentMonth >= 6 
                ? "{$currentYear}-" . ($currentYear + 1)
                : ($currentYear - 1) . "-{$currentYear}",
        ];

        // ============================================
        // STUDENTS WITH OUTSTANDING BALANCE
        // ============================================
        
        $studentsWithBalance = Student::where('status', 'enrolled')
            ->with('account')
            ->get()
            ->filter(function ($student) {
                return $student->account && abs($student->account->balance) > 0;
            })
            ->sortByDesc(function ($student) {
                return abs($student->account->balance);
            })
            ->take(10)
            ->map(function ($student) {
                return [
                    'student_id' => $student->student_id,
                    'name' => $student->full_name,
                    'course' => $student->course,
                    'year_level' => $student->year_level,
                    'total_balance' => (float) abs($student->account->balance),
                ];
            })
            ->values();

        // ============================================
        // RECENT PAYMENTS (Detailed list)
        // ============================================
        
        $recentPaymentsList = (clone $paymentQuery)
            ->with(['student' => function($query) {
                $query->select('id', 'student_id', 'first_name', 'last_name', 'middle_initial');
            }])
            ->where('status', Payment::STATUS_COMPLETED)
            ->latest('paid_at')
            ->take(10)
            ->get()
            ->map(function ($payment) use ($hasAccountId) {
                $data = [
                    'id' => $payment->id,
                    'amount' => (float) $payment->amount,
                    'payment_method' => $payment->payment_method,
                    'reference_number' => $payment->reference_number,
                    'description' => $payment->description,
                    'paid_at' => $payment->paid_at?->toISOString(),
                    'student' => null,
                ];

                if ($hasAccountId) {
                    $data['account_id'] = $payment->account_id;
                }

                if ($payment->student) {
                    $data['student'] = [
                        'student_id' => $payment->student->student_id,
                        'name' => $payment->student->full_name,
                    ];
                }

                return $data;
            });

        // ============================================
        // PAYMENT TRENDS (Last 7 days)
        // ============================================
        
        $paymentTrends = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $dailyQuery = (clone $paymentQuery)
                ->where('status', Payment::STATUS_COMPLETED)
                ->whereDate('paid_at', $date);

            $dailyTotal = $dailyQuery->sum('amount');
            $dailyCount = $dailyQuery->count();
            
            $paymentTrends[] = [
                'date' => $date->format('Y-m-d'),
                'day' => $date->format('D'),
                'day_full' => $date->format('l'),
                'total' => (float) $dailyTotal,
                'count' => $dailyCount,
            ];
        }

        // ============================================
        // COLLECTION EFFICIENCY (Current Month)
        // ============================================
        
        $currentMonthStart = now()->startOfMonth();
        $currentMonthEnd = now()->endOfMonth();

        $paymentTermColumns = Schema::getColumnListing('student_payment_terms');
        $termHasAccountId = in_array('account_id', $paymentTermColumns);

        $termQuery = StudentPaymentTerm::query();
        if ($termHasAccountId) {
            $termQuery->whereNotNull('account_id');
        }
        
        $expectedCollection = (clone $termQuery)
            ->whereBetween('due_date', [$currentMonthStart, $currentMonthEnd])
            ->sum('amount');
        
        $actualCollection = (clone $paymentQuery)
            ->where('status', Payment::STATUS_COMPLETED)
            ->whereBetween('paid_at', [$currentMonthStart, $currentMonthEnd])
            ->sum('amount');
        
        $collectionRate = $expectedCollection > 0 
            ? round(($actualCollection / $expectedCollection) * 100, 2) 
            : 0;

        // ============================================
        // PENDING ASSESSMENT COUNT
        // ============================================
        
        $pendingAssessments = (clone $assessmentQuery)
            ->where('status', 'active')
            ->whereHas('student', function($query) {
                $query->where('status', 'enrolled');
            })
            ->count();
        
        return Inertia::render('Accounting/Dashboard', [
            'stats' => [
                'total_students' => $totalStudents,
                'active_students' => $activeStudents,
                'total_outstanding' => (float) $totalOutstanding,
                'recent_payments_30d' => (float) $recentPayments30d,
                'today_payments' => (float) $todayPayments,
                'pending_charges' => (float) $pendingCharges,
                'total_revenue' => (float) $totalRevenue,
                'overdue_count' => $overdueStudents->count(),
                'pending_assessments' => $pendingAssessments,
                'collection_rate' => (float) $collectionRate,
                'expected_collection' => (float) $expectedCollection,
                'actual_collection' => (float) $actualCollection,
            ],
            'recentTransactions' => $recentTransactions,
            'overdueStudents' => $overdueStudents,
            'recentAssessments' => $recentAssessments,
            'paymentByMethod' => $paymentByMethod,
            'studentsByYearLevel' => $studentsByYearLevel,
            'currentTerm' => $currentTerm,
            'studentsWithBalance' => $studentsWithBalance,
            'recentPayments' => $recentPaymentsList,
            'paymentTrends' => $paymentTrends,
        ]);
    }
}