<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class DataQualityCheck extends Model
{
    use HasUuids;

    protected $fillable = [
        'check_domain', 'status', 'overall_status', 'symbols_checked',
        'issues_found', 'details', 'api_latency_avg_ms', 'api_latency_max_ms',
        'data_gap_count',
    ];

    protected $casts = [
        'details'            => 'array',
        'api_latency_avg_ms' => 'decimal:2',
        'api_latency_max_ms' => 'decimal:2',
    ];

    public function scopeRecent($query, int $hours = 1)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    public function scopeFailing($query)
    {
        return $query->whereIn('status', ['WARN', 'FAIL']);
    }

    public static function latestOverallStatus(): string
    {
        $latest = static::latest()->first();
        return $latest?->overall_status ?? 'UNKNOWN';
    }
}
