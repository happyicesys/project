<?php

namespace App\Http\Middleware;

use App\Models\Agent;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateAgent
{
    /**
     * Authenticate an OpenClaw agent via Bearer token.
     * Sets $request->agent to the authenticated Agent model.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json([
                'error' => 'Missing agent authentication token.',
                'hint' => 'Include Authorization: Bearer <agent_api_token> in your request.',
            ], 401);
        }

        $agent = Agent::where('api_token', hash('sha256', $token))->first();

        if (! $agent) {
            return response()->json([
                'error' => 'Invalid agent token.',
            ], 401);
        }

        if ($agent->status === 'maintenance') {
            return response()->json([
                'error' => 'Agent is in maintenance mode.',
                'agent_id' => $agent->agent_id,
            ], 503);
        }

        // Attach agent to the request for downstream use
        $request->merge(['authenticated_agent' => $agent]);
        $request->setUserResolver(fn () => $agent);

        // Update heartbeat
        $agent->update([
            'status' => 'online',
            'last_heartbeat_at' => now(),
        ]);

        return $next($request);
    }
}
