<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DataQualityCheck;
use Illuminate\Http\Request;

class DataQualityController extends Controller
{
    /**
     * GET /api/data-quality — Latest data quality status
     */
    public function index(Request $request)
    {
        $hours = $request->get('hours', 1);

        return response()->json([
            'overall_status' => DataQualityCheck::latestOverallStatus(),
            'recent_checks'  => DataQualityCheck::recent($hours)
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get(),
            'failing' => DataQualityCheck::recent($hours)->failing()->count(),
        ]);
    }

    /**
     * POST /api/data-quality — Store a data quality check result
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'check_domain'      => 'required|string|max:50',
            'status'            => 'required|in:PASS,WARN,FAIL',
            'overall_status'    => 'required|in:HEALTHY,DEGRADED,CRITICAL',
            'symbols_checked'   => 'nullable|integer',
            'issues_found'      => 'nullable|integer',
            'details'           => 'nullable|array',
            'api_latency_avg_ms'=> 'nullable|numeric',
            'api_latency_max_ms'=> 'nullable|numeric',
            'data_gap_count'    => 'nullable|integer',
        ]);

        $check = DataQualityCheck::create($validated);

        return response()->json($check, 201);
    }
}
