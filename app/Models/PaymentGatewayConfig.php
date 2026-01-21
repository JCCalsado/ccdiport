<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class PaymentGatewayConfig extends Model
{
    protected $fillable = [
        'gateway',
        'environment',
        'public_key',
        'secret_key',
        'webhook_secret',
        'is_enabled',
        'transaction_fee_percentage',
        'transaction_fee_fixed',
        'settings',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'transaction_fee_percentage' => 'decimal:2',
        'transaction_fee_fixed' => 'decimal:2',
        'settings' => 'array',
    ];

    protected $hidden = [
        'secret_key',
        'webhook_secret',
    ];

    // Encrypt sensitive keys
    public function setSecretKeyAttribute($value)
    {
        $this->attributes['secret_key'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getSecretKeyAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function setWebhookSecretAttribute($value)
    {
        $this->attributes['webhook_secret'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getWebhookSecretAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    public function scopeForGateway($query, string $gateway)
    {
        return $query->where('gateway', $gateway);
    }

    public function calculateFee(float $amount): float
    {
        $percentageFee = $amount * ($this->transaction_fee_percentage / 100);
        return $percentageFee + $this->transaction_fee_fixed;
    }
}