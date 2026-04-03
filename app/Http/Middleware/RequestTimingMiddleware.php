<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs every API request with timing so you can spot slow endpoints
 * and correlate agent behaviour over time.
 *
 * Writes to the `agents` channel always.
 * Additionally writes to the `perf` channel when duration > 200 ms.
 */
class RequestTimingMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = hrtime(true); // nanoseconds

        $response = $next($request);

        $durationMs = (int) round((hrtime(true) - $startTime) / 1_000_000);

        /** @var \App\Models\Agent|null $agent */
        $agent     = $request->user();
        $agentId   = $agent?->agent_id ?? 'unauthenticated';
        $method    = $request->method();
        $path      = $request->path();
        $status    = $response->getStatusCode();

        $context = [
            'agent_id'    => $agentId,
            'method'      => $method,
            'path'        => $path,
            'status'      => $status,
            'duration_ms' => $durationMs,
            'ip'          => $request->ip(),
        ];

        // Always record to agents channel (debug level keeps noise low)
        Log::channel('agents')->debug("API {$method} /{$path} → {$status} ({$durationMs}ms)", $context);

        // Flag slow requests so you can find bottlenecks without grepping
        if ($durationMs > 200) {
            Log::channel('perf')->warning("SLOW REQUEST {$durationMs}ms — {$method} /{$path}", $context);
        }

        return $response;
    }
}
