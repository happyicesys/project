<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('positions', function (Blueprint $table): void {
            $table->uuid()->primary();
            $table->uuid('signal_uuid');
            $table->string('symbol', 20);
            $table->enum('direction', ['LONG', 'SHORT']);
            $table->decimal('entry_price', 20, 8);
            $table->decimal('quantity', 20, 8);
            $table->decimal('stop_loss', 20, 8);
            $table->decimal('take_profit', 20, 8);
            $table->decimal('risk_pct', 5, 2);
            $table->decimal('current_price', 20, 8)->nullable();
            $table->decimal('unrealised_pnl', 20, 8)->nullable();
            $table->decimal('unrealised_pnl_pct', 8, 4)->nullable();
            $table->enum('status', ['open', 'closed', 'partially_closed'])
                  ->default('open');
            $table->decimal('exit_price', 20, 8)->nullable();
            $table->decimal('realised_pnl', 20, 8)->nullable();
            $table->string('close_reason')->nullable();  // stop_loss, take_profit, manual, liquidated
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->foreign('signal_uuid')->references('uuid')->on('trade_signals');
            $table->index(['status', 'symbol']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('positions');
    }
};
