<?php

namespace App\Events;

use App\Models\Player;
use App\Models\PlayerAnswer;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched by ScoreService once answer scoring exists (Phase 8). Defined now so the
 * channel shape can be built and tested in Phase 6.
 *
 * This broadcasts on a public channel keyed by the player's session token rather than a
 * Laravel private channel: private/presence channel auth in Laravel requires Auth::user()
 * to be non-null before it will even invoke a custom Broadcast::channel() callback, but
 * players authenticate via a per-game signed cookie (see JoinController), not the Laravel
 * auth guard — there is no Authenticatable user to satisfy that check. Standing up a real
 * auth guard for players just for this would be premature; the session token is a random
 * 64-character string, so a channel name built from it is unguessable in practice without
 * already holding the player's cookie.
 */
class PlayerResultUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Player $player, public ?PlayerAnswer $lastAnswer = null) {}

    public function broadcastOn(): array
    {
        return [new Channel("player.{$this->player->session_token}")];
    }

    public function broadcastAs(): string
    {
        return 'PlayerResultUpdated';
    }

    public function broadcastWith(): array
    {
        // Calculate points gap to player above
        $pointsGap = null;
        if ($this->player->current_rank && $this->player->current_rank > 1) {
            $playerAbove = \App\Models\Player::where('game_id', $this->player->game_id)
                ->where('current_rank', $this->player->current_rank - 1)
                ->whereNull('disqualified_at')
                ->first(['total_score']);
            if ($playerAbove) {
                $pointsGap = $playerAbove->total_score - ($this->player->total_score ?? 0);
            }
        }

        return [
            'total_score' => $this->player->total_score,
            'current_rank' => $this->player->current_rank,
            'previous_rank' => $this->player->previous_rank,
            'current_streak' => $this->player->current_streak,
            'points_gap' => $pointsGap,
            'last_answer' => $this->lastAnswer ? [
                'question_id' => $this->lastAnswer->question_id,
                'is_correct' => $this->lastAnswer->is_correct,
                'total_points' => $this->lastAnswer->total_points,
                'streak_bonus' => $this->lastAnswer->streak_bonus,
            ] : null,
        ];
    }
}
