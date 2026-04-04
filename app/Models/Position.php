<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Position extends Model
{
    use HasUuids;

    protected $primaryKey = 'uuid';

    protected $fillable = [
        'signal_uuid', 'symbol', 'direction', 'entry_price', 'quantity',
        'stop_loss', 'take_profit', 'risk_pct', 'current_price',
        'unrealised_pnl', 'unrealised_pnl_pct', 'status',
        'exit_price', 'realised_pnl', 'close_reason', 'opened_at', 'closed_at',
    ];

    protected $casts = [
        'entry_price'         => 'decimal:8',
        'quantity'            => 'decimal:8',
        'stop_loss'           => 'decimal:8',
        'take_profit'         => 'decimal:8',
        'risk_pct'            => 'decimal:2',
        'current_price'       => 'decimal:8',
        'unrealised_pnl'      => 'decimal:8',
        'unrealised_pnl_pct'  => 'decimal:4',
        'exit_price'          => 'decimal:8',
        'realised_pnl'        => 'decimal:8',
        'opened_at'           => 'datetime',
        'closed_at'           => 'datetime',
    ];

    public function signal(): BelongsTo
    {
        return $this->belongsTo(TradeSignal::class, 'signal_uuid', 'uuid');
    }
}
