<?php

namespace App\Services;

use App\Models\MarketKline;
use Illuminate\Support\Facades\Log;

/**
 * Pre-fetches and caches market data from Binance on a schedule.
 * Agents NEVER call Binance directly — they read from this cache.
 * This is the key to token efficiency: one scheduled fetch serves all agents.
 */
class DataIngestionService
{
    // Symbols to track — top Binance USDT perps by volume
    public const TRACKED_SYMBOLS = [
        'BTCUSDT', 'ETHUSDT', 'SOLUSDT', 'BNBUSDT', 'XRPUSDT',
        'DOGEUSDT', 'ADAUSDT', 'AVAXUSDT', 'LINKUSDT', 'DOTUSDT',
        'MATICUSDT', 'NEARUSDT', 'ATOMUSDT', 'UNIUSDT', 'LTCUSDT',
    ];

    // Intervals to cache (balance freshness vs storage)
    public const TRACKED_INTERVALS = ['15m', '1h', '4h', '1d'];

    // How many candles to backfill per symbol/interval on first run
    public const BACKFILL_LIMIT = 1000;

    // How many candles to refresh on each scheduled run
    public const REFRESH_LIMIT = 50;

    public function __construct(
        private readonly BinanceService $binance,
    ) {}

    /**
     * Incremental refresh — fetches only the latest candles.
     * Called by the scheduled command every 15 minutes.
     * Fast: ~50 candles × 15 symbols × 4 intervals = 3000 rows max per run.
     */
    public function refresh(): array
    {
        $stats = ['updated' => 0, 'symbols' => 0, 'errors' => []];

        foreach (self::TRACKED_SYMBOLS as $symbol) {
            foreach (self::TRACKED_INTERVALS as $interval) {
                try {
                    $inserted = $this->fetchAndStore($symbol, $interval, self::REFRESH_LIMIT);
                    $stats['updated'] += $inserted;
                } catch (\Exception $e) {
                    $stats['errors'][] = "{$symbol}/{$interval}: {$e->getMessage()}";
                    Log::warning('DataIngestion refresh error', [
                        'symbol' => $symbol,
                        'interval' => $interval,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            $stats['symbols']++;
        }

        return $stats;
    }

    /**
     * Full historical backfill — run once on setup or when adding new symbols.
     * Called manually via artisan command.
     */
    public function backfill(string $symbol, string $interval): int
    {
        return $this->fetchAndStore($symbol, $interval, self::BACKFILL_LIMIT);
    }

    /**
     * Backfill all tracked symbols and intervals.
     */
    public function backfillAll(): array
    {
        $stats = ['inserted' => 0, 'symbols' => 0, 'errors' => []];

        foreach (self::TRACKED_SYMBOLS as $symbol) {
            foreach (self::TRACKED_INTERVALS as $interval) {
                try {
                    $inserted = $this->backfill($symbol, $interval);
                    $stats['inserted'] += $inserted;
                    Log::info("Backfilled {$symbol}/{$interval}: {$inserted} candles");
                } catch (\Exception $e) {
                    $stats['errors'][] = "{$symbol}/{$interval}: {$e->getMessage()}";
                }
            }
            $stats['symbols']++;
        }

        return $stats;
    }

    /**
     * Fetch klines from Binance and upsert into the cache.
     * Returns number of rows inserted/updated.
     */
    private function fetchAndStore(string $symbol, string $interval, int $limit): int
    {
        $data = $this->binance->getKlines($symbol, $interval, $limit);

        if (! $data || empty($data)) {
            return 0;
        }

        $inserted = 0;

        foreach ($data as $candle) {
            // Binance kline format: [open_time, open, high, low, close, volume, close_time, quote_vol, trades, ...]
            MarketKline::upsert(
                [
                    'symbol'       => $symbol,
                    'interval'     => $interval,
                    'open_time'    => $candle[0],
                    'open'         => $candle[1],
                    'high'         => $candle[2],
                    'low'          => $candle[3],
                    'close'        => $candle[4],
                    'volume'       => $candle[5],
                    'close_time'   => $candle[6],
                    'quote_volume' => $candle[7],
                    'trade_count'  => $candle[8],
                    'fetched_at'   => now(),
                ],
                ['symbol', 'interval', 'open_time'], // unique keys
                ['open', 'high', 'low', 'close', 'volume', 'close_time', 'quote_volume', 'trade_count', 'fetched_at'],
            );
            $inserted++;
        }

        return $inserted;
    }

    /**
     * Get klines from cache for a symbol/interval/period.
     * Used by the API — agents call this, not Binance.
     */
    public function getKlines(string $symbol, string $interval, int $limit = 500): array
    {
        return MarketKline::where('symbol', $symbol)
            ->where('interval', $interval)
            ->orderByDesc('open_time')
            ->limit($limit)
            ->get()
            ->sortBy('open_time')
            ->values()
            ->toArray();
    }

    /**
     * Get latest cached price for a symbol (from most recent 1h candle close).
     */
    public function getLatestPrice(string $symbol): ?string
    {
        return MarketKline::where('symbol', $symbol)
            ->where('interval', '1h')
            ->orderByDesc('open_time')
            ->value('close');
    }

    /**
     * Batch price fetch for multiple symbols — one query.
     */
    public function getBatchPrices(array $symbols): array
    {
        return MarketKline::whereIn('symbol', $symbols)
            ->where('interval', '1h')
            ->whereIn('open_time', function ($query) use ($symbols) {
                $query->selectRaw('MAX(open_time)')
                    ->from('market_klines')
                    ->where('interval', '1h')
                    ->whereIn('symbol', $symbols)
                    ->groupBy('symbol');
            })
            ->get(['symbol', 'close', 'open_time'])
            ->keyBy('symbol')
            ->map(fn ($k) => ['price' => $k->close, 'timestamp' => $k->open_time])
            ->toArray();
    }
}
