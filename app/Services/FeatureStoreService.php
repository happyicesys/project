<?php

namespace App\Services;

use App\Models\FeatureStore;
use App\Models\MarketKline;
use Illuminate\Support\Collection;

/**
 * Computes and serves pre-computed feature vectors.
 * Computed ONCE per symbol/interval/period — never twice for the same row.
 * Algorithm Designer and Backtester read from here, not from raw klines.
 */
class FeatureStoreService
{
    public const FEATURE_SET_V1 = 'v1_standard';

    /**
     * Batch feature fetch for the Algorithm Designer / Backtester.
     * Returns pre-computed features, computing missing ones on the fly.
     */
    public function getBatch(
        array $symbols,
        string $interval,
        string $startDate,
        string $endDate,
        string $featureSet = self::FEATURE_SET_V1,
    ): array {
        $startMs = strtotime($startDate) * 1000;
        $endMs   = strtotime($endDate) * 1000;

        $result = [];

        foreach ($symbols as $symbol) {
            // Check what's already computed in the store
            $existing = FeatureStore::where('symbol', $symbol)
                ->where('interval', $interval)
                ->where('feature_set', $featureSet)
                ->whereBetween('open_time', [$startMs, $endMs])
                ->orderBy('open_time')
                ->get();

            // Get all kline timestamps in range
            $klineTimes = MarketKline::where('symbol', $symbol)
                ->where('interval', $interval)
                ->whereBetween('open_time', [$startMs, $endMs])
                ->pluck('open_time')
                ->toArray();

            $computedTimes = $existing->pluck('open_time')->toArray();
            $missingTimes  = array_diff($klineTimes, $computedTimes);

            // Compute missing features
            if (! empty($missingTimes)) {
                $this->computeAndStore($symbol, $interval, $missingTimes, $featureSet);
                // Re-fetch after computing
                $existing = FeatureStore::where('symbol', $symbol)
                    ->where('interval', $interval)
                    ->where('feature_set', $featureSet)
                    ->whereBetween('open_time', [$startMs, $endMs])
                    ->orderBy('open_time')
                    ->get();
            }

            $result[$symbol] = $existing->map(fn ($row) => array_merge(
                ['open_time' => $row->open_time],
                $row->features,
            ))->values()->toArray();
        }

        return $result;
    }

    /**
     * Compute and store features for a set of timestamps.
     * Requires enough preceding klines for rolling window features.
     */
    private function computeAndStore(
        string $symbol,
        string $interval,
        array $timestamps,
        string $featureSet,
    ): void {
        // Fetch raw klines with enough lookback for rolling windows (200 extra)
        $minTime = min($timestamps);
        $klines  = MarketKline::where('symbol', $symbol)
            ->where('interval', $interval)
            ->where('open_time', '<=', max($timestamps))
            ->orderBy('open_time')
            ->get();

        if ($klines->count() < 30) {
            return; // Not enough data to compute meaningful features
        }

        $rows        = $klines->toArray();
        $targetTimes = array_flip($timestamps);

        foreach ($rows as $i => $row) {
            if (! isset($targetTimes[$row['open_time']])) {
                continue;
            }

            $features = $this->computeFeatureVector($rows, $i);

            if ($features === null) {
                continue; // Insufficient lookback at this point
            }

            FeatureStore::updateOrInsert(
                [
                    'symbol'      => $symbol,
                    'interval'    => $interval,
                    'open_time'   => $row['open_time'],
                    'feature_set' => $featureSet,
                ],
                [
                    'features'    => json_encode($features),
                    'computed_at' => now(),
                ],
            );
        }
    }

    /**
     * Compute the v1_standard feature vector for a single candle.
     * All features use only past data — NO lookahead bias.
     */
    private function computeFeatureVector(array $rows, int $i): ?array
    {
        if ($i < 24) {
            return null; // Need at least 24 periods of lookback
        }

        $closes  = array_column($rows, 'close');
        $volumes = array_column($rows, 'volume');
        $highs   = array_column($rows, 'high');
        $lows    = array_column($rows, 'low');

        $close = (float) $closes[$i];

        // ─── Return features ──────────────────────────────
        $ret1h  = $this->logReturn($closes, $i, 1);
        $ret4h  = $this->logReturn($closes, $i, 4);
        $ret12h = $this->logReturn($closes, $i, 12);
        $ret24h = $this->logReturn($closes, $i, 24);
        $ret72h = $i >= 72 ? $this->logReturn($closes, $i, 72) : null;

        // ─── Volatility ───────────────────────────────────
        $vol24  = $this->rollingStd(array_slice($closes, max(0, $i - 23), 24));
        $vol72  = $i >= 72  ? $this->rollingStd(array_slice($closes, $i - 71, 72)) : null;
        $vol168 = $i >= 168 ? $this->rollingStd(array_slice($closes, $i - 167, 168)) : null;

        // ─── Volume ratio ─────────────────────────────────
        $vol30avg    = array_sum(array_slice($volumes, max(0, $i - 29), 30)) / 30;
        $volumeRatio = $vol30avg > 0 ? (float) $volumes[$i] / $vol30avg : 1.0;

        // ─── ATR ──────────────────────────────────────────
        $atr14 = $this->atr($highs, $lows, $closes, $i, 14);

        // ─── VWAP deviation (simplified: price vs 24-period typical price avg)
        $typicalPrices = [];
        for ($j = max(0, $i - 23); $j <= $i; $j++) {
            $typicalPrices[] = ((float) $highs[$j] + (float) $lows[$j] + (float) $closes[$j]) / 3;
        }
        $vwapProxy   = array_sum($typicalPrices) / count($typicalPrices);
        $vwapDeviation = $vwapProxy > 0 ? ($close - $vwapProxy) / $vwapProxy : 0;

        // ─── Time features (cyclical encoding) ────────────
        $openTimeMs = (int) $rows[$i]['open_time'];
        $hourOfDay  = (int) gmdate('G', $openTimeMs / 1000);
        $dayOfWeek  = (int) gmdate('N', $openTimeMs / 1000); // 1=Mon, 7=Sun

        $hourSin = sin(2 * M_PI * $hourOfDay / 24);
        $hourCos = cos(2 * M_PI * $hourOfDay / 24);
        $dowSin  = sin(2 * M_PI * ($dayOfWeek - 1) / 7);
        $dowCos  = cos(2 * M_PI * ($dayOfWeek - 1) / 7);

        // Session flags (UTC)
        $isAsian  = ($hourOfDay >= 0 && $hourOfDay < 8)   ? 1 : 0;
        $isEurope = ($hourOfDay >= 8 && $hourOfDay < 16)  ? 1 : 0;
        $isUs     = ($hourOfDay >= 14 && $hourOfDay < 22) ? 1 : 0;

        return array_filter([
            'ret_1'           => round($ret1h, 6),
            'ret_4'           => round($ret4h, 6),
            'ret_12'          => round($ret12h, 6),
            'ret_24'          => round($ret24h, 6),
            'ret_72'          => $ret72h !== null ? round($ret72h, 6) : null,
            'vol_24'          => round($vol24, 8),
            'vol_72'          => $vol72 !== null ? round($vol72, 8) : null,
            'vol_168'         => $vol168 !== null ? round($vol168, 8) : null,
            'volume_ratio'    => round($volumeRatio, 4),
            'atr_14'          => round($atr14, 8),
            'vwap_deviation'  => round($vwapDeviation, 6),
            'hour_sin'        => round($hourSin, 6),
            'hour_cos'        => round($hourCos, 6),
            'dow_sin'         => round($dowSin, 6),
            'dow_cos'         => round($dowCos, 6),
            'is_asian'        => $isAsian,
            'is_europe'       => $isEurope,
            'is_us'           => $isUs,
        ], fn ($v) => $v !== null);
    }

    // ─── Math helpers ──────────────────────────────────────

    private function logReturn(array $closes, int $i, int $periods): float
    {
        $prev = (float) $closes[max(0, $i - $periods)];
        $curr = (float) $closes[$i];

        return $prev > 0 ? log($curr / $prev) : 0.0;
    }

    private function rollingStd(array $values): float
    {
        if (count($values) < 2) {
            return 0.0;
        }

        $floats = array_map('floatval', $values);
        $mean   = array_sum($floats) / count($floats);
        $sqDiff = array_sum(array_map(fn ($v) => ($v - $mean) ** 2, $floats));

        return sqrt($sqDiff / (count($floats) - 1));
    }

    private function atr(array $highs, array $lows, array $closes, int $i, int $period): float
    {
        $trs = [];
        for ($j = max(1, $i - $period + 1); $j <= $i; $j++) {
            $h  = (float) $highs[$j];
            $l  = (float) $lows[$j];
            $pc = (float) $closes[$j - 1];
            $trs[] = max($h - $l, abs($h - $pc), abs($l - $pc));
        }

        return count($trs) > 0 ? array_sum($trs) / count($trs) : 0.0;
    }
}
