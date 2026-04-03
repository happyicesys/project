<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTradeSignalRequest;
use App\Models\ActivityLog;
use App\Models\TradeSignal;
use App\Services\RiskManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    /**
     * Receive a new trade signal from the Signal Engineer.
     * Automatically runs through the risk pipeline.
     */
    public function receiveSignal(
        StoreTradeSignalRequest $request,
        RiskManagementService $riskService,
    ): JsonResponse {
        $signal = TradeSignal::create([
            ...$request->validated(),
            'status' => 'PENDING',
        ]);

        ActivityLog::record(
            $signal->agent_id,
            'signal.submitted',
            'trade_signal',
            $signal->getKey(),
            ['symbol' => $signal->symbol, 'direction' => $signal->direction],
        );

        $approved = $riskService->evaluateSignal($signal);

        if (! $approved) {
            return response()->json([
                'status' => 'REJECTED_BY_RISK',
                'uuid' => $signal->getKey(),
                'rejection_reason' => $signal->rejection_reason,
            ], 422);
        }

        $signal->update(['status' => 'APPROVED']);

        ActivityLog::record(
            'risk-officer',
            'signal.approved',
            'trade_signal',
            $signal->getKey(),
        );

        return response()->json([
            'status' => 'APPROVED',
            'uuid' => $signal->getKey(),
            'symbol' => $signal->symbol,
        ], 201);
    }

    /**
     * List trade signals, optionally filtered by status.
     */
    public function listSignals(Request $request): JsonResponse
    {
        $query = TradeSignal::latest();

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        return response()->json([
            'signals' => $query->paginate($request->integer('per_page', 20)),
        ]);
    }
}
