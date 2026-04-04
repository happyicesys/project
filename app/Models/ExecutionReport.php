<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExecutionReport extends Model
{
    use HasUuids;

    protected $primaryKey = 'uuid';

    protected $fillable = [
        'signal_uuid',
        'symbol',
        'direction',
        'status',
        'entry_order_id',
        'fill_price',
        'fill_quantity',
        'slippage_bps',
        'stop_loss_order_id',
        'take_profit_order_id',
        'fees_paid',
        'error',
        'agent_id',
    ];

    protected $casts = [
        'fill_price' => 'decimal:5',
        'fill_quantity' => 'decimal:8',
        'slippage_bps' => 'decimal:2',
        'fees_paid' => 'decimal:8',
    ];

    public function signal(): BelongsTo
    {
        return $this->belongsTo(TradeSignal::class, 'signal_uuid', 'uuid');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'agent_id');
    }
}
