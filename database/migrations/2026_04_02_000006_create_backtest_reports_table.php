<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backtest_reports', function (Blueprint $table): void {
            $table->uuid()->primary();
            $table->uuid('research_finding_id');
            $table->string('strategy_name');
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('in_sample_sharpe', 8, 4);
            $table->decimal('out_of_sample_sharpe', 8, 4);
            $table->decimal('total_return_pct', 10, 4);
            $table->decimal('max_drawdown_pct', 8, 4);
            $table->integer('max_drawdown_recovery_days')->nullable();
            $table->decimal('win_rate', 5, 2);
            $table->decimal('profit_factor', 8, 4);
            $table->integer('total_trades');
            $table->decimal('avg_trade_duration_hours', 10, 2)->nullable();
            $table->decimal('monte_carlo_95th_drawdown', 8, 4)->nullable();
            $table->json('regime_performance')->nullable();
            $table->enum('verdict', ['PASS', 'FAIL', 'NEEDS_MORE_DATA'])->default('FAIL');
            $table->text('notes')->nullable();
            $table->string('agent_id');
            $table->timestamps();

            $table->foreign('research_finding_id')->references('uuid')->on('research_findings');
            $table->foreign('agent_id')->references('agent_id')->on('agents');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backtest_reports');
    }
};
