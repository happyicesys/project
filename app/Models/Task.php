<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    use HasUuids;

    protected $primaryKey = 'uuid';

    protected $fillable = [
        'title',
        'description',
        'assigned_to',
        'created_by',
        'priority',
        'status',
        'payload',
        'result',
        'started_at',
        'completed_at',
        'deadline_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'result' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'deadline_at' => 'datetime',
    ];

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'assigned_to', 'agent_id');
    }
}
