<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class Student extends Model
{
    protected $fillable = [
        'user_id',
        'account_id',       // ✅ NEW PRIMARY IDENTIFIER
        'student_id',       // Keep for backward compatibility
        'last_name',
        'first_name',
        'middle_initial',
        'email',
        'course',
        'year_level',
        'birthday',
        'phone',
        'address',
        'total_balance',
        'status',
    ];

    protected $casts = [
        'birthday' => 'date',
        'total_balance' => 'decimal:2',
    ];

    /**
     * ✅ Auto-generate account_id on creation
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($student) {
            if (empty($student->account_id)) {
                $student->account_id = self::generateAccountId();
            }
        });

        // ✅ Prevent account_id modification after creation
        static::updating(function ($student) {
            if ($student->isDirty('account_id') && !empty($student->getOriginal('account_id'))) {
                throw new \RuntimeException('Account ID cannot be changed once set. Original: ' . $student->getOriginal('account_id'));
            }
        });
    }

    /**
     * ✅ Generate unique account_id in format ACC-YYYYMMDD-XXXX
     * 
     * This is the ONLY way account_id should be generated.
     * Format ensures:
     * - Global uniqueness (not per-year like student_id)
     * - Date-based organization
     * - Human-readable
     * - Never conflicts
     */
    public static function generateAccountId(): string
    {
        return DB::transaction(function () {
            $date = now()->format('Ymd');
            $prefix = "ACC-{$date}-";

            // Find highest existing number for today
            $lastStudent = self::where('account_id', 'like', "{$prefix}%")
                ->lockForUpdate()
                ->orderByRaw('CAST(SUBSTRING(account_id, 14) AS UNSIGNED) DESC')
                ->first();

            if ($lastStudent) {
                $lastNumber = intval(substr($lastStudent->account_id, -4));
                $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
            } else {
                $newNumber = '0001';
            }

            $newAccountId = "{$prefix}{$newNumber}";

            // Safety: ensure uniqueness
            $attempts = 0;
            while (self::where('account_id', $newAccountId)->exists() && $attempts < 100) {
                $lastNumber = intval($newNumber);
                $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
                $newAccountId = "{$prefix}{$newNumber}";
                $attempts++;
            }

            if ($attempts >= 100) {
                throw new \Exception('Unable to generate unique account_id after 100 attempts');
            }

            return $newAccountId;
        });
    }

    /**
     * ✅ Validate account_id format
     */
    public static function isValidAccountId(string $accountId): bool
    {
        return (bool) preg_match('/^ACC-\d{8}-\d{4}$/', $accountId);
    }

    /**
     * ✅ Find student by account_id (primary lookup method)
     */
    public static function findByAccountId(string $accountId): ?self
    {
        return self::where('account_id', $accountId)->first();
    }

    /**
     * ✅ Get or fail by account_id
     */
    public static function findByAccountIdOrFail(string $accountId): self
    {
        return self::where('account_id', $accountId)->firstOrFail();
    }

    // ============================================
    // RELATIONSHIPS
    // ============================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * ✅ Payments using account_id
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'account_id', 'account_id');
    }

    /**
     * ✅ NEW: Transactions using account_id
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'account_id', 'account_id');
    }

    /**
     * ✅ NEW: Payment terms using account_id
     */
    public function paymentTerms(): HasMany
    {
        return $this->hasMany(StudentPaymentTerm::class, 'account_id', 'account_id');
    }

    /**
     * ✅ NEW: Assessments using account_id
     */
    public function assessments(): HasMany
    {
        return $this->hasMany(StudentAssessment::class, 'account_id', 'account_id');
    }

    /**
     * ✅ User's account (for balance)
     */
    public function account(): HasOne
    {
        return $this->hasOne(Account::class, 'user_id', 'user_id');
    }

    // ============================================
    // COMPUTED ATTRIBUTES
    // ============================================

    /**
     * ✅ Remaining balance (computed from payment terms)
     */
    public function getRemainingBalanceAttribute(): float
    {
        $totalScheduled = $this->paymentTerms()->sum('amount');
        $totalPaid = $this->paymentTerms()->sum('paid_amount');
        return max(0, $totalScheduled - $totalPaid);
    }
    // public function getRemainingBalanceAttribute()
    // {
    //     $totalPaid = $this->payments()
    //         ->where('status', Payment::STATUS_COMPLETED)
    //         ->sum('amount');
    //     return $this->total_balance - $totalPaid;
    // }

    /**
     * ✅ Total paid amount
     */
    public function getTotalPaidAttribute(): float
    {
        return $this->payments()
            ->where('status', Payment::STATUS_COMPLETED)
            ->sum('amount');
    }

    public function getPaymentProgressAttribute(): float
    {
        if ($this->total_balance <= 0) {
            return 100.0;
        }

        $totalPaid = $this->payments()
            ->where('status', Payment::STATUS_COMPLETED)
            ->sum('amount');

        return round(($totalPaid / $this->total_balance) * 100, 2);
    }

    public function getFullNameAttribute(): string
    {
        $mi = $this->middle_initial ? " {$this->middle_initial}." : '';
        return "{$this->last_name}, {$this->first_name}{$mi}";
    }

    /**
     * ✅ Display name (shortened format)
     */
    public function getNameAttribute(): string
    {
        return $this->full_name;
    }

    // ============================================
    // SCOPES
    // ============================================

    /**
     * ✅ NEW: Find by account_id
     */
    public function scopeByAccountId($query, string $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'enrolled');
    }

    public function scopeGraduated($query)
    {
        return $query->where('status', 'graduated');
    }

    public function scopeInactive($query)
    {
        return $query->where('status', 'inactive');
    }

    /**
     * ✅ Students with outstanding balance
     */
    public function scopeWithBalance($query)
    {
        return $query->where('total_balance', '>', 0);
    }

    /**
     * ✅ Students by course
     */
    public function scopeByCourse($query, string $course)
    {
        return $query->where('course', $course);
    }

    /**
     * ✅ Students by year level
     */
    public function scopeByYearLevel($query, string $yearLevel)
    {
        return $query->where('year_level', $yearLevel);
    }
    
    /**
     * ✅ Append computed attributes to JSON
     */
    protected $appends = [
        'full_name',
        'remaining_balance',
        'total_paid',
    ];
}