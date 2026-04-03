<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_quality_checks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('check_domain', 50)->index();       // kline_freshness, price_sanity, feature_store, api_latency, data_gaps, cross_consistency
            $table->enum('status', ['PASS', 'WARN', 'FAIL'])->default('PASS');
            $table->string('overall_status', 20)->default('HEALTHY'); // HEALTHY, DEGRADED, CRITICAL
            $table->integer('symbols_checked')->default(0);
            $table->integer('issues_found')->default(0);
            $table->json('details')->nullable();               // per-domain check results
            $table->decimal('api_latency_avg_ms', 10, 2)->nullable();
            $table->decimal('api_latency_max_ms', 10, 2)->nullable();
            $table->integer('data_gap_count')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_quality_checks');
    }
};
