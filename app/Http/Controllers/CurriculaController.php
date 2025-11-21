<?php

namespace App\Http\Controllers;

use App\Models\Curriculum;
use App\Models\Program;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CurriculaController extends Controller
{
    /**
     * Display a listing of curricula
     */
    public function index(Request $request)
    {
        $query = Curriculum::with(['program', 'courses'])
            ->withCount('courses')
            ->selectRaw('
                curricula.*,
                (SELECT SUM(units + lab_units) FROM curriculum_subjects WHERE curriculum_id = curricula.id) as total_units,
                (
                    (SELECT SUM(units + lab_units) FROM curriculum_subjects WHERE curriculum_id = curricula.id) * curricula.tuition_per_unit +
                    (SELECT SUM(CASE WHEN lab_units > 0 THEN lab_units ELSE 0 END) FROM curriculum_subjects WHERE curriculum_id = curricula.id) * curricula.lab_fee +
                    curricula.registration_fee +
                    curricula.misc_fee
                ) as total_assessment
            ');

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('program', function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('major', 'like', "%{$search}%");
            });
        }

        if ($request->filled('program')) {
            $query->where('program_id', $request->program);
        }

        if ($request->filled('year_level')) {
            $query->where('year_level', $request->year_level);
        }

        if ($request->filled('semester')) {
            $query->where('semester', $request->semester);
        }

        if ($request->filled('school_year')) {
            $query->where('school_year', $request->school_year);
        }

        $curricula = $query->latest()->paginate(15)->withQueryString();

        // Get filter options
        $programs = Program::where('is_active', true)->orderBy('code')->get();
        $yearLevels = Curriculum::distinct()->pluck('year_level')->sort()->values();
        $semesters = Curriculum::distinct()->pluck('semester')->sort()->values();
        $schoolYears = Curriculum::distinct()->pluck('school_year')->sortDesc()->values();

        return Inertia::render('Curricula/Index', [
            'curricula' => $curricula,
            'filters' => $request->only(['search', 'program', 'year_level', 'semester', 'school_year']),
            'programs' => $programs,
            'yearLevels' => $yearLevels,
            'semesters' => $semesters,
            'schoolYears' => $schoolYears,
        ]);
    }

    /**
     * Show the form for creating a new curriculum
     */
    public function create()
    {
        $programs = Program::where('is_active', true)->orderBy('code')->get();
        
        return Inertia::render('Curricula/Create', [
            'programs' => $programs,
        ]);
    }

    /**
     * Store a newly created curriculum
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'program_id' => 'required|exists:programs,id',
            'school_year' => 'required|string',
            'year_level' => 'required|string',
            'semester' => 'required|string',
            'tuition_per_unit' => 'required|numeric|min:0',
            'lab_fee' => 'required|numeric|min:0',
            'registration_fee' => 'required|numeric|min:0',
            'misc_fee' => 'required|numeric|min:0',
            'term_count' => 'required|integer|min:1|max:10',
            'is_active' => 'boolean',
        ]);

        $curriculum = Curriculum::create($validated);

        return redirect()->route('curricula.show', $curriculum)
            ->with('success', 'Curriculum created successfully.');
    }

    /**
     * Display the specified curriculum
     */
    public function show(Curriculum $curriculum)
    {
        $curriculum->load(['program', 'courses.subject']);
        
        return Inertia::render('Curricula/Show', [
            'curriculum' => $curriculum,
        ]);
    }

    /**
     * Show the form for editing the specified curriculum
     */
    public function edit(Curriculum $curriculum)
    {
        $programs = Program::where('is_active', true)->orderBy('code')->get();
        
        return Inertia::render('Curricula/Edit', [
            'curriculum' => $curriculum->load('program'),
            'programs' => $programs,
        ]);
    }

    /**
     * Update the specified curriculum
     */
    public function update(Request $request, Curriculum $curriculum)
    {
        $validated = $request->validate([
            'program_id' => 'required|exists:programs,id',
            'school_year' => 'required|string',
            'year_level' => 'required|string',
            'semester' => 'required|string',
            'tuition_per_unit' => 'required|numeric|min:0',
            'lab_fee' => 'required|numeric|min:0',
            'registration_fee' => 'required|numeric|min:0',
            'misc_fee' => 'required|numeric|min:0',
            'term_count' => 'required|integer|min:1|max:10',
            'is_active' => 'boolean',
        ]);

        $curriculum->update($validated);

        return redirect()->route('curricula.show', $curriculum)
            ->with('success', 'Curriculum updated successfully.');
    }

    /**
     * Remove the specified curriculum
     */
    public function destroy(Curriculum $curriculum)
    {
        $curriculum->delete();

        return redirect()->route('curricula.index')
            ->with('success', 'Curriculum deleted successfully.');
    }

    /**
     * Toggle curriculum active status
     */
    public function toggleStatus(Curriculum $curriculum)
    {
        $curriculum->update([
            'is_active' => !$curriculum->is_active,
        ]);

        return back()->with('success', 'Curriculum status updated.');
    }

    /**
     * Get courses for AJAX requests
     */
    public function getCourses(Request $request)
    {
        $courses = Curriculum::with('courses.subject')
            ->where('program_id', $request->program_id)
            ->where('year_level', $request->year_level)
            ->where('semester', $request->semester)
            ->where('school_year', $request->school_year)
            ->first()
            ?->courses ?? [];

        return response()->json($courses);
    }
}