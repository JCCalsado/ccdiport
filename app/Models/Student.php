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
        'account_id',       // ✅ PRIMARY IDENTIFIER
        'student_id',
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

    protected $appends = [
        'full_name',
        'remaining_balance',
        'total_paid',
    ];

    // ============================================
    // MODEL BOOT - AUTO-GENERATE account_id
    // ============================================

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($student) {
            if (empty($student->account_id)) {
                $student->account_id = self::generateAccountId();
            }
        });

        static::updating(function ($student) {
            if ($student->isDirty('account_id') && !empty($student->getOriginal('account_id'))) {
                throw new \RuntimeException(
                    'Account ID cannot be changed once set. Original: ' . $student->getOriginal('account_id')
                );
            }
        });
    }

    // ============================================
    // ACCOUNT ID GENERATION
    // ============================================

    public static function generateAccountId(): string
    {
        return DB::transaction(function () {
            $date = now()->format('Ymd');
            $prefix = "ACC-{$date}-";

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

            // Safety check
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

    public static function isValidAccountId(string $accountId): bool
    {
        return (bool) preg_match('/^ACC-\d{8}-\d{4}$/', $accountId);
    }

    public static function findByAccountId(string $accountId): ?self
    {
        return self::where('account_id', $accountId)->first();
    }

    public static function findByAccountIdOrFail(string $accountId): self
    {
        return self::where('account_id', $accountId)->firstOrFail();
    }

    // ============================================
    // RELATIONSHIPS (ALL USE account_id)
    // ============================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'account_id', 'account_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'account_id', 'account_id');
    }

    public function paymentTerms(): HasMany
    {
        return $this->hasMany(StudentPaymentTerm::class, 'account_id', 'account_id');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(StudentAssessment::class, 'account_id', 'account_id');
    }

    public function account(): HasOne
    {
        return $this->hasOne(Account::class, 'user_id', 'user_id');
    }

    // ============================================
    // COMPUTED ATTRIBUTES
    // ============================================

    public function getRemainingBalanceAttribute(): float
    {
        $totalScheduled = $this->paymentTerms()->sum('amount');
        $totalPaid = $this->paymentTerms()->sum('paid_amount');
        return max(0, $totalScheduled - $totalPaid);
    }

    public function getTotalPaidAttribute(): float
    {
        return $this->payments()
            ->where('status', Payment::STATUS_COMPLETED)
            ->sum('amount');
    }

    public function getFullNameAttribute(): string
    {
        $mi = $this->middle_initial ? " {$this->middle_initial}." : '';
        return "{$this->last_name}, {$this->first_name}{$mi}";
    }

    public function getNameAttribute(): string
    {
        return $this->full_name;
    }

    // ============================================
    // SCOPES (ALL USE account_id)
    // ============================================

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

    public function scopeWithBalance($query)
    {
        return $query->where('total_balance', '>', 0);
    }

    public function scopeByCourse($query, string $course)
    {
        return $query->where('course', $course);
    }

    public function scopeByYearLevel($query, string $yearLevel)
    {
        return $query->where('year_level', $yearLevel);
    }
}