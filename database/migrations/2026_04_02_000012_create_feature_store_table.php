<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pre-computed feature vectors for the Algorithm Designer and Backtester.
 * Computed once from klines, stored here, read many times.
 * Avoids redundant computation across agent sessions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_store', function (Blueprint $table): void {
            $table->id();
            $table->string('symbol', 20)->index();
            $table->string('interval', 10);
            $table->unsignedBigInteger('open_time');     // Aligns with market_klines
            $table->string('feature_set');               // Named set, e.g. "v1_standard"
            $table->json('features');                    // { feature_name: value, ... }
            $table->timestamp('computed_at')->useCurrent();

            $table->unique(['symbol', 'interval', 'open_time', 'feature_set']);
            $table->index(['symbol', 'interval', 'feature_set', 'open_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_store');
    }
};
