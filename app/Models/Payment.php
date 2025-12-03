<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Services\AccountService;

class Payment extends Model
{
    const STATUS_COMPLETED = 'completed';
    const STATUS_PENDING = 'pending';
    const STATUS_FAILED = 'failed';

    protected $fillable = [
        'student_id',      // ⚠️ Keep for backward compatibility
        'account_id',      // ✅ NEW - Primary identifier
        'amount',
        'description',
        'payment_method',
        'reference_number',
        'status',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    // ============================================
    // RELATIONSHIPS
    // ============================================

    /**
     * ✅ NEW: Primary relationship via account_id
     */
    public function studentByAccount(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'account_id', 'account_id');
    }

    /**
     * ⚠️ Keep for backward compatibility (will be deprecated)
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
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

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    // ============================================
    // MODEL EVENTS
    // ============================================

    protected static function booted()
    {
        static::saved(function ($payment) {
            // Recalculate account balance when payment is saved
            if ($payment->account_id) {
                $student = Student::where('account_id', $payment->account_id)->first();
                if ($student && $student->user) {
                    AccountService::recalculate($student->user);
                }
            }
        });
    }

    // ============================================
    // HELPER METHODS
    // ============================================

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }
}