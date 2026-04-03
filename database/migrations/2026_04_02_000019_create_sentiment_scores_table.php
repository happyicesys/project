<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sentiment_scores', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->decimal('score', 5, 3);                    // -1.000 to +1.000
            $table->decimal('velocity', 8, 4)->nullable();     // rate of change per hour
            $table->integer('fear_greed_index')->nullable();   // 0-100
            $table->string('dominant_narrative', 255)->nullable();
            $table->string('overall_bias', 20)->default('NEUTRAL'); // BULLISH, BEARISH, NEUTRAL
            $table->decimal('confidence', 3, 2)->default(0.5); // 0.00-1.00
            $table->json('sources')->nullable();               // which data sources contributed
            $table->timestamp('measured_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sentiment_scores');
    }
};
