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
    // SCOPES - BASIC
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

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    // ============================================
    // SCOPES - FILTERING (NEW)
    // ============================================

    /**
     * Filter by year
     */
    public function scopeByYear($query, $year)
    {
        return $query->where('year', $year);
    }

    /**
     * Filter by semester
     */
    public function scopeBySemester($query, $semester)
    {
        return $query->where('semester', $semester);
    }

    /**
     * Filter by transaction type/category
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Filter by payment channel
     */
    public function scopeByPaymentChannel($query, $channel)
    {
        return $query->where('payment_channel', $channel);
    }

    /**
     * Filter by date range
     */
    public function scopeBetweenDates($query, $from, $to)
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }

    /**
     * Filter by created date
     */
    public function scopeCreatedBetween($query, $startDate, $endDate)
    {
        return $query->whereDate('created_at', '>=', $startDate)
                     ->whereDate('created_at', '<=', $endDate);
    }

    /**
     * Filter by paid date
     */
    public function scopePaidBetween($query, $startDate, $endDate)
    {
        return $query->whereNotNull('paid_at')
                     ->whereDate('paid_at', '>=', $startDate)
                     ->whereDate('paid_at', '<=', $endDate);
    }

    /**
     * Get recent transactions (default 30 days)
     */
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Get transactions for current academic term
     */
    public function scopeCurrentTerm($query)
    {
        $currentYear = now()->year;
        $currentMonth = now()->month;
        
        $semester = match(true) {
            $currentMonth >= 6 && $currentMonth <= 10 => '1st Sem',
            $currentMonth >= 11 || $currentMonth <= 3 => '2nd Sem',
            default => 'Summer',
        };
        
        return $query->where('year', $currentYear)
                     ->where('semester', $semester);
    }

    /**
     * Filter by term (year + semester)
     */
    public function scopeForTerm($query, $year, $semester)
    {
        return $query->where('year', $year)
                     ->where('semester', $semester);
    }

    /**
     * Search by reference or type
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('reference', 'like', "%{$search}%")
              ->orWhere('type', 'like', "%{$search}%")
              ->orWhereHas('fee', function($fq) use ($search) {
                  $fq->where('name', 'like', "%{$search}%");
              });
        });
    }

    // ============================================
    // SCOPES - AGGREGATION
    // ============================================

    /**
     * Get total charges
     */
    public function scopeTotalCharges($query)
    {
        return $query->where('kind', 'charge')->sum('amount');
    }

    /**
     * Get total payments
     */
    public function scopeTotalPayments($query)
    {
        return $query->where('kind', 'payment')
                     ->where('status', 'paid')
                     ->sum('amount');
    }

    /**
     * Get pending charges total
     */
    public function scopePendingChargesTotal($query)
    {
        return $query->where('kind', 'charge')
                     ->where('status', 'pending')
                     ->sum('amount');
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

        static::deleted(function ($transaction) {
            app()->terminating(function () use ($transaction) {
                if ($transaction->account_id) {
                    $student = Student::where('account_id', $transaction->account_id)->first();
                    if ($student && $student->user) {
                        AccountService::recalculate($student->user);
                    }
                }
            });
        });
    }

    // ============================================
    // HELPER METHODS
    // ============================================

    /**
     * Check if transaction is a charge
     */
    public function isCharge(): bool
    {
        return $this->kind === 'charge';
    }

    /**
     * Check if transaction is a payment
     */
    public function isPayment(): bool
    {
        return $this->kind === 'payment';
    }

    /**
     * Check if transaction is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if transaction is paid
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * Check if transaction is failed
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if transaction is cancelled
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Get formatted amount with sign
     */
    public function getFormattedAmountAttribute(): string
    {
        $sign = $this->kind === 'charge' ? '+' : '-';
        return $sign . number_format($this->amount, 2);
    }

    /**
     * Get transaction term label
     */
    public function getTermLabelAttribute(): string
    {
        if (!$this->year || !$this->semester) {
            return 'Unknown Term';
        }
        
        return "{$this->year} {$this->semester}";
    }

    /**
     * Get status badge info
     */
    public function getStatusBadgeAttribute(): array
    {
        return match($this->status) {
            'paid' => ['class' => 'success', 'label' => 'Paid'],
            'pending' => ['class' => 'warning', 'label' => 'Pending'],
            'failed' => ['class' => 'danger', 'label' => 'Failed'],
            'cancelled' => ['class' => 'secondary', 'label' => 'Cancelled'],
            default => ['class' => 'default', 'label' => ucfirst($this->status)],
        };
    }

    /**
     * Get kind badge info
     */
    public function getKindBadgeAttribute(): array
    {
        return $this->kind === 'charge'
            ? ['class' => 'danger', 'label' => 'Charge']
            : ['class' => 'success', 'label' => 'Payment'];
    }
}