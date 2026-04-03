<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Queryable access to the system_logs table.
 *
 * GET /api/logs           — paginated log entries with filters
 * GET /api/logs/summary   — counts grouped by channel + level (last 60 min)
 * GET /api/logs/errors    — latest critical + error entries (quick health check)
 */
class LogController extends Controller
{
    /**
     * Paginated log query.
     *
     * Query params:
     *   channel  — trading | agents | binance | perf
     *   level    — debug | info | warning | error | critical
     *   event    — e.g. TRADE_EXECUTED, SLOW_REQUEST
     *   minutes  — look-back window (default 60, max 1440)
     *   per_page — page size (default 50, max 200)
     */
    public function index(Request $request): JsonResponse
    {
        $minutes = min((int) $request->input('minutes', 60), 1440);
        $perPage = min((int) $request->input('per_page', 50), 200);

        $query = SystemLog::query()
            ->recent($minutes)
            ->orderByDesc('created_at');

        if ($channel = $request->input('channel')) {
            $query->channel($channel);
        }

        if ($level = $request->input('level')) {
            $query->level($level);
        }

        if ($event = $request->input('event')) {
            $query->event($event);
        }

        return response()->json([
            'filters' => [
                'channel' => $channel,
                'level'   => $level,
                'event'   => $event,
                'minutes' => $minutes,
            ],
            'logs' => $query->paginate($perPage),
        ]);
    }

    /**
     * Health summary: entry counts per channel + level for the last N minutes.
     * Used by the coordinator and dashboard for a fast at-a-glance check.
     *
     * GET /api/logs/summary?minutes=60
     */
    public function summary(Request $request): JsonResponse
    {
        $minutes = min((int) $request->input('minutes', 60), 1440);

        $counts = SystemLog::query()
            ->recent($minutes)
            ->selectRaw('channel, level, COUNT(*) as count')
            ->groupBy('channel', 'level')
            ->orderBy('channel')
            ->orderBy('level')
            ->get();

        // Also surface the latest critical event per channel (if any)
        $latestCriticals = SystemLog::query()
            ->recent($minutes)
            ->whereIn('level', ['critical', 'error'])
            ->selectRaw('channel, level, event, message, created_at')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return response()->json([
            'window_minutes'   => $minutes,
            'counts'           => $counts,
            'latest_errors'    => $latestCriticals,
        ]);
    }

    /**
     * Quick-access: latest critical + error entries across all channels.
     * GET /api/logs/errors?minutes=60
     */
    public function errors(Request $request): JsonResponse
    {
        $minutes = min((int) $request->input('minutes', 60), 1440);

        $entries = SystemLog::query()
            ->errors()
            ->recent($minutes)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json([
            'window_minutes' => $minutes,
            'count'          => $entries->count(),
            'entries'        => $entries,
        ]);
    }
}
