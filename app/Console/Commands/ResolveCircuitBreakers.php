<?php

namespace App\Console\Commands;

use App\Models\CircuitBreaker;
use Illuminate\Console\Command;

/**
 * Scheduled every minute — auto-expires timed circuit breakers.
 * Keeps the trading system from staying halted after a temporary condition resolves.
 */
class ResolveCircuitBreakers extends Command
{
    protected $signature = 'circuit-breakers:resolve';

    protected $description = 'Auto-expire circuit breakers that have passed their expiry time';

    public function handle(): int
    {
        $resolved = CircuitBreaker::autoResolveExpired();

        if ($resolved > 0) {
            $this->info("Resolved {$resolved} expired circuit breaker(s)");
        }

        return self::SUCCESS;
    }
}
