<?php

namespace App\Events;

use App\Models\Question;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AnswerRevealed implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Question $question) {}

    public function broadcastOn(): array
    {
        return [new Channel("game.{$this->question->game_id}")];
    }

    public function broadcastAs(): string
    {
        return 'AnswerRevealed';
    }

    public function broadcastWith(): array
    {
        return [
            'prompt_id' => $this->question->id,
            'correct_option_id' => $this->question->options()->where('is_correct', true)->value('id'),
            'accepted_answers' => $this->question->question_type === 'open_text'
                ? $this->question->accepted_answers
                : null,
        ];
    }
}
