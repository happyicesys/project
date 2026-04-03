<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot of portfolio state at each assessment.
 * Portfolio Manager writes here every hour.
 * Single source of truth for all agents querying portfolio exposure.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portfolio_state', function (Blueprint $table): void {
            $table->id();
            $table->decimal('total_equity', 20, 8);          // USD value
            $table->decimal('available_capital', 20, 8);
            $table->decimal('deployed_pct', 5, 2);           // % of equity in positions
            $table->decimal('daily_pnl', 20, 8)->default(0);
            $table->decimal('daily_pnl_pct', 8, 4)->default(0);
            $table->decimal('weekly_pnl_pct', 8, 4)->default(0);
            $table->decimal('monthly_pnl_pct', 8, 4)->default(0);
            $table->decimal('max_drawdown_daily_pct', 8, 4)->default(0);
            $table->decimal('max_drawdown_weekly_pct', 8, 4)->default(0);
            $table->integer('open_positions_count')->default(0);
            $table->boolean('circuit_breaker_active')->default(false);
            $table->string('circuit_breaker_reason')->nullable();
            $table->json('strategy_allocations')->nullable();  // { strategy_id: pct, ... }
            $table->timestamps();
        });

        // Separate table for per-strategy live performance tracking
        Schema::create('strategy_performance', function (Blueprint $table): void {
            $table->uuid()->primary();
            $table->uuid('backtest_report_id');
            $table->string('strategy_name');
            $table->unsignedTinyInteger('tier')->default(0);  // 0-4
            $table->decimal('allocation_pct', 5, 2)->default(0);
            $table->decimal('live_sharpe_7d', 8, 4)->nullable();
            $table->decimal('live_sharpe_30d', 8, 4)->nullable();
            $table->decimal('total_pnl_pct', 10, 4)->default(0);
            $table->decimal('max_drawdown_live_pct', 8, 4)->default(0);
            $table->decimal('win_rate_live', 5, 2)->nullable();
            $table->integer('total_live_trades')->default(0);
            $table->date('paper_started_at')->nullable();
            $table->date('live_started_at')->nullable();
            $table->timestamp('last_reviewed_at')->nullable();
            $table->enum('status', ['paper', 'live', 'paused', 'retired'])->default('paper');
            $table->timestamps();

            $table->foreign('backtest_report_id')->references('uuid')->on('backtest_reports');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('strategy_performance');
        Schema::dropIfExists('portfolio_state');
    }
};
