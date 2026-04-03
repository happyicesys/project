<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Circuit breaker log — records when trading was halted and why.
 * Both Risk Officer and Market Analyst can trigger these autonomously.
 * Portfolio Manager and Execution Engineer check this before acting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('circuit_breakers', function (Blueprint $table): void {
            $table->id();
            $table->string('type');                     // daily_loss, weekly_drawdown, market_anomaly, manual
            $table->string('action');                   // halt_new_trades, pause_new_signals, reduce_sizes
            $table->text('reason');
            $table->boolean('active')->default(true);
            $table->string('triggered_by');             // agent_id or "manager"
            $table->timestamp('expires_at')->nullable();// null = manual reset only
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolved_by')->nullable();
            $table->timestamps();

            $table->index(['active', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('circuit_breakers');
    }
};
