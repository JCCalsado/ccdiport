<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\StudentAssessment;
use App\Models\Transaction;
use App\Models\Payment;

class StudentAccountController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        
        // Ensure account exists
        if (!$user->account) {
            $user->account()->create(['balance' => 0]);
        }

        // Load user with necessary relationships
        $user->load(['transactions' => function ($query) {
            $query->orderBy('created_at', 'desc');
        }]);

        // Get latest active assessment WITH curriculum relationship
        $latestAssessment = StudentAssessment::with(['curriculum.courses', 'curriculum.program'])
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->latest()
            ->first();

        // Initialize variables
        $assessment = null;
        $assessmentLines = [];
        $termsOfPayment = null;
        $feeBreakdown = collect();

        // Build assessment data from curriculum if available
        if ($latestAssessment) {
            if ($latestAssessment->curriculum_id && $latestAssessment->curriculum) {
                $curriculum = $latestAssessment->curriculum;
                
                // Build proper subject lines from curriculum courses
                $assessmentLines = $curriculum->courses->map(function ($course) use ($curriculum) {
                    $tuition = $course->total_units * $curriculum->tuition_per_unit;
                    $labFee = $course->has_lab ? $curriculum->lab_fee : 0;
                    
                    return [
                        'subject_code' => $course->code,
                        'code' => $course->code,
                        'description' => $course->title,
                        'title' => $course->title,
                        'name' => $course->title,
                        'units' => $course->total_units,
                        'total_units' => $course->total_units,
                        'lec_units' => $course->lec_units,
                        'lab_units' => $course->lab_units,
                        'tuition' => $tuition,
                        'lab_fee' => $labFee,
                        'misc_fee' => 0,
                        'total' => $tuition + $labFee,
                        'has_lab' => $course->has_lab,
                    ];
                })->toArray();
                
                // Calculate lab fees correctly (sum of all lab fees for courses with labs)
                $labFeesTotal = $curriculum->courses
                    ->filter(fn($course) => $course->has_lab)
                    ->count() * $curriculum->lab_fee;
                
                // Build proper fee breakdown
                $feeBreakdown = collect([
                    [
                        'name' => 'Registration Fee',
                        'amount' => $latestAssessment->registration_fee ?? $curriculum->registration_fee ?? 0,
                        'category' => 'Registration',
                    ],
                    [
                        'name' => 'Tuition Fee',
                        'amount' => $curriculum->calculateTuition(),
                        'category' => 'Tuition',
                    ],
                    [
                        'name' => 'Laboratory Fee',
                        'amount' => $labFeesTotal,
                        'category' => 'Laboratory',
                    ],
                    [
                        'name' => 'Miscellaneous Fee',
                        'amount' => $curriculum->misc_fee ?? 0,
                        'category' => 'Miscellaneous',
                    ],
                ]);
                
                // Build proper terms of payment
                $termsOfPayment = $latestAssessment->payment_terms ?? $curriculum->generatePaymentTerms();
                
            } else {
                // Fallback: use stored subjects from assessment
                $assessmentLines = $latestAssessment->subjects ?? [];
                $termsOfPayment = $latestAssessment->payment_terms;
                
                // Build fee breakdown from transactions
                $feeBreakdown = collect([
                    [
                        'name' => 'Registration Fee',
                        'amount' => $latestAssessment->registration_fee ?? 0,
                        'category' => 'Registration',
                    ],
                    [
                        'name' => 'Tuition Fee',
                        'amount' => $latestAssessment->tuition_fee ?? 0,
                        'category' => 'Tuition',
                    ],
                    [
                        'name' => 'Other Fees',
                        'amount' => $latestAssessment->other_fees ?? 0,
                        'category' => 'Other',
                    ],
                ]);
            }
            
            // Prepare assessment data for frontend
            $assessment = [
                'id' => $latestAssessment->id,
                'assessment_number' => $latestAssessment->assessment_number,
                'school_year' => $latestAssessment->school_year,
                'semester' => $latestAssessment->semester,
                'year_level' => $latestAssessment->year_level,
                'status' => $latestAssessment->status,
                'tuition_fee' => $latestAssessment->tuition_fee,
                'other_fees' => $latestAssessment->other_fees,
                'registration_fee' => $latestAssessment->registration_fee ?? 0,
                'total_assessment' => $latestAssessment->total_assessment,
                'subjects' => $assessmentLines, // Use the properly formatted subjects
                'total_units' => collect($assessmentLines)->sum('units'),
                'created_at' => $latestAssessment->created_at,
                // Add fee breakdown to assessment
                'lab_fee' => $feeBreakdown->where('category', 'Laboratory')->first()['amount'] ?? 0,
                'misc_fee' => $feeBreakdown->where('category', 'Miscellaneous')->first()['amount'] ?? 0,
            ];
        }

        // Get all transactions with fee relation
        $transactions = Transaction::where('user_id', $user->id)
            ->with('fee')
            ->orderBy('created_at', 'desc')
            ->get();

        // Get student payments if student model exists
        $payments = collect();
        if ($user->student) {
            $payments = Payment::where('student_id', $user->student->id)
                ->orderBy('paid_at', 'desc')
                ->get();
        }

        // Build fees list from feeBreakdown (not transactions)
        $fees = $feeBreakdown->map(function ($fee) {
            return [
                'id' => null,
                'name' => $fee['name'],
                'amount' => $fee['amount'],
                'category' => $fee['category'],
            ];
        })->values();

        // Determine current term
        $year = now()->year;
        $month = now()->month;

        if ($month >= 6 && $month <= 10) {
            $semester = '1st Sem';
        } elseif ($month >= 11 || $month <= 3) {
            $semester = '2nd Sem';
        } else {
            $semester = 'Summer';
        }

        $currentTerm = [
            'year' => $latestAssessment->year ?? $year,
            'semester' => $latestAssessment->semester ?? $semester,
        ];

        // Calculate statistics
        $totalCharges = $transactions->where('kind', 'charge')->sum('amount');
        $totalPayments = $transactions->where('kind', 'payment')
            ->where('status', 'paid')
            ->sum('amount');
        $remainingBalance = max(0, $totalCharges - $totalPayments);
        $pendingChargesCount = $transactions->where('kind', 'charge')
            ->where('status', 'pending')
            ->count();

        return Inertia::render('Student/AccountOverview', [
            'student' => $user,
            'account' => $user->account,
            'assessment' => $assessment,
            'assessmentLines' => $assessmentLines,
            'termsOfPayment' => $termsOfPayment,
            'transactions' => $transactions,
            'fees' => $fees,
            'currentTerm' => $currentTerm,
            'tab' => request('tab', 'fees'),
            'stats' => [
                'total_fees' => (float) $totalCharges,
                'total_paid' => (float) $totalPayments,
                'remaining_balance' => (float) $remainingBalance,
                'pending_charges_count' => $pendingChargesCount,
            ],
            'feeBreakdown' => $feeBreakdown->values(),
        ]);
    }
}