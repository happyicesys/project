<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModelRegistry extends Model
{
    use HasUuids;

    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'model_registry';

    protected $fillable = [
        'research_finding_id', 'strategy_name', 'model_type', 'feature_set',
        'feature_list', 'hyperparameters', 'in_sample_sharpe', 'out_of_sample_sharpe',
        'directional_accuracy', 'shap_summary', 'fragility_flags', 'artifact_path',
        'status', 'used_in_signal', 'agent_id',
    ];

    protected $casts = [
        'feature_list'        => 'array',
        'hyperparameters'     => 'array',
        'shap_summary'        => 'array',
        'fragility_flags'     => 'array',
        'in_sample_sharpe'    => 'decimal:4',
        'out_of_sample_sharpe'=> 'decimal:4',
        'directional_accuracy'=> 'decimal:2',
        'used_in_signal'      => 'boolean',
    ];

    public function researchFinding(): BelongsTo
    {
        return $this->belongsTo(ResearchFinding::class, 'research_finding_id', 'uuid');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'agent_id');
    }
}
