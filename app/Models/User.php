<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    const STATUS_ACTIVE = 'active';
    const STATUS_GRADUATED = 'graduated';
    const STATUS_DROPPED = 'dropped';

    protected $fillable = [
        'last_name',
        'first_name',
        'middle_initial',
        'email',
        'password',
        'birthday',
        'address',
        'phone',
        'student_id',
        'profile_picture',
        'course',
        'year_level',
        'faculty',
        'status',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = ['name'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'birthday' => 'date',
        ];
    }

    // ============================================
    // RELATIONSHIPS
    // ============================================

    public function student(): HasOne
    {
        return $this->hasOne(Student::class, 'user_id');
    }

    public function account(): HasOne
    {
        return $this->hasOne(Account::class);
    }

    /**
     * ⚠️ DEPRECATED: Use student->transactions() instead
     * Kept for backward compatibility only
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'user_id');
    }

    // ============================================
    // ACCOUNT_ID ACCESSORS (Via Student)
    // ============================================

    /**
     * ✅ Get account_id through student relationship
     */
    public function getAccountIdAttribute(): ?string
    {
        return $this->student?->account_id;
    }

    /**
     * ✅ Get payment terms through student relationship
     */
    public function getPaymentTermsAttribute()
    {
        if (!$this->student) {
            return collect([]);
        }

        return StudentPaymentTerm::where('account_id', $this->student->account_id)->get();
    }

    /**
     * ✅ Get assessments through student relationship
     */
    public function getAssessmentsAttribute()
    {
        if (!$this->student) {
            return collect([]);
        }

        return StudentAssessment::where('account_id', $this->student->account_id)->get();
    }

    /**
     * ✅ Get active assessment
     */
    public function getActiveAssessmentAttribute()
    {
        if (!$this->student) {
            return null;
        }

        return StudentAssessment::where('account_id', $this->student->account_id)
            ->where('status', 'active')
            ->latest()
            ->first();
    }

    // ============================================
    // NAME ACCESSORS
    // ============================================

    /**
     * Get the user's full name
     */
    public function getNameAttribute(): string
    {
        $mi = $this->middle_initial ? ' ' . strtoupper($this->middle_initial) . '.' : '';
        return "{$this->last_name}, {$this->first_name}{$mi}";
    }

    /**
     * Get the user's full name (alternative format)
     */
    public function getFullNameAttribute(): string
    {
        $mi = $this->middle_initial ? "{$this->middle_initial}." : '';
        return "{$this->last_name}, {$this->first_name} {$mi}";
    }

    // ============================================
    // ROLE HELPERS
    // ============================================

    /**
     * Get role value (handles both string and enum)
     */
    public function getRoleValueAttribute(): string
    {
        return is_object($this->role) ? $this->role->value : (string) $this->role;
    }

    /**
     * Check if user has specific role
     */
    public function hasRole(string|array $roles): bool
    {
        if (is_array($roles)) {
            return in_array($this->role_value, $roles);
        }
        return $this->role_value === $roles;
    }

    /**
     * Check if user is a student
     */
    public function isStudent(): bool
    {
        return $this->role_value === 'student';
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->role_value === 'admin';
    }

    /**
     * Check if user is accounting staff
     */
    public function isAccounting(): bool
    {
        return $this->role_value === 'accounting';
    }
}