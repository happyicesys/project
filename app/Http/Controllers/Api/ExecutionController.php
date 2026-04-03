<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ExecutionReport;
use App\Models\TradeSignal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ExecutionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ExecutionReport::with(['signal', 'agent'])->latest();

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        return response()->json([
            'execution_reports' => $query->paginate($request->integer('per_page', 20)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'signal_uuid' => 'required|uuid|exists:trade_signals,uuid',
            'symbol' => 'required|string',
            'direction' => 'required|in:LONG,SHORT',
            'status' => 'required|in:FILLED,PARTIAL,FAILED,CANCELLED',
            'entry_order_id' => 'nullable|string',
            'fill_price' => 'nullable|numeric|gt:0',
            'fill_quantity' => 'nullable|numeric|gt:0',
            'slippage_bps' => 'nullable|numeric',
            'stop_loss_order_id' => 'nullable|string',
            'take_profit_order_id' => 'nullable|string',
            'fees_paid' => 'nullable|numeric',
            'error' => 'nullable|string',
            'agent_id' => 'required|string|exists:agents,agent_id',
        ]);

        $report = ExecutionReport::create($validated);

        // Update the original signal status
        $signal = TradeSignal::find($validated['signal_uuid']);
        if ($signal) {
            $newStatus = match ($validated['status']) {
                'FILLED', 'PARTIAL' => 'EXECUTED',
                'FAILED' => 'FAILED',
                default => $signal->status,
            };
            $signal->update(['status' => $newStatus]);
        }

        ActivityLog::record(
            $validated['agent_id'],
            'execution.reported',
            'execution_report',
            $report->getKey(),
            ['signal_uuid' => $validated['signal_uuid'], 'status' => $validated['status']],
        );

        // ── Structured trade log ──────────────────────────────────────────
        $hasStopLoss = ! empty($validated['stop_loss_order_id']);
        $isFill      = in_array($validated['status'], ['FILLED', 'PARTIAL']);

        $tradeContext = [
            'report_id'           => $report->getKey(),
            'signal_uuid'         => $validated['signal_uuid'],
            'symbol'              => $validated['symbol'],
            'direction'           => $validated['direction'],
            'status'              => $validated['status'],
            'fill_price'          => $validated['fill_price'] ?? null,
            'fill_quantity'       => $validated['fill_quantity'] ?? null,
            'slippage_bps'        => $validated['slippage_bps'] ?? null,
            'fees_paid'           => $validated['fees_paid'] ?? null,
            'entry_order_id'      => $validated['entry_order_id'] ?? null,
            'stop_loss_order_id'  => $validated['stop_loss_order_id'] ?? null,
            'take_profit_order_id'=> $validated['take_profit_order_id'] ?? null,
            'stop_loss_confirmed' => $hasStopLoss,
            'agent_id'            => $validated['agent_id'],
        ];

        if ($validated['status'] === 'FAILED') {
            Log::channel('trading')->warning('TRADE_FAILED', array_merge($tradeContext, [
                'error' => $validated['error'] ?? 'unknown',
            ]));
        } elseif ($isFill && ! $hasStopLoss) {
            // This should NEVER happen — Execution Engineer AGENTS.md requires stop loss
            // before reporting EXECUTED. Log as critical so it's impossible to miss.
            Log::channel('trading')->critical('TRADE_FILLED_WITHOUT_STOP_LOSS', $tradeContext);
            Log::channel('agents')->critical('STOP_LOSS_MISSING on filled trade', [
                'report_id'  => $report->getKey(),
                'symbol'     => $validated['symbol'],
                'agent_id'   => $validated['agent_id'],
            ]);
        } else {
            Log::channel('trading')->info('TRADE_EXECUTED', $tradeContext);
        }

        return response()->json(['execution_report' => $report], 201);
    }
}
