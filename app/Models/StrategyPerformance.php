<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StrategyPerformance extends Model
{
    use HasUuids;

    protected $primaryKey = 'uuid';

    protected $table = 'strategy_performance';

    protected $fillable = [
        'backtest_report_id', 'strategy_name', 'tier', 'allocation_pct',
        'live_sharpe_7d', 'live_sharpe_30d', 'total_pnl_pct',
        'max_drawdown_live_pct', 'win_rate_live', 'total_live_trades',
        'paper_started_at', 'live_started_at', 'last_reviewed_at', 'status',
    ];

    protected $casts = [
        'live_sharpe_7d'        => 'decimal:4',
        'live_sharpe_30d'       => 'decimal:4',
        'total_pnl_pct'         => 'decimal:4',
        'max_drawdown_live_pct' => 'decimal:4',
        'win_rate_live'         => 'decimal:2',
        'allocation_pct'        => 'decimal:2',
        'paper_started_at'      => 'date',
        'live_started_at'       => 'date',
        'last_reviewed_at'      => 'datetime',
    ];

    public function backtestReport(): BelongsTo
    {
        return $this->belongsTo(BacktestReport::class, 'backtest_report_id', 'uuid');
    }
}
