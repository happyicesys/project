<?php

namespace App\Console\Commands;

use App\Services\DataIngestionService;
use Illuminate\Console\Command;

/**
 * Scheduled every 15 minutes — pre-fetches klines so agents never hit Binance directly.
 * This is the central data pump for the entire firm.
 */
class IngestMarketData extends Command
{
    protected $signature = 'market:ingest
                            {--backfill : Perform a full historical backfill instead of incremental refresh}
                            {--symbol= : Backfill a specific symbol only}
                            {--interval= : Backfill a specific interval only}';

    protected $description = 'Fetch and cache market data from Binance into the local kline store';

    public function handle(DataIngestionService $ingestion): int
    {
        if ($this->option('backfill')) {
            return $this->runBackfill($ingestion);
        }

        return $this->runRefresh($ingestion);
    }

    private function runRefresh(DataIngestionService $ingestion): int
    {
        $this->info('[' . now()->toIso8601String() . '] Starting incremental market data refresh...');

        $stats = $ingestion->refresh();

        $this->info("✓ Refreshed {$stats['symbols']} symbols, {$stats['updated']} rows upserted");

        if (! empty($stats['errors'])) {
            foreach ($stats['errors'] as $error) {
                $this->warn("  ⚠ {$error}");
            }
        }

        return self::SUCCESS;
    }

    private function runBackfill(DataIngestionService $ingestion): int
    {
        $symbol   = $this->option('symbol');
        $interval = $this->option('interval');

        if ($symbol && $interval) {
            $this->info("Backfilling {$symbol}/{$interval}...");
            $count = $ingestion->backfill($symbol, $interval);
            $this->info("✓ Inserted {$count} rows");

            return self::SUCCESS;
        }

        $this->info('Starting full historical backfill for all symbols...');
        $this->warn('This may take several minutes.');

        $bar   = $this->output->createProgressBar(
            count(DataIngestionService::TRACKED_SYMBOLS) * count(DataIngestionService::TRACKED_INTERVALS),
        );
        $stats = $ingestion->backfillAll();
        $bar->finish();

        $this->newLine();
        $this->info("✓ Backfilled {$stats['symbols']} symbols, {$stats['inserted']} total rows");

        if (! empty($stats['errors'])) {
            $this->warn(count($stats['errors']) . ' errors encountered:');
            foreach ($stats['errors'] as $error) {
                $this->warn("  ⚠ {$error}");
            }
        }

        return self::SUCCESS;
    }
}
