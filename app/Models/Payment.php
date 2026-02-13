<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    // ✅ STATUS CONSTANTS - Add these at the top of your Payment model
    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';
    
    protected $fillable = [
        'account_id',
        'amount',
        'payment_method',
        'reference_number',
        'description',
        'status',
        'paid_at',
        'processed_by',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the account that owns the payment
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get the student by account relationship
     */
    public function studentByAccount(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'account_id', 'account_id');
    }

    /**
     * Scope to get completed payments only
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope to get pending payments only
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Check if payment is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if payment is pending
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if payment is failed
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }
}