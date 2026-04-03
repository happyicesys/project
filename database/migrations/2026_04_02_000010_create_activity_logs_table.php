<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('actor');                       // agent_id, "manager", "ceo"
            $table->string('action');                      // e.g. "signal.submitted", "task.completed"
            $table->string('subject_type')->nullable();    // e.g. "trade_signal", "task"
            $table->string('subject_id')->nullable();      // UUID of the subject
            $table->json('metadata')->nullable();          // Additional context
            $table->timestamps();

            $table->index(['actor', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
