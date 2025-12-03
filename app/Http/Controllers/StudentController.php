<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StudentController extends Controller
{
    // Display all students
    public function index(Request $request)
    {
        $query = Student::with(['user', 'account']);

        // Search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('student_id', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('account_id', 'like', "%{$search}%") // ✅ NEW: Search by account_id
                    ->orWhereRaw("CONCAT(last_name, ', ', first_name, ' ', COALESCE(middle_initial, '')) like ?", ["%{$search}%"]);
            });
        }

        // Course filter
        if ($request->filled('course')) {
            $query->where('course', $request->course);
        }

        // Year level filter
        if ($request->filled('year_level')) {
            $query->where('year_level', $request->year_level);
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $students = $query->latest('created_at')
            ->paginate(15)
            ->withQueryString()
            ->through(function ($student) {
                return [
                    'id' => $student->id,
                    'account_id' => $student->account_id, // ✅ PRIMARY IDENTIFIER
                    'student_id' => $student->student_id,
                    'name' => $student->full_name,
                    'email' => $student->email,
                    'course' => $student->course,
                    'year_level' => $student->year_level,
                    'status' => $student->status,
                    'total_balance' => (float) ($student->total_balance ?? 0),
                    'user' => $student->user ? [
                        'id' => $student->user->id,
                        'status' => $student->user->status,
                    ] : null,
                ];
            });

        // Get filter options
        $courses = Student::whereNotNull('course')
            ->distinct()
            ->orderBy('course')
            ->pluck('course');

        $yearLevels = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
        
        $statuses = [
            'enrolled' => 'Enrolled',
            'graduated' => 'Graduated',
            'inactive' => 'Inactive',
        ];

        return Inertia::render('Students/Index', [
            'students' => $students,
            'filters' => $request->only(['search', 'course', 'year_level', 'status']),
            'courses' => $courses,
            'yearLevels' => $yearLevels,
            'statuses' => $statuses,
        ]);
    }

    public function show($accountId)
    {
        // Find student by account_id
        $student = Student::with(['user.account', 'payments', 'paymentTerms', 'assessments'])
            ->where('account_id', $accountId)
            ->firstOrFail();

        // Get financial summary
        $totalScheduled = $student->paymentTerms->sum('amount');
        $totalPaid = $student->paymentTerms->sum('paid_amount');
        $remainingDue = $totalScheduled - $totalPaid;

        return Inertia::render('Students/StudentProfile', [
            'student' => [
                'id' => $student->id,
                'account_id' => $student->account_id, // ✅ PRIMARY IDENTIFIER
                'student_id' => $student->student_id,
                'name' => $student->full_name,
                'email' => $student->email,
                'course' => $student->course,
                'year_level' => $student->year_level,
                'status' => $student->status,
                'birthday' => $student->birthday?->format('Y-m-d'),
                'phone' => $student->phone,
                'address' => $student->address,
                'total_balance' => (float) ($student->total_balance ?? 0),
                'user' => $student->user ? [
                    'id' => $student->user->id,
                    'email' => $student->user->email,
                    'status' => $student->user->status,
                ] : null,
            ],
            'account' => $student->user?->account ? [
                'id' => $student->user->account->id,
                'balance' => (float) $student->user->account->balance,
            ] : null,
            'payments' => $student->payments->map(fn($payment) => [
                'id' => $payment->id,
                'amount' => (float) $payment->amount,
                'payment_method' => $payment->payment_method,
                'reference_number' => $payment->reference_number,
                'description' => $payment->description,
                'status' => $payment->status,
                'paid_at' => $payment->paid_at?->toISOString(),
            ]),
            'paymentTerms' => $student->paymentTerms->map(fn($term) => [
                'id' => $term->id,
                'term_name' => $term->term_name,
                'term_order' => $term->term_order,
                'amount' => (float) $term->amount,
                'paid_amount' => (float) $term->paid_amount,
                'remaining_balance' => (float) $term->remaining_balance,
                'due_date' => $term->due_date?->format('Y-m-d'),
                'status' => $term->status,
            ]),
            'stats' => [
                'total_scheduled' => (float) $totalScheduled,
                'total_paid' => (float) $totalPaid,
                'remaining_due' => (float) $remainingDue,
            ],
        ]);
    }

    // Student profile (for logged-in student)
    public function profile(Request $request)
    {
        $user = $request->user();
        
        // Get student record by user_id, then use account_id
        $student = Student::with(['payments', 'paymentTerms', 'assessments'])
            ->where('user_id', $user->id)
            ->firstOrFail();

        // Redirect to unified show method using account_id
        return redirect()->route('students.show', $student->account_id);
    }

    // Store payment for a student
    public function storePayment(Request $request, $accountId)
    {
        $student = Student::with(['user', 'account'])
            ->where('account_id', $accountId)
            ->firstOrFail();

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:255',
            'payment_method' => 'required|string|in:cash,gcash,bank_transfer,credit_card,debit_card',
            'reference_number' => 'nullable|string|max:100',
            'status' => 'required|string|in:completed,pending,failed',
            'paid_at' => 'required|date|before_or_equal:today',
        ]);

        // Create payment record linked to account_id
        $payment = $student->payments()->create([
            'account_id' => $student->account_id, // ✅ PRIMARY LINKAGE
            'student_id' => $student->id, // Keep for backward compatibility
            'amount' => $validated['amount'],
            'description' => $validated['description'],
            'payment_method' => $validated['payment_method'],
            'reference_number' => $validated['reference_number'] ?? 'PAY-' . strtoupper(\Illuminate\Support\Str::random(10)),
            'status' => $validated['status'],
            'paid_at' => $validated['paid_at'],
        ]);

        // Recalculate account balance
        if ($student->user) {
            \App\Services\AccountService::recalculate($student->user);
        }

        return back()->with('success', 'Payment recorded successfully!');
    }

    public function edit($accountId)
    {
        $student = Student::with('user')
            ->where('account_id', $accountId)
            ->firstOrFail();

        return Inertia::render('Students/Edit', [
            'student' => [
                'id' => $student->id,
                'account_id' => $student->account_id, // ✅ PRIMARY IDENTIFIER
                'student_id' => $student->student_id,
                'last_name' => $student->last_name,
                'first_name' => $student->first_name,
                'middle_initial' => $student->middle_initial,
                'email' => $student->email,
                'course' => $student->course,
                'year_level' => $student->year_level,
                'status' => $student->status,
                'birthday' => $student->birthday?->format('Y-m-d'),
                'phone' => $student->phone,
                'address' => $student->address,
            ],
            'courses' => ['BS Computer Science', 'BS Information Technology', 'BS Accountancy'],
            'yearLevels' => ['1st Year', '2nd Year', '3rd Year', '4th Year'],
            'statuses' => ['enrolled', 'graduated', 'inactive'],
        ]);
    }

    public function update(Request $request, $accountId)
    {
        $student = Student::with('user')
            ->where('account_id', $accountId)
            ->firstOrFail();

        $validated = $request->validate([
            'student_id' => [
                'required',
                'string',
                'max:50',
                \Illuminate\Validation\Rule::unique('students', 'student_id')->ignore($student->id),
            ],
            'last_name' => 'required|string|max:255',
            'first_name' => 'required|string|max:255',
            'middle_initial' => 'nullable|string|max:10',
            'email' => [
                'required',
                'email',
                \Illuminate\Validation\Rule::unique('students', 'email')->ignore($student->id),
            ],
            'course' => 'required|string|max:255',
            'year_level' => 'required|string|in:1st Year,2nd Year,3rd Year,4th Year',
            'status' => 'required|string|in:enrolled,graduated,inactive',
            'birthday' => 'nullable|date|before:today',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
        ]);

        // Update student record
        $student->update($validated);

        // Update associated user if exists
        if ($student->user) {
            $student->user->update([
                'last_name' => $validated['last_name'],
                'first_name' => $validated['first_name'],
                'middle_initial' => $validated['middle_initial'],
                'email' => $validated['email'],
                'course' => $validated['course'],
                'year_level' => $validated['year_level'],
                'status' => $this->mapStudentStatusToUserStatus($validated['status']),
            ]);
        }

        return redirect()
            ->route('students.show', $student->account_id) // ✅ USE account_id
            ->with('success', 'Student updated successfully!');
    }

    protected function mapStudentStatusToUserStatus(string $studentStatus): string
    {
        return match($studentStatus) {
            'enrolled' => User::STATUS_ACTIVE,
            'graduated' => User::STATUS_GRADUATED,
            'inactive' => User::STATUS_DROPPED,
            default => User::STATUS_ACTIVE,
        };
    }
}