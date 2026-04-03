<?php

namespace App\Logging;

use App\Models\SystemLog;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

/**
 * Monolog handler that writes log records to the system_logs DB table.
 *
 * Registered as a custom Monolog channel in config/logging.php.
 * Used in a stack alongside the daily file handler so every log entry
 * goes to both the file (for tail -f) and the DB (for querying/dashboard).
 */
class DatabaseLogHandler extends AbstractProcessingHandler
{
    public function __construct(Level $level = Level::Debug, bool $bubble = true)
    {
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        try {
            SystemLog::create([
                'channel'    => $record->channel,
                'level'      => strtolower($record->level->name),
                'event'      => SystemLog::parseEvent($record->message),
                'message'    => $record->message,
                'context'    => $record->context ?: null,
                'created_at' => $record->datetime,
            ]);
        } catch (\Throwable) {
            // Never let a logging failure crash the application.
            // If the DB is unavailable the file log still works.
        }
    }
}
