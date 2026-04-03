<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trade_signals', function (Blueprint $table): void {
            $table->uuid()->primary();
            $table->string('symbol');
            $table->enum('direction', ['LONG', 'SHORT']);
            $table->decimal('entry_price', 15, 5);
            $table->decimal('stop_loss', 15, 5);
            $table->decimal('take_profit', 15, 5);
            $table->decimal('risk_percentage', 5, 2);
            $table->enum('status', ['PENDING', 'REJECTED_BY_RISK', 'APPROVED', 'EXECUTED', 'FAILED'])
                  ->default('PENDING');
            $table->text('rejection_reason')->nullable();
            $table->string('agent_id');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_signals');
    }
};
