<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\Alert;
use App\Models\BacktestReport;
use App\Models\DataQualityCheck;
use App\Models\ExecutionReport;
use App\Models\MarketUpdate;
use App\Models\ResearchFinding;
use App\Models\SentimentScore;
use App\Models\Task;
use App\Models\TradeSignal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardPageController extends Controller
{
    /**
     * Build the firm overview data array.
     * Shared by both the Inertia page load and the JSON refresh endpoint.
     */
    private function buildOverview(): array
    {
        return [
            'firm_status' => [
                'agents' => [
                    'total'  => Agent::count(),
                    'online' => Agent::where('status', 'online')
                        ->where('last_heartbeat_at', '>', now()->subMinutes(40))
                        ->count(),
                ],
                'tasks' => [
                    'pending'         => Task::where('status', 'pending')->count(),
                    'in_progress'     => Task::where('status', 'in_progress')->count(),
                    'completed_today' => Task::where('status', 'completed')
                        ->whereDate('completed_at', today())
                        ->count(),
                ],
                'signals' => [
                    'pending'  => TradeSignal::where('status', 'PENDING')->count(),
                    'approved' => TradeSignal::where('status', 'APPROVED')->count(),
                    'executed' => TradeSignal::where('status', 'EXECUTED')->count(),
                    'rejected' => TradeSignal::where('status', 'REJECTED_BY_RISK')->count(),
                ],
                'research' => [
                    'submitted'            => ResearchFinding::where('status', 'submitted')->count(),
                    'approved_for_backtest'=> ResearchFinding::where('status', 'approved_for_backtest')->count(),
                    'promoted'             => ResearchFinding::where('status', 'promoted')->count(),
                ],
                'backtests' => [
                    'pass' => BacktestReport::where('verdict', 'PASS')->count(),
                    'fail' => BacktestReport::where('verdict', 'FAIL')->count(),
                ],
                'executions_today' => ExecutionReport::whereDate('created_at', today())->count(),
                'alerts' => [
                    'unacknowledged' => Alert::unacknowledged()->count(),
                    'critical'       => Alert::unacknowledged()->critical()->count(),
                ],
                'latest_market' => MarketUpdate::latest()->first(),
                'data_quality'  => [
                    'status'        => DataQualityCheck::latestOverallStatus(),
                    'failing_checks'=> DataQualityCheck::recent(1)->failing()->count(),
                ],
                'sentiment' => SentimentScore::current(),
            ],
            'agents'    => Agent::orderBy('name')->get()->map(fn (Agent $a) => [
                'agent_id'          => $a->agent_id,
                'name'              => $a->name,
                'role'              => $a->role,
                'status'            => $a->status,
                'is_online'         => $a->isOnline(),
                'last_heartbeat_at' => $a->last_heartbeat_at?->toIso8601String(),
                'capabilities'      => $a->capabilities,
            ])->values()->all(),
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Render the Inertia dashboard page with initial server-side data.
     */
    public function show(Request $request): Response
    {
        return Inertia::render('dashboard', $this->buildOverview());
    }

    /**
     * JSON refresh endpoint for the client-side auto-refresh.
     * Protected by session auth (web middleware), no Sanctum needed.
     */
    public function overview(Request $request): JsonResponse
    {
        return response()->json($this->buildOverview());
    }
}
