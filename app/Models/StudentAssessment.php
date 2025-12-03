<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentAssessment extends Model
{
    protected $fillable = [
        'user_id',              // ⚠️ Keep for backward compatibility
        'account_id',           // ✅ NEW - Primary identifier
        'curriculum_id',
        'assessment_number',
        'year_level',
        'semester',
        'school_year',
        'tuition_fee',
        'other_fees',
        'registration_fee',
        'total_assessment',
        'subjects',
        'fee_breakdown',
        'payment_terms',
        'status',
        'created_by',
    ];

    protected $casts = [
        'tuition_fee' => 'decimal:2',
        'other_fees' => 'decimal:2',
        'registration_fee' => 'decimal:2',
        'total_assessment' => 'decimal:2',
        'subjects' => 'array',
        'fee_breakdown' => 'array',
        'payment_terms' => 'array',
    ];

    // ============================================
    // RELATIONSHIPS
    // ============================================

    /**
     * ✅ NEW: Primary relationship via account_id
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'account_id', 'account_id');
    }

    /**
     * ⚠️ Keep for backward compatibility (will be deprecated)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(Curriculum::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ============================================
    // SCOPES
    // ============================================

    /**
     * ✅ NEW: Query by account_id (PRIMARY METHOD)
     */
    public function scopeByAccountId($query, string $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForTerm($query, $schoolYear, $semester)
    {
        return $query->where('school_year', $schoolYear)
                     ->where('semester', $semester);
    }

    // ============================================
    // HELPER METHODS
    // ============================================

    /**
     * Generate unique assessment number
     */
    public static function generateAssessmentNumber(): string
    {
        $year = now()->year;
        $lastAssessment = self::where('assessment_number', 'like', "ASS-{$year}-%")
            ->orderBy('id', 'desc')
            ->first();

        if ($lastAssessment) {
            $lastNumber = intval(substr($lastAssessment->assessment_number, -4));
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }

        return "ASS-{$year}-{$newNumber}";
    }

    /**
     * Calculate total from breakdown
     */
    public function calculateTotal(): void
    {
        $this->total_assessment = $this->tuition_fee 
            + $this->other_fees 
            + ($this->registration_fee ?? 0);
        $this->save();
    }

    /**
     * Get payment terms with status
     */
    public function getPaymentTermsStatusAttribute(): array
    {
        if (!$this->payment_terms) {
            return [];
        }

        // This would ideally check StudentPaymentTerm records
        return $this->payment_terms;
    }
}