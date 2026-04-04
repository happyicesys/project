<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends Model
{
    use HasUuids;

    protected $primaryKey = 'uuid';

    protected $fillable = [
        'type',
        'symbol',
        'severity',
        'description',
        'recommended_action',
        'acknowledged',
        'acknowledged_by',
        'agent_id',
    ];

    protected $casts = [
        'acknowledged' => 'boolean',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'agent_id');
    }

    public function scopeUnacknowledged($query)
    {
        return $query->where('acknowledged', false);
    }

    public function scopeCritical($query)
    {
        return $query->where('severity', 'CRITICAL');
    }
}
