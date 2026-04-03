<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_updates', function (Blueprint $table): void {
            $table->uuid()->primary();
            $table->decimal('btc_price', 15, 2);
            $table->decimal('btc_24h_change_pct', 8, 4);
            $table->string('market_regime');               // TRENDING_UP, TRENDING_DOWN, RANGING, VOLATILE, RISK_OFF
            $table->decimal('funding_rate_btc', 10, 6)->nullable();
            $table->decimal('total_oi_change_1h_pct', 8, 4)->nullable();
            $table->json('notable_events')->nullable();
            $table->enum('risk_level', ['LOW', 'MEDIUM', 'HIGH', 'EXTREME'])->default('LOW');
            $table->string('agent_id');
            $table->timestamps();

            $table->foreign('agent_id')->references('agent_id')->on('agents');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_updates');
    }
};
