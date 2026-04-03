<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketKline extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'symbol', 'interval', 'open_time', 'open', 'high', 'low',
        'close', 'volume', 'close_time', 'quote_volume', 'trade_count', 'fetched_at',
    ];

    protected $casts = [
        'open'         => 'decimal:8',
        'high'         => 'decimal:8',
        'low'          => 'decimal:8',
        'close'        => 'decimal:8',
        'volume'       => 'decimal:8',
        'quote_volume' => 'decimal:8',
        'fetched_at'   => 'datetime',
    ];
}
