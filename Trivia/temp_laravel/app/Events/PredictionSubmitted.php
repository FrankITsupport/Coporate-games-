<?php

namespace App\Events;

use App\Models\PredictionAnswer;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a player submits or edits a prediction answer. Broadcast on the
 * public player.{session_token} channel (same token-keyed approach as
 * PlayerResultUpdated) so the player's own phone can confirm the submission.
 */
class PredictionSubmitted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public PredictionAnswer $answer) {}

    public function broadcastOn(): array
    {
        return [new Channel("player.{$this->answer->player->session_token}")];
    }

    public function broadcastAs(): string
    {
        return 'PredictionSubmitted';
    }

    public function broadcastWith(): array
    {
        return [
            'prediction_prompt_id' => $this->answer->prediction_prompt_id,
            'submitted'            => true,
        ];
    }
}
