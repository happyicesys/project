<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortfolioState extends Model
{
    protected $fillable = [
        'total_equity',
        'available_capital',
        'deployed_pct',
        'daily_pnl',
        'daily_pnl_pct',
        'weekly_pnl_pct',
        'monthly_pnl_pct',
        'max_drawdown_daily_pct',
        'max_drawdown_weekly_pct',
        'open_positions_count',
        'circuit_breaker_active',
        'circuit_breaker_reason',
        'strategy_allocations',
    ];

    protected $casts = [
        'total_equity'              => 'decimal:8',
        'available_capital'         => 'decimal:8',
        'deployed_pct'              => 'decimal:4',
        'daily_pnl'                 => 'decimal:8',
        'daily_pnl_pct'             => 'decimal:4',
        'weekly_pnl_pct'            => 'decimal:4',
        'monthly_pnl_pct'           => 'decimal:4',
        'max_drawdown_daily_pct'    => 'decimal:4',
        'max_drawdown_weekly_pct'   => 'decimal:4',
        'circuit_breaker_active'    => 'boolean',
        'strategy_allocations'      => 'array',
    ];

    /**
     * Always a singleton — only one row exists.
     */
    public static function current(): ?self
    {
        return static::orderByDesc('updated_at')->first();
    }

    /**
     * Upsert the single portfolio state row.
     */
    public static function updateState(array $data): self
    {
        $state = static::current() ?? new static();
        $state->fill($data);
        $state->save();
        return $state;
    }
}
