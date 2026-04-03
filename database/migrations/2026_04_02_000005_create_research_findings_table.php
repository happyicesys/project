<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('research_findings', function (Blueprint $table): void {
            $table->uuid()->primary();
            $table->string('signal_name');
            $table->text('hypothesis');
            $table->string('universe');                    // e.g. "BTCUSDT, ETHUSDT"
            $table->string('timeframe');                   // e.g. "1h", "4h"
            $table->string('lookback');                    // e.g. "6 months"
            $table->string('edge_metric');                 // e.g. "sharpe", "sortino"
            $table->decimal('edge_value', 10, 4);
            $table->string('statistical_test');
            $table->decimal('p_value', 10, 6);
            $table->boolean('out_of_sample')->default(false);
            $table->decimal('out_of_sample_value', 10, 4)->nullable();
            $table->enum('status', ['submitted', 'under_review', 'approved_for_backtest', 'rejected', 'promoted'])
                  ->default('submitted');
            $table->string('agent_id');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('agent_id')->references('agent_id')->on('agents');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research_findings');
    }
};
