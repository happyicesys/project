<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\CircuitBreaker;
use App\Models\Position;
use App\Models\PortfolioState;
use App\Models\StrategyPerformance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortfolioController extends Controller
{
    /**
     * Full portfolio state snapshot — the single source of truth.
     * Portfolio Manager writes here. All other agents read from here.
     * One call, complete picture.
     */
    public function state(): JsonResponse
    {
        $latest    = PortfolioState::current();
        $positions = Position::where('status', 'open')->get([
            'uuid', 'symbol', 'direction', 'entry_price', 'current_price',
            'unrealised_pnl', 'unrealised_pnl_pct', 'risk_pct',
        ]);
        $circuitBreakers = CircuitBreaker::where('active', true)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->get(['type', 'action', 'reason', 'expires_at']);

        return response()->json([
            'snapshot'        => $latest,
            'open_positions'  => $positions,
            'circuit_breakers'=> $circuitBreakers,
            'is_halted'       => CircuitBreaker::isHalted(),
            'timestamp'       => now()->toIso8601String(),
        ]);
    }

    /**
     * Exposure summary — quick check used by Risk Officer every 5 min.
     */
    public function exposure(): JsonResponse
    {
        $openPositions = Position::where('status', 'open')->get();

        return response()->json([
            'open_count'     => $openPositions->count(),
            'symbols'        => $openPositions->pluck('symbol')->unique()->values(),
            'is_halted'      => CircuitBreaker::isHalted(),
        ]);
    }

    /**
     * Trigger a circuit breaker — Risk Officer and Market Analyst call this autonomously.
     */
    public function triggerCircuitBreaker(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type'             => 'required|string',
            'action'           => 'required|in:halt_new_trades,pause_new_signals,reduce_sizes',
            'reason'           => 'required|string',
            'triggered_by'     => 'required|string',
            'duration_minutes' => 'nullable|integer|min:1|max:1440',
        ]);

        $breaker = CircuitBreaker::create([
            'type'         => $validated['type'],
            'action'       => $validated['action'],
            'reason'       => $validated['reason'],
            'triggered_by' => $validated['triggered_by'],
            'active'       => true,
            'expires_at'   => isset($validated['duration_minutes'])
                ? now()->addMinutes($validated['duration_minutes'])
                : null,
        ]);

        ActivityLog::record(
            $validated['triggered_by'],
            'circuit_breaker.triggered',
            'circuit_breaker',
            (string) $breaker->id,
            ['action' => $validated['action'], 'reason' => $validated['reason']],
        );

        return response()->json(['circuit_breaker' => $breaker], 201);
    }

    /**
     * Manually resolve a circuit breaker (Manager or CEO only).
     */
    public function resolveCircuitBreaker(Request $request, int $id): JsonResponse
    {
        $breaker = CircuitBreaker::findOrFail($id);
        $breaker->update([
            'active'       => false,
            'resolved_at'  => now(),
            'resolved_by'  => $request->input('resolved_by', 'manager'),
        ]);

        return response()->json(['circuit_breaker' => $breaker->fresh()]);
    }

    /**
     * Portfolio Manager updates strategy performance tracking.
     */
    public function updateStrategyPerformance(Request $request, string $uuid): JsonResponse
    {
        $perf = StrategyPerformance::findOrFail($uuid);

        $validated = $request->validate([
            'tier'              => 'nullable|integer|between:0,4',
            'allocation_pct'    => 'nullable|numeric|between:0,20',
            'live_sharpe_7d'    => 'nullable|numeric',
            'live_sharpe_30d'   => 'nullable|numeric',
            'total_pnl_pct'     => 'nullable|numeric',
            'max_drawdown_live_pct' => 'nullable|numeric',
            'win_rate_live'     => 'nullable|numeric|between:0,100',
            'total_live_trades' => 'nullable|integer',
            'status'            => 'nullable|in:paper,live,paused,retired',
        ]);

        $validated['last_reviewed_at'] = now();
        $perf->update($validated);

        return response()->json(['strategy_performance' => $perf->fresh()]);
    }

    /**
     * List all strategy performances — Portfolio Manager's main read.
     */
    public function strategies(Request $request): JsonResponse
    {
        $query = StrategyPerformance::query();

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        return response()->json([
            'strategies' => $query->orderByDesc('tier')->get(),
        ]);
    }
}
