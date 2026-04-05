<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Alert;
use App\Models\MarketUpdate;
use App\Services\BinanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

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
     * Cached 15 s so 11 agents hitting the same symbol don't all fan out to Binance.
     */
    public function price(string $symbol): JsonResponse
    {
        $sym  = strtoupper($symbol);
        $data = Cache::remember("binance_price_{$sym}", 15, fn () => $this->binance->getPrice($sym));

        return $data
            ? response()->json($data)
            : response()->json(['error' => 'Failed to fetch price'], 502);
    }

    /**
     * Proxy to Binance — get klines.
     * Cached 60 s — klines at 1h resolution don't change meaningfully faster.
     */
    public function klines(Request $request, string $symbol): JsonResponse
    {
        $sym      = strtoupper($symbol);
        $interval = $request->query('interval', '1h');
        $limit    = $request->integer('limit', 500);
        $key      = "binance_klines_{$sym}_{$interval}_{$limit}";

        // Large requests (backtest-sized, limit > 200) cached 5 min — they change very slowly
        // Small requests (real-time monitoring) cached 60 s
        $ttl = $limit > 200 ? 300 : 60;

        $data = Cache::remember($key, $ttl, fn () => $this->binance->getKlines($sym, $interval, $limit));

        return $data
            ? response()->json($data)
            : response()->json(['error' => 'Failed to fetch klines'], 502);
    }

    /**
     * Proxy to Binance — get funding rate.
     * Cached 5 min — funding rates update every 8 h on Binance.
     */
    public function fundingRate(string $symbol): JsonResponse
    {
        $sym  = strtoupper($symbol);
        $data = Cache::remember("binance_funding_{$sym}", 300, fn () => $this->binance->getFundingRate($sym));

        return $data
            ? response()->json($data)
            : response()->json(['error' => 'Failed to fetch funding rate'], 502);
    }

    /**
     * Proxy to Binance — get open interest.
     * Cached 5 min.
     */
    public function openInterest(string $symbol): JsonResponse
    {
        $sym  = strtoupper($symbol);
        $data = Cache::remember("binance_oi_{$sym}", 300, fn () => $this->binance->getOpenInterest($sym));

        return $data
            ? response()->json($data)
            : response()->json(['error' => 'Failed to fetch open interest'], 502);
    }

    /**
     * Proxy to Alternative.me — Fear & Greed Index.
     * Cached 10 min (index updates once per day, no need to hammer it).
     */
    public function fearGreed(): JsonResponse
    {
        $data = Cache::remember('fear_greed_index', 600, function () {
            try {
                $response = Http::timeout(10)->get('https://api.alternative.me/fng/?limit=3&format=json');
                if ($response->successful()) {
                    return $response->json();
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning("Fear & Greed fetch failed: " . $e->getMessage());
            }
            return null;
        });

        return $data
            ? response()->json($data)
            : response()->json(['error' => 'Failed to fetch Fear & Greed index'], 502);
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
