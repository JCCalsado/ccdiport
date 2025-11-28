<?php

namespace App\Services;

use App\Models\User;
use App\Models\Curriculum;
use App\Models\StudentAssessment;
use App\Models\StudentCurriculum;
use App\Models\StudentPaymentTerm;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AssessmentGeneratorService
{
    /**
     * Generate complete assessment for a student from curriculum
     */
    public function generateFromCurriculum(User $student, Curriculum $curriculum): StudentAssessment
    {
        if ($curriculum->courses->isEmpty()) {
            throw new \Exception('Cannot generate assessment: Curriculum has no courses');
        }

        DB::beginTransaction();
        try {
            // Calculate all fees
            $tuitionFee = $curriculum->calculateTuition();
            $labFees = $curriculum->calculateLabFees();
            $registrationFee = $curriculum->registration_fee;
            $miscFee = $curriculum->misc_fee;
            $otherFees = $labFees + $miscFee;
            $totalAssessment = $tuitionFee + $otherFees + $registrationFee;

            // Prepare subjects data with detailed breakdown
            $subjects = $curriculum->courses->map(function ($course) use ($curriculum) {
                $courseTuition = $course->total_units * $curriculum->tuition_per_unit;
                $courseLab = $course->has_lab ? $curriculum->lab_fee : 0;
                
                return [
                    'id' => $course->id,
                    'code' => $course->code,
                    'title' => $course->title,
                    'lec_units' => (float) $course->lec_units,
                    'lab_units' => (float) $course->lab_units,
                    'total_units' => (float) $course->total_units,
                    'has_lab' => (bool) $course->has_lab,
                    'tuition' => (float) $courseTuition,
                    'lab_fee' => (float) $courseLab,
                    'misc_fee' => 0.0,
                    'total' => (float) ($courseTuition + $courseLab),
                ];
            })->toArray();

            // Prepare fee breakdown
            $feeBreakdown = [
                [
                    'name' => 'Registration Fee',
                    'category' => 'Registration',
                    'amount' => (float) $registrationFee,
                ],
                [
                    'name' => 'Laboratory Fee',
                    'category' => 'Laboratory',
                    'amount' => (float) $labFees,
                ],
                [
                    'name' => 'Miscellaneous Fee',
                    'category' => 'Miscellaneous',
                    'amount' => (float) $miscFee,
                ],
            ];

            // Generate payment terms
            $paymentTerms = $this->generatePaymentTerms($totalAssessment, $curriculum->term_count);

            // Create assessment
            $assessment = StudentAssessment::create([
                'user_id' => $student->id,
                'curriculum_id' => $curriculum->id,
                'assessment_number' => StudentAssessment::generateAssessmentNumber(),
                'year_level' => $curriculum->year_level,
                'semester' => $curriculum->semester,
                'school_year' => $curriculum->school_year,
                'tuition_fee' => $tuitionFee,
                'other_fees' => $otherFees,
                'registration_fee' => $registrationFee,
                'total_assessment' => $totalAssessment,
                'subjects' => $subjects,
                'fee_breakdown' => $feeBreakdown,
                'payment_terms' => $paymentTerms,
                'status' => 'active',
                'created_by' => auth()->id() ?? 1,
            ]);

            // Enroll student in curriculum
            StudentCurriculum::create([
                'user_id' => $student->id,
                'curriculum_id' => $curriculum->id,
                'enrollment_status' => 'active',
                'enrolled_at' => now(),
            ]);

            // Create payment terms
            $this->createPaymentTerms($student, $curriculum, $paymentTerms);

            DB::commit();
            
            \Log::info('Assessment generated successfully', [
                'user_id' => $student->id,
                'assessment_id' => $assessment->id,
                'total_assessment' => $totalAssessment,
            ]);

            return $assessment;

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Assessment generation failed', [
                'user_id' => $student->id,
                'curriculum_id' => $curriculum->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * ✅ FIX: Generate payment terms breakdown
     */
    protected function generatePaymentTerms(float $totalAmount, int $termCount = 5): array
    {
        $termAmount = round($totalAmount / $termCount, 2);
        $lastTermAmount = $totalAmount - ($termAmount * ($termCount - 1));

        return [
            'upon_registration' => $termAmount,
            'prelim' => $termAmount,
            'midterm' => $termAmount,
            'semi_final' => $termAmount,
            'final' => $lastTermAmount,
        ];
    }

    /**
     * ✅ FIX: Create StudentPaymentTerm records
     */
    protected function createPaymentTerms(User $student, Curriculum $curriculum, array $paymentTerms): void
    {
        $termNames = [
            'upon_registration' => 'Upon Registration',
            'prelim' => 'Prelim',
            'midterm' => 'Midterm',
            'semi_final' => 'Semi-Final',
            'final' => 'Final',
        ];

        $order = 1;
        
        // ✅ FIX: Extract year from "2025-2026" format properly
        $yearParts = explode('-', $curriculum->school_year);
        $startYear = (int) $yearParts[0]; // 2025
        $startDate = Carbon::create($startYear, 8, 1); // August 1, 2025

        $weeksMap = [
            'upon_registration' => 0,
            'prelim' => 6,
            'midterm' => 12,
            'semi_final' => 15,
            'final' => 18,
        ];

        foreach ($termNames as $key => $name) {
            if (isset($paymentTerms[$key]) && $paymentTerms[$key] > 0) {
                StudentPaymentTerm::create([
                    'user_id' => $student->id,
                    'curriculum_id' => $curriculum->id,
                    'school_year' => $curriculum->school_year,
                    'semester' => $curriculum->semester,
                    'term_name' => $name,
                    'term_order' => $order,
                    'amount' => $paymentTerms[$key],
                    'due_date' => $startDate->copy()->addWeeks($weeksMap[$key]),
                    'status' => 'pending',
                    'paid_amount' => 0,
                ]);
                $order++;
            }
        }
    }

    /**
     * ✅ FIX: Get curriculum preview with ALL required fields
     */
    public function getCurriculumPreview(int $programId, string $yearLevel, string $semester, string $schoolYear): ?array
    {
        $curriculum = Curriculum::with(['program', 'courses'])
            ->where('program_id', $programId)
            ->where('year_level', $yearLevel)
            ->where('semester', $semester)
            ->where('school_year', $schoolYear)
            ->where('is_active', true)
            ->first();

        if (!$curriculum) {
            return null;
        }

        // Calculate totals
        $tuition = $curriculum->calculateTuition();
        $labFees = $curriculum->calculateLabFees();
        $registrationFee = $curriculum->registration_fee;
        $miscFee = $curriculum->misc_fee;
        $totalAssessment = $tuition + $labFees + $registrationFee + $miscFee;

        return [
            'id' => $curriculum->id,
            'program' => $curriculum->program->full_name,
            'term' => $curriculum->term_description,
            'courses' => $curriculum->courses->map(function ($course) use ($curriculum) {
                return [
                    'code' => $course->code,
                    'title' => $course->title,
                    'lec_units' => (float) $course->lec_units,
                    'lab_units' => (float) $course->lab_units,
                    'total_units' => (float) $course->total_units,
                    'has_lab' => (bool) $course->has_lab,
                ];
            })->values()->toArray(),
            'totals' => [
                'tuition' => (float) $tuition,
                'lab_fees' => (float) $labFees,
                'registration_fee' => (float) $registrationFee, // ✅ NOW INCLUDED
                'misc_fee' => (float) $miscFee, // ✅ NOW INCLUDED
                'total_assessment' => (float) $totalAssessment,
            ],
        ];
    }
}