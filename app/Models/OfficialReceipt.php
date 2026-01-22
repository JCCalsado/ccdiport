<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfficialReceipt extends Model
{
    protected $fillable = [
        'account_id',
        'payment_id',
        'receipt_number',
        'amount',
        'issued_at',
        'issued_by',
    ];

    public static function generateReceiptNumber(): string
    {
        $year = now()->year;
        $lastReceipt = self::where('receipt_number', 'like', "OR-{$year}-%")
            ->orderByDesc('id')
            ->first();

        if ($lastReceipt) {
            $lastNumber = intval(substr($lastReceipt->receipt_number, -6));
            $newNumber = str_pad($lastNumber + 1, 6, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '000001';
        }

        return "OR-{$year}-{$newNumber}";
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}