<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cached OHLCV klines from Binance.
 * Pre-fetched by DataIngestionCommand on a schedule.
 * Agents read from here — never call Binance directly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_klines', function (Blueprint $table): void {
            $table->id();
            $table->string('symbol', 20)->index();
            $table->string('interval', 10);              // 1m, 5m, 15m, 1h, 4h, 1d
            $table->unsignedBigInteger('open_time');     // Unix ms — primary sort key
            $table->decimal('open', 20, 8);
            $table->decimal('high', 20, 8);
            $table->decimal('low', 20, 8);
            $table->decimal('close', 20, 8);
            $table->decimal('volume', 30, 8);
            $table->decimal('quote_volume', 30, 8);
            $table->unsignedInteger('trade_count');
            $table->unsignedBigInteger('close_time');
            $table->timestamp('fetched_at')->useCurrent();

            $table->unique(['symbol', 'interval', 'open_time']);
            $table->index(['symbol', 'interval', 'open_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_klines');
    }
};
