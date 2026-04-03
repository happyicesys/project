<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class OnchainMetric extends Model
{
    use HasUuids;

    protected $fillable = [
        'symbol', 'metric_type', 'value', 'metadata', 'measured_at',
    ];

    protected $casts = [
        'value'       => 'decimal:8',
        'metadata'    => 'array',
        'measured_at' => 'datetime',
    ];

    public function scopeForSymbol($query, string $symbol)
    {
        return $query->where('symbol', $symbol);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('metric_type', $type);
    }

    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('measured_at', '>=', now()->subHours($hours));
    }
}
