<?php

namespace App\Console\Commands;

use App\Services\DataIngestionService;
use App\Services\FeatureStoreService;
use Illuminate\Console\Command;

/**
 * Scheduled every hour — pre-computes features from cached klines.
 * Agents read from the feature store, not from raw klines.
 * Compute once, serve many.
 */
class ComputeFeatures extends Command
{
    protected $signature = 'features:compute
                            {--symbol= : Compute for a specific symbol only}
                            {--days=30 : Number of days of features to (re)compute}';

    protected $description = 'Pre-compute feature vectors from cached klines into the feature store';

    public function handle(FeatureStoreService $featureStore): int
    {
        $symbols  = $this->option('symbol')
            ? [$this->option('symbol')]
            : DataIngestionService::TRACKED_SYMBOLS;

        $days     = (int) $this->option('days');
        $startDate = now()->subDays($days)->toDateString();
        $endDate   = now()->toDateString();

        $this->info('[' . now()->toIso8601String() . "] Computing features for {$days} days, " . count($symbols) . ' symbols...');

        foreach (DataIngestionService::TRACKED_INTERVALS as $interval) {
            foreach ($symbols as $symbol) {
                try {
                    // Calling getBatch triggers computation of any missing features
                    $featureStore->getBatch([$symbol], $interval, $startDate, $endDate);
                    $this->line("  ✓ {$symbol}/{$interval}");
                } catch (\Exception $e) {
                    $this->warn("  ⚠ {$symbol}/{$interval}: {$e->getMessage()}");
                }
            }
        }

        $this->info('✓ Feature computation complete');

        return self::SUCCESS;
    }
}
