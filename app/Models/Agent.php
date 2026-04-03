<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agent extends Model
{
    protected $primaryKey = 'agent_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'agent_id',
        'name',
        'role',
        'api_token',
        'status',
        'capabilities',
        'permissions',
        'last_heartbeat_at',
    ];

    protected $hidden = [
        'api_token',
    ];

    protected $casts = [
        'capabilities' => 'array',
        'permissions' => 'array',
        'last_heartbeat_at' => 'datetime',
    ];

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assigned_to', 'agent_id');
    }

    public function tradeSignals(): HasMany
    {
        return $this->hasMany(TradeSignal::class, 'agent_id', 'agent_id');
    }

    public function researchFindings(): HasMany
    {
        return $this->hasMany(ResearchFinding::class, 'agent_id', 'agent_id');
    }

    public function isOnline(): bool
    {
        return $this->status === 'online'
            && $this->last_heartbeat_at
            && $this->last_heartbeat_at->isAfter(now()->subMinutes(5));
    }
}
