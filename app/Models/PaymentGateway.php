<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentGateway extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'logo_url',
        'description',
        'is_active',
        'supported_methods',
        'fees',
        'config',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'supported_methods' => 'array',
        'fees' => 'array',
        'config' => 'array',
    ];

    /**
     * Get transactions using this gateway
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'payment_channel', 'slug');
    }

    /**
     * Scope for active gateways
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    /**
     * Check if gateway supports a payment method
     */
    public function supportsMethod(string $method): bool
    {
        return in_array($method, $this->supported_methods ?? []);
    }

    /**
     * Calculate fee for an amount
     */
    public function calculateFee(float $amount): float
    {
        $fees = $this->fees ?? [];
        
        $percentage = $fees['percentage'] ?? 0;
        $fixed = $fees['fixed'] ?? 0;
        
        return ($amount * ($percentage / 100)) + $fixed;
    }

    /**
     * Get gateway configuration
     */
    public function getConfigValue(string $key, $default = null)
    {
        return data_get($this->config, $key, $default);
    }
}