<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\StudentAssessment;
use App\Models\Fee;
use App\Models\Transaction;
use App\Models\Payment;
use App\Models\StudentPaymentTerm;
use App\Models\Program;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Services\AssessmentDataService;
use App\Services\StudentCreationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use App\Models\OfficialReceipt;

class StudentFeeController extends Controller
{
    protected $studentCreationService;

    public function __construct(
        StudentCreationService $studentCreationService
    ) {
        $this->studentCreationService = $studentCreationService;
    }

    /**
     * ✅ Display listing using account_id
     */
    public function index(Request $request)
    {
        $query = Student::with(['user', 'account'])
            ->whereNotNull('account_id');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('account_id', 'like', "%{$search}%")
                    ->orWhere('student_id', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhereRaw("CONCAT(last_name, ', ', first_name, ' ', COALESCE(middle_initial, '')) like ?", ["%{$search}%"]);
            });
        }

        if ($request->filled('course')) {
            $query->where('course', $request->course);
        }

        if ($request->filled('year_level')) {
            $query->where('year_level', $request->year_level);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $students = $query->orderBy('last_name')
            ->paginate(15)
            ->withQueryString()
            ->through(function ($student) {
                return [
                    'id' => $student->id,
                    'account_id' => $student->account_id,
                    'student_id' => $student->student_id,
                    'name' => $student->full_name,
                    'email' => $student->email,
                    'course' => $student->course,
                    'year_level' => $student->year_level,
                    'status' => $student->status,
                ];
            });

        $courses = Student::whereNotNull('course')
            ->distinct()
            ->pluck('course');

        return Inertia::render('StudentFees/Index', [
            'students' => $students,
            'filters' => $request->only(['search', 'course', 'year_level', 'status']),
            'courses' => $courses,
            'yearLevels' => ['1st Year', '2nd Year', '3rd Year', '4th Year'],
            'statuses' => [
                'enrolled' => 'Enrolled',
                'graduated' => 'Graduated',
                'inactive' => 'Inactive',
            ],
        ]);
    }

    /**
     * ✅ Create form
     */
    public function create(Request $request)
    {
        // AJAX endpoint for student data
        if ($request->has('get_data') && $request->has('account_id')) {
            return $this->getStudentDataForAssessment($request->account_id);
        }

        $students = Student::whereNotNull('account_id')
            ->where('status', 'enrolled')
            ->orderBy('last_name')
            ->get()
            ->map(function ($student) {
                return [
                    'id' => $student->id,
                    'account_id' => $student->account_id,
                    'student_id' => $student->student_id,
                    'name' => $student->full_name,
                    'email' => $student->email,
                    'course' => $student->course,
                    'year_level' => $student->year_level,
                ];
            });

        $yearLevels = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
        $semesters = ['1st Sem', '2nd Sem', 'Summer'];
        $currentYear = now()->year;
        $schoolYears = [
            "{$currentYear}-" . ($currentYear + 1),
            ($currentYear - 1) . "-{$currentYear}",
        ];

        return Inertia::render('StudentFees/Create', [
            'students' => $students,
            'yearLevels' => $yearLevels,
            'semesters' => $semesters,
            'schoolYears' => $schoolYears,
        ]);
    }

    /**
     * ✅ Get student data by account_id (removed subject logic)
     */
    protected function getStudentDataForAssessment(string $accountId)
    {
        $student = Student::where('account_id', $accountId)->firstOrFail();

        // Get available fees
        $fees = Fee::active()
            ->get()
            ->map(function ($fee) {
                return [
                    'id' => $fee->id,
                    'name' => $fee->name,
                    'category' => $fee->category,
                    'amount' => (float) $fee->amount,
                ];
            });

        return response()->json([
            'fees' => $fees,
            'student' => [
                'account_id' => $student->account_id,
                'name' => $student->full_name,
                'course' => $student->course,
                'year_level' => $student->year_level,
            ],
        ]);
    }

    /**
     * ✅ Store assessment (removed subject logic)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'account_id' => 'required|exists:students,account_id',
            'year_level' => 'required|string',
            'semester' => 'required|string',
            'school_year' => 'required|string',
            'tuition_fee' => 'required|numeric|min:0',
            'fees' => 'nullable|array',
            'fees.*.id' => 'required|exists:fees,id',
            'fees.*.amount' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $student = Student::where('account_id', $validated['account_id'])
                ->with('user', 'account')
                ->firstOrFail();

            $tuitionFee = $validated['tuition_fee'];
            $otherFeesTotal = isset($validated['fees'])
                ? collect($validated['fees'])->sum('amount')
                : 0;

            $assessment = StudentAssessment::create([
                'account_id' => $student->account_id,
                'user_id' => $student->user_id,
                'assessment_number' => StudentAssessment::generateAssessmentNumber(),
                'year_level' => $validated['year_level'],
                'semester' => $validated['semester'],
                'school_year' => $validated['school_year'],
                'tuition_fee' => $tuitionFee,
                'other_fees' => $otherFeesTotal,
                'total_assessment' => $tuitionFee + $otherFeesTotal,
                'fee_breakdown' => $validated['fees'] ?? [],
                'status' => 'active',
                'created_by' => auth()->id(),
            ]);

            // Create transactions
            $this->createTransactionsFromAssessment($assessment, $student);

            // Create payment terms
            $this->generatePaymentTermsFromAssessment($assessment, $student);

            DB::commit();

            return redirect()
                ->route('student-fees.show', $student->account_id)
                ->with('success', 'Student fee assessment created successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Assessment creation failed', [
                'account_id' => $request->input('account_id'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * ✅ Show assessment by account_id
     */
    public function show($accountId)
    {
        $student = Student::with(['user', 'account'])
            ->where('account_id', $accountId)
            ->firstOrFail();

        $data = AssessmentDataService::getUnifiedAssessmentData($student->user);

        return Inertia::render('StudentFees/Show', array_merge($data, [
            'account_id' => $accountId,
        ]));
    }

    /**
     * ✅ Store payment using account_id
     */
    public function storePayment(Request $request, $accountId)
    {
        $student = Student::with(['user', 'account'])
            ->where('account_id', $accountId)
            ->firstOrFail();

        $balance = abs($student->user?->account->balance ?? 0);

        $validated = $request->validate([
            'amount' => [
                'required',
                'numeric',
                'min:0.01',
            ],
            'payment_method' => 'required|string|in:cash,gcash,bank_transfer,credit_card,debit_card',
            'description' => 'nullable|string|max:255',
            'payment_date' => 'required|date|before_or_equal:today',
        ]);

        if ($validated['amount'] > $balance) {
            return back()->withErrors(['amount' => 'Payment amount cannot exceed outstanding balance.']);
        }

        DB::beginTransaction();
        try {
            $paymentDate = $validated['payment_date'] ?? now();

            $payment = Payment::create([
                'account_id' => $student->account_id,
                'student_id' => $student->id,
                'user_id' => $student->user_id,
                'amount' => $validated['amount'],
                'payment_method' => $validated['payment_method'],
                'reference_number' => 'PAY-' . strtoupper(Str::random(10)),
                'description' => $validated['description'] ?? 'Payment',
                'status' => Payment::STATUS_COMPLETED,
                'paid_at' => $paymentDate,
            ]);

            Transaction::create([
                'account_id' => $student->account_id,
                'user_id' => $student->user_id,
                'reference' => $payment->reference_number,
                'payment_channel' => $validated['payment_method'],
                'kind' => 'payment',
                'type' => 'Payment',
                'amount' => $validated['amount'],
                'status' => 'paid',
                'paid_at' => $paymentDate,
                'meta' => [
                    'payment_id' => $payment->id,
                    'description' => $validated['description'] ?? 'Payment',
                ],
            ]);

            $receipt = OfficialReceipt::create([
                'account_id' => $student->account_id,
                'payment_id' => $payment->id,
                'receipt_number' => OfficialReceipt::generateReceiptNumber(),
                'amount' => $validated['amount'],
                'issued_at' => now(),
                'issued_by' => auth()->id(),
            ]);

            \App\Services\AccountService::recalculate($student->user);

            DB::commit();

            return back()->with('success', 'Payment recorded. OR #' . $receipt->receipt_number);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payment recording failed', [
                'account_id' => $accountId,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'error' => 'Failed to record payment. Please try again.'
            ]);
        }
    }

    /**
     * ✅ Edit assessment (removed subject logic)
     */
    public function edit($accountId)
    {
        $student = Student::with(['user', 'account'])
            ->where('account_id', $accountId)
            ->firstOrFail();

        $assessment = StudentAssessment::where('account_id', $accountId)
            ->where('status', 'active')
            ->latest()
            ->firstOrFail();

        $fees = Fee::active()->get();

        return Inertia::render('StudentFees/Edit', [
            'student' => [
                'account_id' => $student->account_id,
                'name' => $student->full_name,
                'course' => $student->course,
                'year_level' => $student->year_level,
            ],
            'assessment' => $assessment,
            'fees' => $fees,
        ]);
    }

    /**
     * ✅ Update assessment (removed subject logic)
     */
    public function update(Request $request, string $accountId)
    {
        $validated = $request->validate([
            'year_level' => 'required|string',
            'semester' => 'required|string',
            'school_year' => 'required|string',
            'tuition_fee' => 'required|numeric|min:0',
            'fees' => 'nullable|array',
            'fees.*.id' => 'required|exists:fees,id',
            'fees.*.amount' => 'required|numeric|min:0',
        ]);

        $student = Student::where('account_id', $accountId)
            ->with('user', 'account')
            ->firstOrFail();

        DB::beginTransaction();
        try {
            $assessment = StudentAssessment::where('account_id', $accountId)
                ->where('status', 'active')
                ->latest()
                ->firstOrFail();

            $tuitionFee = $validated['tuition_fee'];
            $otherFeesTotal = isset($validated['fees'])
                ? collect($validated['fees'])->sum('amount')
                : 0;

            $assessment->update([
                'year_level' => $validated['year_level'],
                'semester' => $validated['semester'],
                'school_year' => $validated['school_year'],
                'tuition_fee' => $tuitionFee,
                'other_fees' => $otherFeesTotal,
                'total_assessment' => $tuitionFee + $otherFeesTotal,
                'fee_breakdown' => $validated['fees'] ?? [],
            ]);

            // Create tuition transaction
            Transaction::create([
                'account_id' => $accountId,
                'user_id' => $student->user_id,
                'reference' => 'TUITION-' . strtoupper(Str::random(8)),
                'kind' => 'charge',
                'type' => 'Tuition',
                'year' => explode('-', $validated['school_year'])[0],
                'semester' => $validated['semester'],
                'amount' => $tuitionFee,
                'status' => 'pending',
                'meta' => [
                    'assessment_id' => $assessment->id,
                ],
            ]);

            // Create fee transactions
            if (isset($validated['fees'])) {
                foreach ($validated['fees'] as $fee) {
                    $feeModel = Fee::find($fee['id']);
                    Transaction::create([
                        'account_id' => $accountId,
                        'user_id' => $student->user_id,
                        'fee_id' => $fee['id'],
                        'reference' => 'FEE-' . strtoupper(Str::random(8)),
                        'kind' => 'charge',
                        'type' => $feeModel->category,
                        'year' => explode('-', $validated['school_year'])[0],
                        'semester' => $validated['semester'],
                        'amount' => $fee['amount'],
                        'status' => 'pending',
                        'meta' => [
                            'assessment_id' => $assessment->id,
                            'fee_code' => $feeModel->code,
                            'fee_name' => $feeModel->name,
                        ],
                    ]);
                }
            }

            \App\Services\AccountService::recalculate($student->user);

            DB::commit();

            return redirect()
                ->route('student-fees.show', $accountId)
                ->with('success', 'Student assessment updated successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Assessment update failed', [
                'account_id' => $accountId,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'Failed to update assessment: ' . $e->getMessage()]);
        }
    }

    /**
     * ✅ Export PDF
     */
    public function exportPdf($accountId)
    {
        $student = Student::with(['user', 'account'])
            ->where('account_id', $accountId)
            ->firstOrFail();

        $assessment = StudentAssessment::where('account_id', $accountId)
            ->where('status', 'active')
            ->latest()
            ->firstOrFail();

        $transactions = Transaction::where('account_id', $accountId)
            ->with('fee')
            ->orderBy('created_at', 'desc')
            ->get();

        $payments = Payment::where('account_id', $accountId)
            ->orderBy('paid_at', 'desc')
            ->get();

        $pdf = Pdf::loadView('pdf.student-assessment', [
            'student' => $student,
            'assessment' => $assessment,
            'transactions' => $transactions,
            'payments' => $payments,
        ]);

        return $pdf->download("assessment-{$student->student_id}.pdf");
    }

    /**
     * ✅ Create student form (removed curriculum/program logic)
     */
    public function createStudent()
    {
        $legacyCourses = collect([
            'BS Electrical Engineering Technology',
            'BS Electronics Engineering Technology',
            'BS Computer Science',
            'BS Information Technology',
            'BS Accountancy',
        ]);

        $yearLevels = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
        $semesters = ['1st Sem', '2nd Sem', 'Summer'];
        $currentYear = now()->year;
        $schoolYears = [];
        for ($i = 0; $i < 3; $i++) {
            $year = $currentYear + $i;
            $schoolYears[] = "{$year}-" . ($year + 1);
        }

        return Inertia::render('StudentFees/CreateStudent', [
            'courses' => $legacyCourses,
            'yearLevels' => $yearLevels,
            'semesters' => $semesters,
            'schoolYears' => $schoolYears,
        ]);
    }

    /**
     * ✅ Store new student (removed curriculum logic)
     */
    public function storeStudent(Request $request)
    {
        $validated = $request->validate([
            'last_name' => 'required|string|max:255',
            'first_name' => 'required|string|max:255',
            'middle_initial' => 'nullable|string|max:10',
            'email' => 'required|email|unique:users,email',
            'birthday' => 'required|date|before:today',
            'phone' => 'required|string|max:20',
            'address' => 'required|string|max:255',
            'year_level' => 'required|string|in:1st Year,2nd Year,3rd Year,4th Year',
            'student_id' => 'nullable|string|unique:users,student_id',
            'course' => 'required|string|max:255',
        ]);

        DB::beginTransaction();
        try {
            $studentId = $validated['student_id'] ?? $this->generateUniqueStudentId();
            $courseName = $validated['course'];

            $user = \App\Models\User::create([
                'last_name' => $validated['last_name'],
                'first_name' => $validated['first_name'],
                'middle_initial' => $validated['middle_initial'],
                'email' => $validated['email'],
                'birthday' => $validated['birthday'],
                'phone' => $validated['phone'],
                'address' => $validated['address'],
                'year_level' => $validated['year_level'],
                'course' => $courseName,
                'student_id' => $studentId,
                'role' => 'student',
                'status' => \App\Models\User::STATUS_ACTIVE,
                'password' => Hash::make('password'),
            ]);

            $student = Student::create([
                'user_id' => $user->id,
                'student_id' => $studentId,
                'last_name' => $validated['last_name'],
                'first_name' => $validated['first_name'],
                'middle_initial' => $validated['middle_initial'],
                'email' => $validated['email'],
                'course' => $courseName,
                'year_level' => $validated['year_level'],
                'status' => 'enrolled',
                'birthday' => $validated['birthday'],
                'phone' => $validated['phone'],
                'address' => $validated['address'],
                'total_balance' => 0,
            ]);

            $user->account()->create(['balance' => 0]);

            DB::commit();

            return redirect()
                ->route('student-fees.show', $student->account_id)
                ->with('success', 'Student created successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Student creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withErrors(['error' => 'Failed to create student: ' . $e->getMessage()]);
        }
    }

    /**
     * ✅ Helper: Generate unique student ID
     */
    protected function generateUniqueStudentId(): string
    {
        return DB::transaction(function () {
            $year = now()->year;
            $lastStudent = \App\Models\User::where('student_id', 'like', "{$year}-%")
                ->lockForUpdate()
                ->orderByRaw('CAST(SUBSTRING(student_id, 6) AS UNSIGNED) DESC')
                ->first();

            $lastNumber = $lastStudent ? intval(substr($lastStudent->student_id, 5)) : 0;
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
            return "{$year}-{$newNumber}";
        });
    }

    /**
     * ✅ Helper: Create transactions (removed subject logic)
     */
    protected function createTransactionsFromAssessment(StudentAssessment $assessment, Student $student): void
    {
        // Create tuition transaction
        Transaction::create([
            'account_id' => $student->account_id,
            'user_id' => $student->user_id,
            'reference' => 'TUITION-' . strtoupper(Str::random(8)),
            'kind' => 'charge',
            'type' => 'Tuition',
            'year' => explode('-', $assessment->school_year)[0],
            'semester' => $assessment->semester,
            'amount' => $assessment->tuition_fee,
            'status' => 'pending',
            'meta' => [
                'assessment_id' => $assessment->id,
            ],
        ]);

        // Create fee transactions
        foreach ($assessment->fee_breakdown ?? [] as $fee) {
            $feeModel = Fee::find($fee['id']);
            Transaction::create([
                'account_id' => $student->account_id,
                'user_id' => $student->user_id,
                'fee_id' => $fee['id'],
                'reference' => 'FEE-' . strtoupper(Str::random(8)),
                'kind' => 'charge',
                'type' => $feeModel->category,
                'year' => explode('-', $assessment->school_year)[0],
                'semester' => $assessment->semester,
                'amount' => $fee['amount'],
                'status' => 'pending',
                'meta' => [
                    'assessment_id' => $assessment->id,
                    'fee_code' => $feeModel->code,
                    'fee_name' => $feeModel->name,
                ],
            ]);
        }
    }

    /**
     * ✅ Helper: Generate payment terms
     */
    protected function generatePaymentTermsFromAssessment(StudentAssessment $assessment, Student $student): void
    {
        $totalAmount = $assessment->total_assessment;
        $termAmount = round($totalAmount / 5, 2);
        $lastTermAmount = $totalAmount - ($termAmount * 4);

        $terms = [
            ['name' => 'Upon Registration', 'order' => 1, 'weeks' => 0, 'amount' => $termAmount],
            ['name' => 'Prelim', 'order' => 2, 'weeks' => 6, 'amount' => $termAmount],
            ['name' => 'Midterm', 'order' => 3, 'weeks' => 12, 'amount' => $termAmount],
            ['name' => 'Semi-Final', 'order' => 4, 'weeks' => 15, 'amount' => $termAmount],
            ['name' => 'Final', 'order' => 5, 'weeks' => 18, 'amount' => $lastTermAmount],
        ];

        try {
            $startDate = Carbon::parse(explode('-', $assessment->school_year)[0] . '-08-01');
        } catch (\Exception $e) {
            $startDate = Carbon::now();
        }

        foreach ($terms as $term) {
            StudentPaymentTerm::create([
                'account_id' => $student->account_id,
                'user_id' => $student->user_id,
                'school_year' => $assessment->school_year,
                'semester' => $assessment->semester,
                'term_name' => $term['name'],
                'term_order' => $term['order'],
                'amount' => $term['amount'],
                'due_date' => $startDate->copy()->addWeeks($term['weeks']),
                'status' => 'pending',
                'paid_amount' => 0,
            ]);
        }
    }
}