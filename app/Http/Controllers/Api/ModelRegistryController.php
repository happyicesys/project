<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ModelRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ModelRegistryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ModelRegistry::with('agent')->latest();

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('used_in_signal')) {
            $query->where('used_in_signal', (bool) $request->used_in_signal);
        }

        return response()->json([
            'models' => $query->paginate($request->integer('per_page', 20)),
        ]);
    }

    public function show(string $uuid): JsonResponse
    {
        return response()->json([
            'model' => ModelRegistry::with(['researchFinding', 'agent'])->findOrFail($uuid),
        ]);
    }

    /**
     * Algorithm Designer registers a newly trained model.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'research_finding_id'  => 'nullable|uuid|exists:research_findings,uuid',
            'strategy_name'        => 'required|string|max:255',
            'model_type'           => 'required|string',
            'feature_set'          => 'required|string',
            'feature_list'         => 'required|array',
            'hyperparameters'      => 'required|array',
            'in_sample_sharpe'     => 'required|numeric',
            'out_of_sample_sharpe' => 'required|numeric',
            'directional_accuracy' => 'nullable|numeric|between:0,100',
            'shap_summary'         => 'nullable|array',
            'fragility_flags'      => 'nullable|array',
            'artifact_path'        => 'nullable|string',
            'agent_id'             => 'required|string|exists:agents,agent_id',
        ]);

        $model = ModelRegistry::create($validated);

        ActivityLog::record(
            $validated['agent_id'],
            'model.registered',
            'model_registry',
            $model->getKey(),
            ['strategy' => $model->strategy_name, 'oos_sharpe' => $model->out_of_sample_sharpe],
        );

        return response()->json(['model' => $model], 201);
    }

    /**
     * Manager approves a model for signal engineering.
     */
    public function updateStatus(Request $request, string $uuid): JsonResponse
    {
        $model = ModelRegistry::findOrFail($uuid);

        $validated = $request->validate([
            'status' => 'required|in:candidate,approved,in_paper,live,retired',
        ]);

        $model->update($validated);

        ActivityLog::record('manager', "model.{$validated['status']}", 'model_registry', $uuid);

        return response()->json(['model' => $model->fresh()]);
    }
}
