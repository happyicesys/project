<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;
use Monolog\Processor\PsrLogMessageProcessor;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that is utilized to write
    | messages to your logs. The value provided here should match one of
    | the channels present in the list of "channels" configured below.
    |
    */

    'default' => env('LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Deprecations Log Channel
    |--------------------------------------------------------------------------
    |
    | This option controls the log channel that should be used to log warnings
    | regarding deprecated PHP and library features. This allows you to get
    | your application ready for upcoming major versions of dependencies.
    |
    */

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => env('LOG_DEPRECATIONS_TRACE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Laravel
    | utilizes the Monolog PHP logging library, which includes a variety
    | of powerful log handlers and formatters that you're free to use.
    |
    | Available drivers: "single", "daily", "slack", "syslog",
    |                    "errorlog", "monolog", "custom", "stack"
    |
    */

    'channels' => [

        'stack' => [
            'driver' => 'stack',
            'channels' => explode(',', (string) env('LOG_STACK', 'single')),
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => env('LOG_DAILY_DAYS', 14),
            'replace_placeholders' => true,
        ],

        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => env('LOG_SLACK_USERNAME', env('APP_NAME', 'Laravel')),
            'emoji' => env('LOG_SLACK_EMOJI', ':boom:'),
            'level' => env('LOG_LEVEL', 'critical'),
            'replace_placeholders' => true,
        ],

        'papertrail' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => env('LOG_PAPERTRAIL_HANDLER', SyslogUdpHandler::class),
            'handler_with' => [
                'host' => env('PAPERTRAIL_URL'),
                'port' => env('PAPERTRAIL_PORT'),
                'connectionString' => 'tls://'.env('PAPERTRAIL_URL').':'.env('PAPERTRAIL_PORT'),
            ],
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'handler_with' => [
                'stream' => 'php://stderr',
            ],
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => env('LOG_LEVEL', 'debug'),
            'facility' => env('LOG_SYSLOG_FACILITY', LOG_USER),
            'replace_placeholders' => true,
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],

        // ─── Armado Quant structured channels ────────────────────────────
        // Each channel is a stack: writes to a daily rotating file AND
        // to the system_logs DB table simultaneously.
        //
        // • File  → tail -f storage/logs/<channel>-YYYY-MM-DD.log  (server ops)
        // • DB    → query via GET /api/logs                         (dashboard / agents)

        'trading' => [
            'driver'   => 'stack',
            'channels' => ['trading_file', 'trading_db'],
            'ignore_exceptions' => false,
        ],
        'trading_file' => [
            'driver' => 'daily',
            'path'   => storage_path('logs/trading.log'),
            'level'  => 'debug',
            'days'   => 30,
            'replace_placeholders' => true,
        ],
        'trading_db' => [
            'driver' => 'custom',
            'via'    => \App\Logging\DatabaseLoggerFactory::class,
            'name'   => 'trading',
            'level'  => 'debug',
        ],

        'agents' => [
            'driver'   => 'stack',
            'channels' => ['agents_file', 'agents_db'],
            'ignore_exceptions' => false,
        ],
        'agents_file' => [
            'driver' => 'daily',
            'path'   => storage_path('logs/agents.log'),
            'level'  => 'debug',
            'days'   => 14,
            'replace_placeholders' => true,
        ],
        'agents_db' => [
            'driver' => 'custom',
            'via'    => \App\Logging\DatabaseLoggerFactory::class,
            'name'   => 'agents',
            'level'  => 'debug',
        ],

        'binance' => [
            'driver'   => 'stack',
            'channels' => ['binance_file', 'binance_db'],
            'ignore_exceptions' => false,
        ],
        'binance_file' => [
            'driver' => 'daily',
            'path'   => storage_path('logs/binance.log'),
            'level'  => 'debug',
            'days'   => 30,
            'replace_placeholders' => true,
        ],
        'binance_db' => [
            'driver' => 'custom',
            'via'    => \App\Logging\DatabaseLoggerFactory::class,
            'name'   => 'binance',
            'level'  => 'debug',
        ],

        'perf' => [
            'driver'   => 'stack',
            'channels' => ['perf_file', 'perf_db'],
            'ignore_exceptions' => false,
        ],
        'perf_file' => [
            'driver' => 'daily',
            'path'   => storage_path('logs/performance.log'),
            'level'  => 'debug',
            'days'   => 14,
            'replace_placeholders' => true,
        ],
        'perf_db' => [
            'driver' => 'custom',
            'via'    => \App\Logging\DatabaseLoggerFactory::class,
            'name'   => 'perf',
            'level'  => 'debug',
        ],

    ],

];
