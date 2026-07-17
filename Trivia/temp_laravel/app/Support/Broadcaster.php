<?php

namespace App\Support;

use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Broadcasts an event without letting a Reverb outage take the whole HTTP
 * request down with it. Every admin action already persists its state change
 * to the database before broadcasting; if Reverb is unreachable, the action
 * itself must still succeed and the admin must still see a normal response —
 * only the real-time push is best-effort. Screens recover on refresh either
 * way, per plans/design.md's reliability notes.
 */
class Broadcaster
{
    public static function send(ShouldBroadcast $event): void
    {
        try {
            broadcast($event);
        } catch (Throwable $e) {
            Log::warning('Broadcast failed, continuing without it', [
                'event' => get_class($event),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
