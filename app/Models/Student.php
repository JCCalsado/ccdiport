<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_number',
        'first_name',
        'middle_name',
        'last_name',
        'email',
        'phone',
        'birth_date',
        'address',
        'course',
        'year_level',
        'status',
        'account_id',
    ];

    protected $casts = [
        'birth_date' => 'date',
    ];

    /**
     * Get the account that owns the student.
     */
    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get the user associated with the student (via email).
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'email', 'email');
    }

    /**
     * Get the assessments for the student.
     * NOTE: student_assessments uses user_id, not student_id
     */
    public function assessments()
    {
        return $this->hasMany(StudentAssessment::class, 'user_id', 'email')
                    ->where('student_assessments.user_id', function($query) {
                        $query->select('id')
                              ->from('users')
                              ->whereColumn('users.email', 'students.email')
                              ->limit(1);
                    });
    }

    /**
     * Get the payment terms for the student.
     */
    public function paymentTerms()
    {
        return $this->hasMany(StudentPaymentTerm::class, 'account_id', 'account_id');
    }

    /**
     * Get the payments for the student.
     */
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get full name attribute.
     */
    public function getFullNameAttribute(): string
    {
        $names = array_filter([
            $this->first_name,
            $this->middle_name,
            $this->last_name,
        ]);
        
        return implode(' ', $names);
    }

    /**
     * Scope a query to only include active students.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}