<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CircuitBreaker extends Model
{
    protected $fillable = [
        'type', 'action', 'reason', 'active',
        'triggered_by', 'expires_at', 'resolved_at', 'resolved_by',
    ];

    protected $casts = [
        'active'      => 'boolean',
        'expires_at'  => 'datetime',
        'resolved_at' => 'datetime',
    ];

    /**
     * Check if any circuit breaker is currently blocking new trades.
     */
    public static function isHalted(): bool
    {
        return static::where('active', true)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->whereIn('action', ['halt_new_trades', 'pause_new_signals'])
            ->exists();
    }

    /**
     * Auto-expire breakers that have passed their expiry time.
     */
    public static function autoResolveExpired(): int
    {
        return static::where('active', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['active' => false, 'resolved_at' => now(), 'resolved_by' => 'system']);
    }
}
