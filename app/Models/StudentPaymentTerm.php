<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentPaymentTerm extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'account_id',
        'user_id',
        'assessment_id',
        'school_year',
        'semester',
        'term_name',
        'term_order',
        'amount',
        'due_date',
        'status',
        'paid_amount',
        'balance',
        'payment_date',
        'reference_number',
        'remarks',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'due_date' => 'date',
        'payment_date' => 'datetime',
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'balance' => 'decimal:2',
    ];

    /**
     * The accessors to append to the model's array form.
     */
    protected $appends = [
        'remaining_balance',
        'is_overdue',
    ];

    // ============================================
    // RELATIONSHIPS
    // ============================================

    /**
     * Get the student that owns the payment term.
     * Uses account_id as the foreign key.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'account_id', 'account_id');
    }

    /**
     * Get the assessment that this payment term belongs to.
     */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(StudentAssessment::class, 'assessment_id');
    }

    /**
     * Get the user (backward compatibility)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // ============================================
    // SCOPES - ACCOUNT ID
    // ============================================

    /**
     * Scope by account_id
     */
    public function scopeByAccountId($query, string $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    // ============================================
    // SCOPES - STATUS
    // ============================================

    /**
     * Scope: pending payments (not paid, not overdue)
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending')
                     ->where('due_date', '>=', now());
    }

    /**
     * Scope: paid payments
     */
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    /**
     * Scope: partial payments
     */
    public function scopePartial($query)
    {
        return $query->where('status', 'partial');
    }

    /**
     * Scope: overdue payments (past due date and not paid)
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', '!=', 'paid')
                     ->where('due_date', '<', now());
    }

    /**
     * Scope: unpaid (pending OR partial OR overdue - basically anything not fully paid)
     */
    public function scopeUnpaid($query)
    {
        return $query->where('status', '!=', 'paid')
                     ->whereRaw('paid_amount < amount');
    }

    /**
     * Scope: due soon (within next 7 days)
     */
    public function scopeDueSoon($query)
    {
        return $query->where('status', '!=', 'paid')
                     ->whereBetween('due_date', [now(), now()->addDays(7)]);
    }

    // ============================================
    // SCOPES - ORDERING
    // ============================================

    /**
     * Scope: order by term order
     */
    public function scopeOrderedByTerm($query)
    {
        return $query->orderBy('term_order');
    }

    /**
     * Scope: order by due date
     */
    public function scopeOrderedByDueDate($query)
    {
        return $query->orderBy('due_date');
    }

    // ============================================
    // SCOPES - FILTERING
    // ============================================

    /**
     * Scope: for specific assessment
     */
    public function scopeForAssessment($query, int $assessmentId)
    {
        return $query->where('assessment_id', $assessmentId);
    }

    /**
     * Scope: for specific term (school year + semester)
     */
    public function scopeForTerm($query, string $schoolYear, string $semester)
    {
        return $query->where('school_year', $schoolYear)
                     ->where('semester', $semester);
    }

    // ============================================
    // ACCESSORS
    // ============================================

    /**
     * Get remaining balance (calculated)
     */
    public function getRemainingBalanceAttribute(): float
    {
        return (float) ($this->amount - $this->paid_amount);
    }

    /**
     * Check if payment is overdue
     */
    public function getIsOverdueAttribute(): bool
    {
        if ($this->status === 'paid') {
            return false;
        }

        return $this->due_date < now();
    }

    // ============================================
    // HELPER METHODS
    // ============================================

    /**
     * Check if payment is overdue (method form for backward compatibility)
     */
    public function isOverdue(): bool
    {
        return $this->is_overdue;
    }

    /**
     * Check if payment is fully paid
     */
    public function isFullyPaid(): bool
    {
        return $this->status === 'paid' && $this->paid_amount >= $this->amount;
    }

    /**
     * Check if payment is partially paid
     */
    public function isPartiallyPaid(): bool
    {
        return $this->paid_amount > 0 && $this->paid_amount < $this->amount;
    }

    /**
     * Apply payment to this term
     */
    public function applyPayment(float $amount): float
    {
        $remainingBalance = $this->amount - $this->paid_amount;
        $paymentApplied = min($amount, $remainingBalance);

        $this->paid_amount += $paymentApplied;
        $this->balance = $this->amount - $this->paid_amount;

        // Update status
        if ($this->paid_amount >= $this->amount) {
            $this->status = 'paid';
            $this->payment_date = now();
        } elseif ($this->paid_amount > 0) {
            $this->status = 'partial';
        }

        $this->save();

        return $amount - $paymentApplied; // Return remaining payment amount
    }

    /**
     * Mark term as overdue if past due date
     */
    public function checkOverdue(): void
    {
        if ($this->status !== 'paid' && $this->due_date < now()) {
            $this->status = 'overdue';
            $this->save();
        }
    }

    /**
     * Get days until due or days overdue
     */
    public function getDaysUntilDue(): int
    {
        return now()->diffInDays($this->due_date, false);
    }

    /**
     * Get formatted status badge
     */
    public function getStatusBadgeAttribute(): array
    {
        return match($this->status) {
            'paid' => ['class' => 'success', 'label' => 'Paid'],
            'partial' => ['class' => 'warning', 'label' => 'Partial'],
            'pending' => ['class' => 'info', 'label' => 'Pending'],
            'overdue' => ['class' => 'danger', 'label' => 'Overdue'],
            default => ['class' => 'default', 'label' => ucfirst($this->status)],
        };
    }

    // ============================================
    // MODEL EVENTS
    // ============================================

    protected static function booted()
    {
        // Auto-update balance when amount or paid_amount changes
        static::saving(function ($term) {
            $term->balance = $term->amount - $term->paid_amount;
        });

        // Auto-update student's total balance after save
        static::saved(function ($term) {
            if ($term->account_id) {
                $student = Student::where('account_id', $term->account_id)->first();
                if ($student && $student->user) {
                    app()->terminating(function () use ($student) {
                        \App\Services\AccountService::recalculate($student->user);
                    });
                }
            }
        });
    }

    /**
     * Get payment statistics for an account
     */
    public static function getStatistics(string $accountId): array
    {
        $terms = self::byAccountId($accountId)->get();

        return [
            'total_amount' => $terms->sum('amount'),
            'total_paid' => $terms->sum('paid_amount'),
            'total_balance' => $terms->sum('balance'),
            'count_paid' => $terms->where('status', 'paid')->count(),
            'count_unpaid' => $terms->where('status', '!=', 'paid')->count(),
            'count_overdue' => $terms->where('status', 'overdue')->count(),
            'count_partial' => $terms->where('status', 'partial')->count(),
            'next_due_date' => $terms->where('status', '!=', 'paid')
                ->min('due_date'),
        ];
    }
}