<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureSlowQueryLogging();
    }

    /**
     * Log any DB query that takes longer than 100 ms to the perf channel.
     * This catches missing indexes and N+1 queries before they become
     * problems under real trading load.
     */
    protected function configureSlowQueryLogging(): void
    {
        DB::listen(function (QueryExecuted $query): void {
            if ($query->time > 100) {
                Log::channel('perf')->warning('SLOW QUERY '.round($query->time).'ms', [
                    'sql'      => $query->sql,
                    'bindings' => $query->bindings,
                    'time_ms'  => $query->time,
                ]);
            }
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
