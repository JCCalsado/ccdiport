<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentPaymentTerm extends Model
{
    protected $fillable = [
        'account_id',     // ✅ PRIMARY IDENTIFIER
        'user_id',        // ⚠️ Backward compatibility
        'school_year',
        'semester',
        'term_name',
        'term_order',
        'amount',
        'due_date',
        'status',
        'paid_amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'due_date' => 'date',
    ];

    protected $appends = [
        'remaining_balance',
    ];

    // ============================================
    // RELATIONSHIPS
    // ============================================

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'account_id', 'account_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class); // ⚠️ Backward compatibility
    }

    // ============================================
    // COMPUTED ATTRIBUTES
    // ============================================

    public function getRemainingBalanceAttribute(): float
    {
        return (float) ($this->amount - $this->paid_amount);
    }

    // ============================================
    // HELPER METHODS
    // ============================================

    public function isFullyPaid(): bool
    {
        return $this->paid_amount >= $this->amount;
    }

    public function isOverdue(): bool
    {
        return $this->due_date 
            && $this->due_date->isPast() 
            && !$this->isFullyPaid();
    }

    // ============================================
    // SCOPES
    // ============================================

    public function scopeByAccountId($query, string $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    public function scopeUnpaid($query)
    {
        return $query->where('status', '!=', 'paid')
                     ->whereRaw('paid_amount < amount');
    }

    public function scopeForTerm($query, $schoolYear, $semester)
    {
        return $query->where('school_year', $schoolYear)
                     ->where('semester', $semester);
    }

    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())
                     ->where('status', '!=', 'paid')
                     ->whereRaw('paid_amount < amount');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopePartial($query)
    {
        return $query->where('status', 'partial');
    }
}