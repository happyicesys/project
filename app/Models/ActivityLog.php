<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $fillable = [
        'actor',
        'action',
        'subject_type',
        'subject_id',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Quick factory method for logging activity.
     */
    public static function record(
        string $actor,
        string $action,
        ?string $subjectType = null,
        ?string $subjectId = null,
        ?array $metadata = null,
    ): static {
        return static::create([
            'actor' => $actor,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'metadata' => $metadata,
        ]);
    }
}
