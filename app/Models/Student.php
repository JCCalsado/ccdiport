<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    protected $fillable = [
        'account_id',
        'student_number',
        'student_id',
        'first_name',
        'middle_name',
        'middle_initial',
        'last_name',
        'email',
        'course',
        'year_level',
        'semester',
        'status',
    ];

    /**
     * Get the account that owns the student
     * ✅ CORRECT: Use account_id
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /**
     * Get the user through the account
     * ✅ CORRECT: Use hasOneThrough
     */
    public function user(): BelongsTo
    {
        // Students link to Users through Accounts
        // students.account_id -> accounts.id -> accounts.user_id -> users.id
        return $this->hasOneThrough(
            User::class,           // Final model
            Account::class,        // Intermediate model
            'id',                  // Foreign key on accounts table
            'id',                  // Foreign key on users table
            'account_id',          // Local key on students table
            'user_id'              // Local key on accounts table
        );
    }

    /**
     * Get payment terms for this student
     */
    public function paymentTerms(): HasMany
    {
        return $this->hasMany(StudentPaymentTerm::class, 'account_id', 'account_id');
    }

    /**
     * Get assessments for this student
     */
    public function assessments(): HasMany
    {
        return $this->hasMany(StudentAssessment::class, 'account_id', 'account_id');
    }

    /**
     * Get payments for this student
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'account_id', 'account_id');
    }

    /**
     * Get transactions for this student
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'account_id', 'account_id');
    }

    /**
     * Get full name attribute
     */
    public function getFullNameAttribute(): string
    {
        $middle = $this->middle_initial ?? $this->middle_name ?? '';
        return trim("{$this->first_name} {$middle} {$this->last_name}");
    }
}