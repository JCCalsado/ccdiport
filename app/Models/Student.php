<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'student_id',
        'last_name',
        'first_name',
        'middle_initial',
        'email',
        'course',
        'year_level',
        'status',
        'birthday',
        'phone',
        'address',
        'total_balance',
        'account_id',
    ];

    protected $casts = [
        'birthday' => 'date',
        'total_balance' => 'decimal:2',
    ];

    /**
     * Get the user that owns the student.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the account associated with the student.
     */
    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id', 'id');
    }

    /**
     * Get the payments for the student.
     */
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get the student assessments.
     */
    public function assessments()
    {
        return $this->hasMany(StudentAssessment::class);
    }

    /**
     * Get the transactions for the student.
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get the full name attribute.
     */
    public function getFullNameAttribute()
    {
        return trim("{$this->first_name} {$this->middle_initial} {$this->last_name}");
    }
}