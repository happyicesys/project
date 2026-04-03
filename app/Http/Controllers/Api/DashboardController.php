<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Alert;
use App\Models\BacktestReport;
use App\Models\ExecutionReport;
use App\Models\MarketUpdate;
use App\Models\ResearchFinding;
use App\Models\Task;
use App\Models\DataQualityCheck;
use App\Models\SentimentScore;
use App\Models\TradeSignal;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    /**
     * Firm-wide dashboard for the Manager (Claude) and CEO (Brian).
     */
    public function overview(): JsonResponse
    {
        return response()->json([
            'firm_status' => [
                'agents' => [
                    'total' => Agent::count(),
                    'online' => Agent::where('status', 'online')
                        ->where('last_heartbeat_at', '>', now()->subMinutes(5))
                        ->count(),
                ],
                'tasks' => [
                    'pending' => Task::where('status', 'pending')->count(),
                    'in_progress' => Task::where('status', 'in_progress')->count(),
                    'completed_today' => Task::where('status', 'completed')
                        ->whereDate('completed_at', today())
                        ->count(),
                ],
                'signals' => [
                    'pending' => TradeSignal::where('status', 'PENDING')->count(),
                    'approved' => TradeSignal::where('status', 'APPROVED')->count(),
                    'executed' => TradeSignal::where('status', 'EXECUTED')->count(),
                    'rejected' => TradeSignal::where('status', 'REJECTED_BY_RISK')->count(),
                ],
                'research' => [
                    'submitted' => ResearchFinding::where('status', 'submitted')->count(),
                    'approved_for_backtest' => ResearchFinding::where('status', 'approved_for_backtest')->count(),
                    'promoted' => ResearchFinding::where('status', 'promoted')->count(),
                ],
                'backtests' => [
                    'pass' => BacktestReport::where('verdict', 'PASS')->count(),
                    'fail' => BacktestReport::where('verdict', 'FAIL')->count(),
                ],
                'executions_today' => ExecutionReport::whereDate('created_at', today())->count(),
                'alerts' => [
                    'unacknowledged' => Alert::unacknowledged()->count(),
                    'critical' => Alert::unacknowledged()->critical()->count(),
                ],
                'latest_market' => MarketUpdate::latest()->first(),
                'data_quality' => [
                    'status' => DataQualityCheck::latestOverallStatus(),
                    'failing_checks' => DataQualityCheck::recent(1)->failing()->count(),
                ],
                'sentiment' => SentimentScore::current(),
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
