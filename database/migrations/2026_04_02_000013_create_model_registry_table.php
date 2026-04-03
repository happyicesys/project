<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trained ML model artifacts and metadata.
 * Algorithm Designer writes here after training.
 * Signal Engineer reads here to build signals.
 * Prevents retraining the same model multiple times.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('model_registry', function (Blueprint $table): void {
            $table->uuid()->primary();
            $table->uuid('research_finding_id')->nullable();
            $table->string('strategy_name');
            $table->string('model_type');               // LightGBM, XGBoost, TFT, PPO
            $table->string('feature_set');              // e.g. "v1_standard"
            $table->json('feature_list');               // Ordered list of features used
            $table->json('hyperparameters');            // Model hyperparameters
            $table->decimal('in_sample_sharpe', 8, 4);
            $table->decimal('out_of_sample_sharpe', 8, 4);
            $table->decimal('directional_accuracy', 5, 2)->nullable();
            $table->json('shap_summary')->nullable();   // Top feature importances
            $table->json('fragility_flags')->nullable();
            $table->string('artifact_path')->nullable(); // Path to serialised model file
            $table->enum('status', ['candidate', 'approved', 'in_paper', 'live', 'retired'])
                  ->default('candidate');
            $table->boolean('used_in_signal')->default(false);
            $table->string('agent_id');
            $table->timestamps();

            $table->foreign('research_finding_id')->references('uuid')->on('research_findings')->nullOnDelete();
            $table->foreign('agent_id')->references('agent_id')->on('agents');
            $table->index(['status', 'used_in_signal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_registry');
    }
};
