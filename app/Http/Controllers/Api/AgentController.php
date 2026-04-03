<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentController extends Controller
{
    /**
     * List all registered agents and their status.
     */
    public function index(): JsonResponse
    {
        $agents = Agent::all()->map(fn (Agent $agent) => [
            'agent_id' => $agent->agent_id,
            'name' => $agent->name,
            'role' => $agent->role,
            'status' => $agent->status,
            'is_online' => $agent->isOnline(),
            'last_heartbeat_at' => $agent->last_heartbeat_at?->toIso8601String(),
            'capabilities' => $agent->capabilities,
        ]);

        return response()->json(['agents' => $agents]);
    }

    /**
     * Get a single agent's details.
     */
    public function show(string $agentId): JsonResponse
    {
        $agent = Agent::findOrFail($agentId);

        return response()->json([
            'agent' => $agent->makeHidden('api_token'),
        ]);
    }

    /**
     * Agent heartbeat — called periodically to signal "I'm alive".
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $agent = $request->authenticated_agent;

        $agent->update([
            'status' => 'online',
            'last_heartbeat_at' => now(),
        ]);

        return response()->json([
            'status' => 'ok',
            'agent_id' => $agent->agent_id,
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
