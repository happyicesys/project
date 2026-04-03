<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SystemLog extends Model
{
    public $timestamps   = false;   // we manage created_at manually
    public $incrementing = true;

    protected $fillable = [
        'channel',
        'level',
        'event',
        'message',
        'context',
        'created_at',
    ];

    protected $casts = [
        'context'    => 'array',
        'created_at' => 'datetime',
    ];

    // ─── Scopes ──────────────────────────────────────────────────────────

    public function scopeChannel(Builder $q, string $channel): Builder
    {
        return $q->where('channel', $channel);
    }

    public function scopeLevel(Builder $q, string $level): Builder
    {
        return $q->where('level', $level);
    }

    public function scopeEvent(Builder $q, string $event): Builder
    {
        return $q->where('event', $event);
    }

    /** Entries from the last N minutes. */
    public function scopeRecent(Builder $q, int $minutes = 60): Builder
    {
        return $q->where('created_at', '>=', now()->subMinutes($minutes));
    }

    /** Critical + error entries only. */
    public function scopeErrors(Builder $q): Builder
    {
        return $q->whereIn('level', ['critical', 'error']);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────

    /**
     * Extract a short event token from a log message.
     * e.g. "TRADE_EXECUTED symbol=BTCUSDT ..." → "TRADE_EXECUTED"
     * Falls back to the first 64 chars of the message.
     */
    public static function parseEvent(string $message): string
    {
        // If the message starts with an UPPER_SNAKE_CASE token, use it
        if (preg_match('/^([A-Z][A-Z0-9_]{1,63})/', ltrim($message), $m)) {
            return $m[1];
        }

        return substr($message, 0, 64);
    }
}
