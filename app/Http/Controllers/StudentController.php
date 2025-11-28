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
        $query = Student::with(['payments', 'transactions', 'account']);

        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('last_name', 'like', "%$search%")
                    ->orWhere('first_name', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%")
                    ->orWhere('student_id', 'like', "%$search%")
                    ->orWhere('course', 'like', "%$search%")
                    ->orWhere('year_level', 'like', "%$search%")
                    ->orWhere('phone', 'like', "%$search%")
                    ->orWhere('address', 'like', "%$search%");
            });
        }

        return Inertia::render('Students/Index', [
            'students' => $query->latest()->paginate(10),
            'filters' => $request->only('search'),
        ]);
    }

    public function show(Student $student)
    {
        $student->load(['payments', 'user.account']);
        return Inertia::render('Students/StudentProfile', compact('student'));
    }

    // Student profile (for logged-in student)
    public function profile(Request $request)
    {
        $user = $request->user();
        $student = Student::where('user_id', $user->id)
            ->with(['payments'])
            ->firstOrFail();

        return Inertia::render('Student/Profile', compact('student'));
    }

    // Store payment for a student
    public function storePayment(Request $request, Student $student)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:255',
            'payment_method' => 'required|string',
            'reference_number' => 'nullable|string',
            'status' => 'required|string',
            'paid_at' => 'required|date',
        ]);

        $student->payments()->create($request->all());

        return back()->with('success', 'Payment recorded successfully!');
    }

    public function destroy(Student $student)
    {
        $student->delete();
        return redirect()->route('students.index')->with('success', 'Student deleted successfully!');
    }

    public function edit(Student $student)
    {
        return Inertia::render('Students/Edit', compact('student'));
    }

    public function update(Request $request, Student $student)
    {
        $validated = $request->validate([
            'student_id' => 'required|string|unique:students,student_id,' . $student->id,
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email',
            'course' => 'required|string|max:255',
            'year_level' => 'required|string',
            'birthday' => 'nullable|date',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
        ]);

        $student->update($validated);

        return redirect()->route('students.index')
            ->with('success', 'Student updated successfully!');
    }
}