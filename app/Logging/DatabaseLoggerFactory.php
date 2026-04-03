<?php

namespace App\Logging;

use Monolog\Logger;

/**
 * Custom log channel factory for the 'database' driver.
 * Called by Laravel when it resolves any channel configured with
 * `'driver' => 'custom', 'via' => DatabaseLoggerFactory::class`.
 *
 * The channel name is passed in $config['name'] so each stacked
 * channel (trading, agents, binance, perf) keeps its own identity
 * in the system_logs table.
 */
class DatabaseLoggerFactory
{
    public function __invoke(array $config): Logger
    {
        $name    = $config['name'] ?? 'app';
        $handler = new DatabaseLogHandler();

        return new Logger($name, [$handler]);
    }
}
