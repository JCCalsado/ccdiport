<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    /**
     * FIXED: Fillable now matches actual database columns
     */
    protected $fillable = [
        'account_id',       // ✅ EXISTS
        'user_id',          // ✅ EXISTS
        'reference',        // ✅ EXISTS (not reference_number)
        'payment_channel',  // ✅ EXISTS
        'kind',             // ✅ EXISTS
        'year',             // ✅ EXISTS
        'semester',         // ✅ EXISTS
        'type',             // ✅ EXISTS
        'category',         // ✅ EXISTS
        'amount',           // ✅ EXISTS
        'status',           // ✅ EXISTS
        'paid_at',          // ✅ EXISTS
        'meta',             // ✅ EXISTS
        'fee_id',           // ✅ EXISTS
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'meta' => 'array',
    ];

    /**
     * Get the account that owns the transaction.
     */
    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get the user that owns the transaction.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the fee associated with the transaction.
     */
    public function fee()
    {
        return $this->belongsTo(Fee::class);
    }
}