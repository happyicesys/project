<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TradeSignal extends Model
{
    use HasUuids;

    protected $primaryKey = 'uuid';

    protected $fillable = [
        'symbol',
        'direction',
        'entry_price',
        'stop_loss',
        'take_profit',
        'risk_percentage',
        'status',
        'rejection_reason',
        'agent_id',
    ];

    protected $casts = [
        'entry_price'      => 'decimal:5',
        'stop_loss'        => 'decimal:5',
        'take_profit'      => 'decimal:5',
        'risk_percentage'  => 'decimal:2',
    ];
}
