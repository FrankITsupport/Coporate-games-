<?php

namespace App\Events;

use App\Models\Game;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TriviaLeaderboardUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Game $game) {}

    public function broadcastOn(): array
    {
        return [new Channel("game.{$this->game->id}")];
    }

    public function broadcastAs(): string
    {
        return 'TriviaLeaderboardUpdated';
    }

    public function broadcastWith(): array
    {
        $top = $this->game->players()
            ->active()
            ->orderByDesc('total_score')
            ->limit(10)
            ->get(['id', 'nickname', 'total_score', 'current_rank', 'previous_rank', 'current_streak']);

        return ['leaderboard' => $top];
    }
}
