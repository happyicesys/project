<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fix: add onDelete cascade to positions.signal_uuid foreign key.
 * Without this, deleting a trade signal leaves orphaned position rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('positions', function (Blueprint $table) {
            // Drop old constraint first, then re-add with cascade
            $table->dropForeign(['signal_uuid']);
            $table->foreign('signal_uuid')
                ->references('uuid')
                ->on('trade_signals')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('positions', function (Blueprint $table) {
            $table->dropForeign(['signal_uuid']);
            $table->foreign('signal_uuid')
                ->references('uuid')
                ->on('trade_signals');
        });
    }
};
