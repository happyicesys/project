<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FeatureStoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeatureStoreController extends Controller
{
    public function __construct(
        private readonly FeatureStoreService $featureStore,
    ) {}

    /**
     * Batch feature fetch — the primary endpoint for Algorithm Designer and Backtester.
     * Returns pre-computed features; computes missing ones on-the-fly.
     * One call returns everything — no follow-up calls needed.
     */
    public function batch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'symbols'     => 'required|array|min:1|max:20',
            'symbols.*'   => 'required|string|max:20',
            'interval'    => 'required|string|in:15m,1h,4h,1d',
            'start'       => 'required|date',
            'end'         => 'required|date|after:start',
            'feature_set' => 'nullable|string|in:v1_standard',
        ]);

        $data = $this->featureStore->getBatch(
            $validated['symbols'],
            $validated['interval'],
            $validated['start'],
            $validated['end'],
            $validated['feature_set'] ?? FeatureStoreService::FEATURE_SET_V1,
        );

        $counts = array_map('count', $data);

        return response()->json([
            'feature_set' => $validated['feature_set'] ?? FeatureStoreService::FEATURE_SET_V1,
            'interval'    => $validated['interval'],
            'row_counts'  => $counts,
            'data'        => $data,
        ]);
    }
}
