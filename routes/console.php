<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| DanDan Trading Firm — Scheduled Commands
|--------------------------------------------------------------------------
| Start the scheduler: php artisan schedule:work
|--------------------------------------------------------------------------
*/

// Pre-fetch Binance klines every 15 min — agents read cache, never Binance
Schedule::command('market:ingest')->everyFifteenMinutes()->withoutOverlapping();

// Pre-compute feature vectors every hour — agents read features, not raw klines
Schedule::command('features:compute --days=7')->hourly()->withoutOverlapping();

// Auto-expire timed circuit breakers every minute
Schedule::command('circuit-breakers:resolve')->everyMinute();
