<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResearchFinding extends Model
{
    use HasUuids;

    protected $primaryKey = 'uuid';

    protected $fillable = [
        'signal_name',
        'hypothesis',
        'universe',
        'timeframe',
        'lookback',
        'edge_metric',
        'edge_value',
        'statistical_test',
        'p_value',
        'out_of_sample',
        'out_of_sample_value',
        'status',
        'agent_id',
        'notes',
    ];

    protected $casts = [
        'edge_value' => 'decimal:4',
        'p_value' => 'decimal:6',
        'out_of_sample' => 'boolean',
        'out_of_sample_value' => 'decimal:4',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'agent_id');
    }

    public function backtestReports(): HasMany
    {
        return $this->hasMany(BacktestReport::class, 'research_finding_id', 'uuid');
    }
}
