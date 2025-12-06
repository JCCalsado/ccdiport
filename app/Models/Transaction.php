<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Services\AccountService;

class Transaction extends Model
{
    protected $fillable = [
        'account_id',        // ✅ PRIMARY IDENTIFIER
        'user_id',           // ⚠️ Kept for backward compatibility
        'fee_id',
        'reference',
        'payment_channel',
        'kind',
        'type',
        'year',
        'semester',
        'amount',
        'status',
        'paid_at',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'paid_at' => 'datetime',
        'amount' => 'decimal:2',
    ];

    // ============================================
    // RELATIONSHIPS (PRIMARY: account_id)
    // ============================================

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'account_id', 'account_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class); // ⚠️ Backward compatibility
    }

    public function fee(): BelongsTo
    {
        return $this->belongsTo(Fee::class);
    }

    // ============================================
    // SCOPES
    // ============================================

    public function scopeByAccountId($query, string $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    public function scopeCharges($query)
    {
        return $query->where('kind', 'charge');
    }

    public function scopePayments($query)
    {
        return $query->where('kind', 'payment');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeForTerm($query, $year, $semester)
    {
        return $query->where('year', $year)->where('semester', $semester);
    }

    // ============================================
    // MODEL EVENTS
    // ============================================

    protected static function booted()
    {
        static::saved(function ($transaction) {
            if ($transaction->wasChanged(['amount', 'status', 'kind'])) {
                app()->terminating(function () use ($transaction) {
                    if ($transaction->account_id) {
                        $student = Student::where('account_id', $transaction->account_id)->first();
                        if ($student && $student->user) {
                            AccountService::recalculate($student->user);
                        }
                    }
                });
            }
        });
    }

    // ============================================
    // HELPER METHODS
    // ============================================

    public function isCharge(): bool
    {
        return $this->kind === 'charge';
    }

    public function isPayment(): bool
    {
        return $this->kind === 'payment';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }
}