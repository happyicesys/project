<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_logs', function (Blueprint $table): void {
            $table->id();

            // Which log channel wrote this entry (trading, agents, binance, perf)
            $table->string('channel', 32)->index();

            // Monolog level name: debug / info / warning / error / critical
            $table->string('level', 16)->index();

            // Short event identifier extracted from message (e.g. TRADE_EXECUTED, SLOW_REQUEST)
            // Makes filtering fast without parsing the full message.
            $table->string('event', 128)->nullable()->index();

            // Full human-readable message
            $table->text('message');

            // Structured context payload (agent_id, symbol, latency_ms, etc.)
            $table->json('context')->nullable();

            // Use created_at only — no updated_at, logs are immutable
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_logs');
    }
};
