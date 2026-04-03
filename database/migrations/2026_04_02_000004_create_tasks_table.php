<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table): void {
            $table->uuid()->primary();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('assigned_to')->nullable();    // agent_id
            $table->string('created_by');                  // "manager" (Claude), "ceo" (Brian), or agent_id
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->enum('status', ['pending', 'in_progress', 'completed', 'failed', 'cancelled'])
                  ->default('pending');
            $table->json('payload')->nullable();           // Task-specific structured data
            $table->json('result')->nullable();            // Agent's result data
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('deadline_at')->nullable();
            $table->timestamps();

            $table->foreign('assigned_to')->references('agent_id')->on('agents')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
