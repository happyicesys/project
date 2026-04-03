<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vault_entries', function (Blueprint $table): void {
            $table->id();
            $table->string('service')->index();          // e.g. "binance", "anthropic"
            $table->string('key_name');                   // e.g. "api_key", "api_secret"
            $table->text('encrypted_value');              // AES-256-CBC encrypted via Laravel's encrypt()
            $table->string('environment')->default('production'); // production, testnet, paper
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['service', 'key_name', 'environment']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vault_entries');
    }
};
