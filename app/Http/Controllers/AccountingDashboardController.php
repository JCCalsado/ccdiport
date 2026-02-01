<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Student;
use App\Models\Transaction;
use App\Models\Payment;
use App\Models\StudentAssessment;
use App\Models\StudentPaymentTerm;
use Illuminate\Support\Facades\DB;

class AccountingDashboardController extends Controller
{
    public function index(Request $request)
    {
        // ============================================
        // OVERVIEW STATISTICS
        // ============================================
        
        $totalStudents = Student::whereNotNull('account_id')->count();
        $activeStudents = Student::whereNotNull('account_id')
            ->where('status', 'enrolled')
            ->count();
        
        // ============================================
        // FINANCIAL STATISTICS
        // ============================================
        
        $totalOutstanding = DB::table('students')
            ->whereNotNull('account_id')
            ->sum('total_balance');
        
        $recentPayments30d = Payment::whereNotNull('account_id')
            ->where('created_at', '>=', now()->subDays(30))
            ->where('status', Payment::STATUS_COMPLETED)
            ->sum('amount');
        
        $todayPayments = Payment::whereNotNull('account_id')
            ->whereDate('created_at', today())
            ->where('status', Payment::STATUS_COMPLETED)
            ->sum('amount');
        
        $pendingCharges = Transaction::whereNotNull('account_id')
            ->where('kind', 'charge')
            ->where('status', 'pending')
            ->sum('amount');
        
        $totalRevenue = Payment::whereNotNull('account_id')
            ->where('status', Payment::STATUS_COMPLETED)
            ->sum('amount');
        
        // ============================================
        // RECENT TRANSACTIONS
        // ============================================
        
        $recentTransactions = Transaction::with(['student' => function($query) {
                $query->select('id', 'account_id', 'student_id', 'first_name', 'last_name', 'middle_initial');
            }])
            ->whereNotNull('account_id')
            ->latest('created_at')
            ->take(15)
            ->get()
            ->map(function ($txn) {
                return [
                    'id' => $txn->id,
                    'account_id' => $txn->account_id,
                    'reference' => $txn->reference,
                    'kind' => $txn->kind,
                    'type' => $txn->type,
                    'amount' => (float) $txn->amount,
                    'status' => $txn->status,
                    'created_at' => $txn->created_at->toISOString(),
                    'student' => $txn->student ? [
                        'account_id' => $txn->student->account_id,
                        'student_id' => $txn->student->student_id,
                        'name' => $txn->student->full_name,
                    ] : null,
                ];
            });
        
        // ============================================
        // STUDENTS WITH OVERDUE PAYMENTS
        // ============================================
        
        $overdueStudents = Student::whereNotNull('account_id')
            ->whereHas('paymentTerms', function($query) {
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
                    'account_id' => $student->account_id,
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
        
        $recentAssessments = StudentAssessment::with(['student' => function($query) {
                $query->select('id', 'account_id', 'student_id', 'first_name', 'last_name', 'middle_initial', 'course');
            }])
            ->whereNotNull('account_id')
            ->where('status', 'active')
            ->latest('created_at')
            ->take(5)
            ->get()
            ->map(function ($assessment) {
                return [
                    'id' => $assessment->id,
                    'account_id' => $assessment->account_id,
                    'assessment_number' => $assessment->assessment_number,
                    'total_assessment' => (float) $assessment->total_assessment,
                    'created_at' => $assessment->created_at->toISOString(),
                    'student' => $assessment->student ? [
                        'account_id' => $assessment->student->account_id,
                        'name' => $assessment->student->full_name,
                        'program' => $assessment->student->course, // ✅ Use course directly
                    ] : null,
                ];
            });

        // ============================================
        // PAYMENT BREAKDOWN BY METHOD (Last 30 days)
        // ============================================
        
        $paymentByMethod = Payment::whereNotNull('account_id')
            ->where('status', Payment::STATUS_COMPLETED)
            ->where('created_at', '>=', now()->subDays(30))
            ->select('payment_method', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as total'))
            ->groupBy('payment_method')
            ->get()
            ->map(function ($item) {
                return [
                    'method' => ucfirst(str_replace('_', ' ', $item->payment_method)),
                    'count' => $item->count,
                    'total' => (float) $item->total,
                ];
            });

        // ============================================
        // STUDENTS BY YEAR LEVEL
        // ============================================
        
        $studentsByYearLevel = Student::whereNotNull('account_id')
            ->where('status', 'enrolled')
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
        
        $studentsWithBalance = Student::whereNotNull('account_id')
            ->where('status', 'enrolled')
            ->where('total_balance', '>', 0)
            ->orderBy('total_balance', 'desc')
            ->take(10)
            ->get()
            ->map(function ($student) {
                return [
                    'account_id' => $student->account_id,
                    'student_id' => $student->student_id,
                    'name' => $student->full_name,
                    'course' => $student->course,
                    'year_level' => $student->year_level,
                    'total_balance' => (float) $student->total_balance,
                ];
            });

        // ============================================
        // RECENT PAYMENTS (Detailed list)
        // ============================================
        
        $recentPaymentsList = Payment::with(['studentByAccount' => function($query) {
                $query->select('id', 'account_id', 'student_id', 'first_name', 'last_name', 'middle_initial');
            }])
            ->whereNotNull('account_id')
            ->where('status', Payment::STATUS_COMPLETED)
            ->latest('paid_at')
            ->take(10)
            ->get()
            ->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'account_id' => $payment->account_id,
                    'amount' => (float) $payment->amount,
                    'payment_method' => $payment->payment_method,
                    'reference_number' => $payment->reference_number,
                    'description' => $payment->description,
                    'paid_at' => $payment->paid_at?->toISOString(),
                    'student' => $payment->studentByAccount ? [
                        'account_id' => $payment->studentByAccount->account_id,
                        'student_id' => $payment->studentByAccount->student_id,
                        'name' => $payment->studentByAccount->full_name,
                    ] : null,
                ];
            });

        // ============================================
        // PAYMENT TRENDS (Last 7 days)
        // ============================================
        
        $paymentTrends = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $dailyTotal = Payment::whereNotNull('account_id')
                ->where('status', Payment::STATUS_COMPLETED)
                ->whereDate('paid_at', $date)
                ->sum('amount');
            
            $dailyCount = Payment::whereNotNull('account_id')
                ->where('status', Payment::STATUS_COMPLETED)
                ->whereDate('paid_at', $date)
                ->count();
            
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
        
        $expectedCollection = StudentPaymentTerm::whereNotNull('account_id')
            ->whereBetween('due_date', [$currentMonthStart, $currentMonthEnd])
            ->sum('amount');
        
        $actualCollection = Payment::whereNotNull('account_id')
            ->where('status', Payment::STATUS_COMPLETED)
            ->whereBetween('paid_at', [$currentMonthStart, $currentMonthEnd])
            ->sum('amount');
        
        $collectionRate = $expectedCollection > 0 
            ? round(($actualCollection / $expectedCollection) * 100, 2) 
            : 0;

        // ============================================
        // PENDING ASSESSMENT COUNT
        // ============================================
        
        $pendingAssessments = StudentAssessment::whereNotNull('account_id')
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