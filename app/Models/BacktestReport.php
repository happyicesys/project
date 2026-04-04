<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BacktestReport extends Model
{
    use HasUuids;

    protected $primaryKey = 'uuid';

    protected $fillable = [
        'research_finding_id',
        'strategy_name',
        'period_start',
        'period_end',
        'in_sample_sharpe',
        'out_of_sample_sharpe',
        'total_return_pct',
        'max_drawdown_pct',
        'max_drawdown_recovery_days',
        'win_rate',
        'profit_factor',
        'total_trades',
        'avg_trade_duration_hours',
        'monte_carlo_95th_drawdown',
        'regime_performance',
        'verdict',
        'notes',
        'agent_id',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'in_sample_sharpe' => 'decimal:4',
        'out_of_sample_sharpe' => 'decimal:4',
        'total_return_pct' => 'decimal:4',
        'max_drawdown_pct' => 'decimal:4',
        'win_rate' => 'decimal:2',
        'profit_factor' => 'decimal:4',
        'avg_trade_duration_hours' => 'decimal:2',
        'monte_carlo_95th_drawdown' => 'decimal:4',
        'regime_performance' => 'array',
    ];

    public function researchFinding(): BelongsTo
    {
        return $this->belongsTo(ResearchFinding::class, 'research_finding_id', 'uuid');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'agent_id');
    }
}
