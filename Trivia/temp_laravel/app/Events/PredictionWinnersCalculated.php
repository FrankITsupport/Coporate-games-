<?php

namespace App\Events;

use App\Models\Game;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PredictionWinnersCalculated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Game $game) {}

    public function broadcastOn(): array
    {
        return [new Channel("game.{$this->game->id}")];
    }

    public function broadcastAs(): string
    {
        return 'PredictionWinnersCalculated';
    }

    public function broadcastWith(): array
    {
        $dashboard = app(\App\Services\PredictionDashboardService::class);

        return [
            'winners'  => app(\App\Services\PredictionLeaderboardService::class)->getTop10($this->game),
            'dashboard'=> $dashboard->getAggregates($this->game),
        ];
    }
}
