<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ResearchFinding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResearchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ResearchFinding::with('agent')->latest();

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        return response()->json([
            'research_findings' => $query->paginate($request->integer('per_page', 20)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'signal_name' => 'required|string|max:255',
            'hypothesis' => 'required|string',
            'universe' => 'required|string',
            'timeframe' => 'required|string',
            'lookback' => 'required|string',
            'edge_metric' => 'required|string',
            'edge_value' => 'required|numeric',
            'statistical_test' => 'required|string',
            'p_value' => 'required|numeric|between:0,1',
            'out_of_sample' => 'required|boolean',
            'out_of_sample_value' => 'nullable|numeric',
            'agent_id' => 'required|string|exists:agents,agent_id',
            'notes' => 'nullable|string',
        ]);

        $finding = ResearchFinding::create($validated);

        ActivityLog::record(
            $validated['agent_id'],
            'research.submitted',
            'research_finding',
            $finding->getKey(),
            ['signal_name' => $finding->signal_name],
        );

        return response()->json(['research_finding' => $finding], 201);
    }

    public function show(string $uuid): JsonResponse
    {
        $finding = ResearchFinding::with(['agent', 'backtestReports'])->findOrFail($uuid);

        return response()->json(['research_finding' => $finding]);
    }

    /**
     * Manager approves/rejects a research finding.
     */
    public function updateStatus(Request $request, string $uuid): JsonResponse
    {
        $finding = ResearchFinding::findOrFail($uuid);

        $validated = $request->validate([
            'status' => 'required|in:under_review,approved_for_backtest,rejected,promoted',
            'notes' => 'nullable|string',
        ]);

        $finding->update($validated);

        ActivityLog::record(
            'manager',
            "research.{$validated['status']}",
            'research_finding',
            $finding->getKey(),
        );

        return response()->json(['research_finding' => $finding->fresh()]);
    }
}
