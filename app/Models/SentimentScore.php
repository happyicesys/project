<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SentimentScore extends Model
{
    use HasUuids;

    protected $fillable = [
        'score', 'velocity', 'fear_greed_index', 'dominant_narrative',
        'overall_bias', 'confidence', 'sources', 'measured_at',
    ];

    protected $casts = [
        'score'       => 'decimal:3',
        'velocity'    => 'decimal:4',
        'confidence'  => 'decimal:2',
        'sources'     => 'array',
        'measured_at' => 'datetime',
    ];

    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('measured_at', '>=', now()->subHours($hours));
    }

    /**
     * Get the most recent sentiment score.
     * Named 'current' to avoid shadowing Eloquent's built-in latest() query scope.
     */
    public static function current(): ?self
    {
        return static::query()->latest('measured_at')->first();
    }
}
