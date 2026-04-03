<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OnchainMetric;
use App\Models\SentimentScore;
use Illuminate\Http\Request;

class OnchainController extends Controller
{
    /**
     * GET /api/market/onchain — Fetch on-chain metrics (batch)
     */
    public function metrics(Request $request)
    {
        $symbol = $request->get('symbol', 'BTCUSDT');
        $hours  = $request->get('hours', 24);
        $type   = $request->get('type'); // optional filter

        $query = OnchainMetric::forSymbol($symbol)->recent($hours);

        if ($type) {
            $query->ofType($type);
        }

        return response()->json([
            'symbol'  => $symbol,
            'hours'   => $hours,
            'metrics' => $query->orderBy('measured_at', 'desc')->limit(200)->get(),
        ]);
    }

    /**
     * POST /api/market/onchain — Store on-chain metric(s)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'metrics'              => 'required|array|min:1',
            'metrics.*.symbol'     => 'required|string|max:20',
            'metrics.*.metric_type'=> 'required|string|max:50',
            'metrics.*.value'      => 'required|numeric',
            'metrics.*.metadata'   => 'nullable|array',
            'metrics.*.measured_at'=> 'required|date',
        ]);

        $created = [];
        foreach ($validated['metrics'] as $metric) {
            $created[] = OnchainMetric::create($metric);
        }

        return response()->json(['stored' => count($created)], 201);
    }

    /**
     * GET /api/market/sentiment — Fetch latest sentiment scores
     */
    public function sentiment(Request $request)
    {
        $hours = $request->get('hours', 24);

        return response()->json([
            'latest' => SentimentScore::query()->latest('measured_at')->first(),
            'history' => SentimentScore::recent($hours)
                ->orderBy('measured_at', 'desc')
                ->limit(100)
                ->get(),
        ]);
    }

    /**
     * POST /api/market/sentiment — Store sentiment score
     */
    public function storeSentiment(Request $request)
    {
        $validated = $request->validate([
            'score'              => 'required|numeric|between:-1,1',
            'velocity'           => 'nullable|numeric',
            'fear_greed_index'   => 'nullable|integer|between:0,100',
            'dominant_narrative'  => 'nullable|string|max:255',
            'overall_bias'       => 'required|in:BULLISH,BEARISH,NEUTRAL',
            'confidence'         => 'required|numeric|between:0,1',
            'sources'            => 'nullable|array',
            'measured_at'        => 'required|date',
        ]);

        $score = SentimentScore::create($validated);

        return response()->json($score, 201);
    }
}
