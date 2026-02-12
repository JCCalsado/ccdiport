<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentAssessment extends Model
{
    use HasFactory;

    protected $table = 'student_assessments';

    /**
     * FIXED: Fillable now matches actual database columns
     */
    protected $fillable = [
        'user_id',              // ✅ EXISTS
        'account_id',           // ✅ EXISTS
        'assessment_number',    // ✅ EXISTS
        'year_level',           // ✅ EXISTS
        'semester',             // ✅ EXISTS
        'school_year',          // ✅ EXISTS
        'tuition_fee',          // ✅ EXISTS
        'other_fees',           // ✅ EXISTS
        'registration_fee',     // ✅ EXISTS
        'total_assessment',     // ✅ EXISTS
        'subjects',             // ✅ EXISTS
        'fee_breakdown',        // ✅ EXISTS
        'status',               // ✅ EXISTS
        'created_by',           // ✅ EXISTS
    ];

    protected $casts = [
        'tuition_fee' => 'decimal:2',
        'other_fees' => 'decimal:2',
        'registration_fee' => 'decimal:2',
        'total_assessment' => 'decimal:2',
        'subjects' => 'array',
        'fee_breakdown' => 'array',
    ];

    /**
     * Get the user (student) that owns the assessment.
     */
    public function student()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the user that owns the assessment (alias).
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the payment terms for this assessment.
     */
    public function paymentTerms()
    {
        return $this->hasMany(StudentPaymentTerm::class, 'assessment_id');
    }

    /**
     * Get the creator of the assessment.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}