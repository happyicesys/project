<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeatureStore extends Model
{
    public $timestamps = false;

    protected $table = 'feature_store';

    protected $fillable = [
        'symbol', 'interval', 'open_time', 'feature_set', 'features', 'computed_at',
    ];

    protected $casts = [
        'features'    => 'array',
        'computed_at' => 'datetime',
    ];
}
