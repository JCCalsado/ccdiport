<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    /**
     * FIXED: Removed account_id (doesn't exist in payments table)
     */
    protected $fillable = [
        'student_id',         // ✅ EXISTS
        'amount',             // ✅ EXISTS
        'description',        // ✅ EXISTS
        'payment_method',     // ✅ EXISTS
        'reference_number',   // ✅ EXISTS
        'status',             // ✅ EXISTS
        'paid_at',            // ✅ EXISTS
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    /**
     * Get the student that owns the payment.
     */
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the student via account (for backward compatibility).
     */
    public function studentByAccount()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    /**
     * Get the official receipt for this payment.
     */
    public function officialReceipt()
    {
        return $this->hasOne(OfficialReceipt::class);
    }
}