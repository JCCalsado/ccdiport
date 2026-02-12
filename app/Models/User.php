<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name',
        'middle_name',
        'middle_initial', // Keep for backward compatibility
        'last_name',
        'email',
        'password',
        'role',
        'student_number',
        'student_id', // Keep for backward compatibility
        'course',
        'year_level',
        'semester',
        'phone',
        'birthday',
        'birth_date',
        'address',
        'status',
        'faculty',
        'profile_picture',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'birthday' => 'date',
            'birth_date' => 'date',
        ];
    }

    /**
     * Get the account associated with the user.
     */
    public function account()
    {
        return $this->hasOne(Account::class);
    }

    /**
     * Get the transactions for the user.
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get the student record for this user (if student role).
     */
    public function student()
    {
        return $this->hasOne(Student::class, 'email', 'email');
    }

    /**
     * Check if user is admin.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if user is student.
     */
    public function isStudent(): bool
    {
        return $this->role === 'student';
    }

    /**
     * Check if user is accounting staff.
     */
    public function isAccounting(): bool
    {
        return $this->role === 'accounting';
    }

    /**
     * Get full name attribute.
     */
    public function getFullNameAttribute(): string
    {
        $names = array_filter([
            $this->first_name,
            $this->middle_name ?? $this->middle_initial,
            $this->last_name,
        ]);
        
        return implode(' ', $names);
    }
    
    /**
     * Get student number (use new column, fallback to old).
     */
    public function getStudentNumberAttribute($value)
    {
        return $value ?? $this->student_id;
    }
}