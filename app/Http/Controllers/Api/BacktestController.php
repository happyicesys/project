<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\BacktestReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BacktestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = BacktestReport::with(['researchFinding', 'agent'])->latest();

        if ($request->has('verdict')) {
            $query->where('verdict', $request->verdict);
        }

        return response()->json([
            'backtest_reports' => $query->paginate($request->integer('per_page', 20)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'research_finding_id' => 'required|uuid|exists:research_findings,uuid',
            'strategy_name' => 'required|string|max:255',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after:period_start',
            'in_sample_sharpe' => 'required|numeric',
            'out_of_sample_sharpe' => 'required|numeric',
            'total_return_pct' => 'required|numeric',
            'max_drawdown_pct' => 'required|numeric',
            'max_drawdown_recovery_days' => 'nullable|integer',
            'win_rate' => 'required|numeric|between:0,100',
            'profit_factor' => 'required|numeric',
            'total_trades' => 'required|integer|min:1',
            'avg_trade_duration_hours' => 'nullable|numeric',
            'monte_carlo_95th_drawdown' => 'nullable|numeric',
            'regime_performance' => 'nullable|array',
            'verdict' => 'required|in:PASS,FAIL,NEEDS_MORE_DATA',
            'notes' => 'nullable|string',
            'agent_id' => 'required|string|exists:agents,agent_id',
        ]);

        $report = BacktestReport::create($validated);

        ActivityLog::record(
            $validated['agent_id'],
            'backtest.submitted',
            'backtest_report',
            $report->getKey(),
            ['verdict' => $report->verdict, 'strategy' => $report->strategy_name],
        );

        return response()->json(['backtest_report' => $report], 201);
    }
}
