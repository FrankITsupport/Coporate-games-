<?php

namespace App\Events;

use App\Models\PredictionPrompt;
use App\Models\PredictionSetting;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PredictionPromptStarted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public PredictionPrompt $prompt) {}

    public function broadcastOn(): array
    {
        return [new Channel("game.{$this->prompt->game_id}")];
    }

    public function broadcastAs(): string
    {
        return 'PredictionPromptStarted';
    }

    public function broadcastWith(): array
    {
        $setting = $this->prompt->game->predictionSetting;

        return $this->prompt->toActivePromptPayload($setting);
    }
}
