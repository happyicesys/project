<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Alert;
use App\Models\MarketUpdate;
use App\Services\BinanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketDataController extends Controller
{
    public function __construct(
        private readonly BinanceService $binance,
    ) {}

    // ── Query-param aliases (agents call /market/price?symbol=X instead of /market-data/X/price) ──

    public function priceByQuery(Request $request): JsonResponse
    {
        $symbol = strtoupper($request->query('symbol', 'BTCUSDT'));
        return $this->price($symbol);
    }

    public function klinesByQuery(Request $request): JsonResponse
    {
        $symbol = strtoupper($request->query('symbol', 'BTCUSDT'));
        return $this->klines($request, $symbol);
    }

    public function fundingRateByQuery(Request $request): JsonResponse
    {
        $symbol = strtoupper($request->query('symbol', 'BTCUSDT'));
        return $this->fundingRate($symbol);
    }

    // ── Binance account proxy (Execution Engineer) ──────────────────────────

    /**
     * GET /api/binance/account — account balance & margin info
     */
    public function binanceAccount(): JsonResponse
    {
        $data = $this->binance->getAccountInfo();
        return $data
            ? response()->json($data)
            : response()->json(['error' => 'Failed to fetch account info'], 502);
    }

    /**
     * GET /api/binance/positions — open futures positions
     */
    public function binancePositions(): JsonResponse
    {
        $data = $this->binance->getOpenPositions();
        return $data
            ? response()->json(['positions' => $data])
            : response()->json(['error' => 'Failed to fetch positions'], 502);
    }

    // ────────────────────────────────────────────────────────────────────────

    /**
     * Proxy to Binance — get current price.
     */
    public function price(string $symbol): JsonResponse
    {
        $data = $this->binance->getPrice(strtoupper($symbol));

        return $data
            ? response()->json($data)
            : response()->json(['error' => 'Failed to fetch price'], 502);
    }

    /**
     * Proxy to Binance — get klines.
     */
    public function klines(Request $request, string $symbol): JsonResponse
    {
        $interval = $request->query('interval', '1h');
        $limit = $request->integer('limit', 500);

        $data = $this->binance->getKlines(strtoupper($symbol), $interval, $limit);

        return $data
            ? response()->json($data)
            : response()->json(['error' => 'Failed to fetch klines'], 502);
    }

    /**
     * Proxy to Binance — get funding rate.
     */
    public function fundingRate(string $symbol): JsonResponse
    {
        $data = $this->binance->getFundingRate(strtoupper($symbol));

        return $data
            ? response()->json($data)
            : response()->json(['error' => 'Failed to fetch funding rate'], 502);
    }

    /**
     * Proxy to Binance — get open interest.
     */
    public function openInterest(string $symbol): JsonResponse
    {
        $data = $this->binance->getOpenInterest(strtoupper($symbol));

        return $data
            ? response()->json($data)
            : response()->json(['error' => 'Failed to fetch open interest'], 502);
    }

    /**
     * Store a market status update from the Market Analyst.
     */
    public function storeUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'btc_price' => 'required|numeric|gt:0',
            'btc_24h_change_pct' => 'required|numeric',
            'market_regime' => 'required|string',
            'funding_rate_btc' => 'nullable|numeric',
            'total_oi_change_1h_pct' => 'nullable|numeric',
            'notable_events' => 'nullable|array',
            'risk_level' => 'required|in:LOW,MEDIUM,HIGH,EXTREME',
            'agent_id' => 'required|string|exists:agents,agent_id',
        ]);

        $update = MarketUpdate::create($validated);

        return response()->json(['market_update' => $update], 201);
    }

    /**
     * Get latest market updates.
     */
    public function latestUpdates(Request $request): JsonResponse
    {
        $updates = MarketUpdate::latest()
            ->limit($request->integer('limit', 10))
            ->get();

        return response()->json(['market_updates' => $updates]);
    }

    /**
     * Store an anomaly alert from the Market Analyst.
     */
    public function storeAlert(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string',
            'symbol' => 'nullable|string',
            'severity' => 'required|in:INFO,WARNING,CRITICAL',
            'description' => 'required|string',
            'recommended_action' => 'nullable|string',
            'agent_id' => 'required|string|exists:agents,agent_id',
        ]);

        $alert = Alert::create($validated);

        ActivityLog::record(
            $validated['agent_id'],
            'alert.created',
            'alert',
            $alert->getKey(),
            ['severity' => $alert->severity, 'type' => $alert->type],
        );

        return response()->json(['alert' => $alert], 201);
    }

    /**
     * Get unacknowledged alerts.
     */
    public function activeAlerts(Request $request): JsonResponse
    {
        $query = Alert::unacknowledged()->latest();

        if ($request->has('severity')) {
            $query->where('severity', $request->severity);
        }

        return response()->json([
            'alerts' => $query->paginate($request->integer('per_page', 20)),
        ]);
    }

    /**
     * Acknowledge an alert (Manager or CEO).
     */
    public function acknowledgeAlert(Request $request, string $uuid): JsonResponse
    {
        $alert = Alert::findOrFail($uuid);

        $alert->update([
            'acknowledged' => true,
            'acknowledged_by' => $request->input('acknowledged_by', 'manager'),
        ]);

        return response()->json(['alert' => $alert->fresh()]);
    }
}
