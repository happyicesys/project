<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('execution_reports', function (Blueprint $table): void {
            $table->uuid()->primary();
            $table->uuid('signal_uuid');
            $table->string('symbol');
            $table->enum('direction', ['LONG', 'SHORT']);
            $table->enum('status', ['FILLED', 'PARTIAL', 'FAILED', 'CANCELLED']);
            $table->string('entry_order_id')->nullable();      // Binance order ID
            $table->decimal('fill_price', 15, 5)->nullable();
            $table->decimal('fill_quantity', 15, 8)->nullable();
            $table->decimal('slippage_bps', 8, 2)->nullable(); // Basis points
            $table->string('stop_loss_order_id')->nullable();
            $table->string('take_profit_order_id')->nullable();
            $table->decimal('fees_paid', 15, 8)->nullable();
            $table->text('error')->nullable();
            $table->string('agent_id');
            $table->timestamps();

            $table->foreign('signal_uuid')->references('uuid')->on('trade_signals');
            $table->foreign('agent_id')->references('agent_id')->on('agents');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('execution_reports');
    }
};
