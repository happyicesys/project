<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onchain_metrics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('symbol', 20)->index();            // BTCUSDT, ETHUSDT
            $table->string('metric_type', 50)->index();        // exchange_netflow, whale_transfer, stablecoin_supply, active_addresses, sentiment_score, fear_greed_index
            $table->decimal('value', 20, 8);                   // numeric value
            $table->json('metadata')->nullable();              // extra context (e.g., wallet address, exchange name)
            $table->timestamp('measured_at')->index();
            $table->timestamps();

            $table->index(['symbol', 'metric_type', 'measured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onchain_metrics');
    }
};
