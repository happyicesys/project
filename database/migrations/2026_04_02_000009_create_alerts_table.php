<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table): void {
            $table->uuid()->primary();
            $table->string('type');                         // PRICE_SPIKE, VOLUME_SPIKE, etc.
            $table->string('symbol')->nullable();
            $table->enum('severity', ['INFO', 'WARNING', 'CRITICAL']);
            $table->text('description');
            $table->text('recommended_action')->nullable();
            $table->boolean('acknowledged')->default(false);
            $table->string('acknowledged_by')->nullable();  // "manager" or "ceo"
            $table->string('agent_id');
            $table->timestamps();

            $table->foreign('agent_id')->references('agent_id')->on('agents');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
