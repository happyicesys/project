<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agents', function (Blueprint $table): void {
            $table->string('agent_id')->primary();        // e.g. "quant-researcher"
            $table->string('name');                        // e.g. "Quant Researcher"
            $table->string('role');                        // e.g. "researcher", "backtester"
            $table->string('api_token', 80)->unique();    // Bearer token for agent auth
            $table->enum('status', ['online', 'offline', 'error', 'maintenance'])
                  ->default('offline');
            $table->json('capabilities')->nullable();      // What this agent can do
            $table->json('permissions')->nullable();       // What API endpoints it can access
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};
