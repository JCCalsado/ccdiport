<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentAssessment extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'student_assessments';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'student_id',
        'academic_year',
        'semester',
        'tuition_fee',
        'other_fees',
        'total_assessment',
        'total_paid',
        'balance',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'tuition_fee' => 'decimal:2',
        'other_fees' => 'decimal:2',
        'total_assessment' => 'decimal:2',
        'total_paid' => 'decimal:2',
        'balance' => 'decimal:2',
    ];

    /**
     * Get the student that owns the assessment.
     */
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the payment terms for this assessment.
     */
    public function paymentTerms()
    {
        return $this->hasMany(StudentPaymentTerm::class, 'assessment_id');
    }
}