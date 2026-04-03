<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketUpdate extends Model
{
    use HasUuids;

    protected $fillable = [
        'btc_price',
        'btc_24h_change_pct',
        'market_regime',
        'funding_rate_btc',
        'total_oi_change_1h_pct',
        'notable_events',
        'risk_level',
        'agent_id',
    ];

    protected $casts = [
        'btc_price' => 'decimal:2',
        'btc_24h_change_pct' => 'decimal:4',
        'funding_rate_btc' => 'decimal:6',
        'total_oi_change_1h_pct' => 'decimal:4',
        'notable_events' => 'array',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'agent_id');
    }
}
