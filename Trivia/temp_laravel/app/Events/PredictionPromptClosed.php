<?php

namespace App\Events;

use App\Models\PredictionPrompt;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PredictionPromptClosed implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public PredictionPrompt $prompt) {}

    public function broadcastOn(): array
    {
        return [new Channel("game.{$this->prompt->game_id}")];
    }

    public function broadcastAs(): string
    {
        return 'PredictionPromptClosed';
    }

    public function broadcastWith(): array
    {
        return [
            'prompt_id' => $this->prompt->id,
        ];
    }
}
