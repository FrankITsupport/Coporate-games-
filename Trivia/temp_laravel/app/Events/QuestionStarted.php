<?php

namespace App\Events;

use App\Models\Question;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuestionStarted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Question $question) {}

    public function broadcastOn(): array
    {
        return [new Channel("game.{$this->question->game_id}")];
    }

    public function broadcastAs(): string
    {
        return 'QuestionStarted';
    }

    /**
     * Never include is_correct or accepted_answers here — this payload reaches
     * player phones before the question closes.
     */
    public function broadcastWith(): array
    {
        $closesAt = $this->question->started_at->clone()->addSeconds($this->question->duration_seconds);

        return [
            'prompt_mode' => 'trivia',
            'prompt_id' => $this->question->id,
            'duration_seconds' => $this->question->duration_seconds,
            'started_at' => $this->question->started_at->toIso8601String(),
            'closes_at' => $closesAt->toIso8601String(),
            'payload' => [
                'round_name' => $this->question->round_name,
                'question_text' => $this->question->question_text,
                'question_type' => $this->question->question_type,
                'is_double_points' => $this->question->is_double_points,
                'options' => $this->question->question_type === 'multiple_choice'
                    ? $this->question->options()
                        ->where('is_enabled', true)
                        ->orderBy('order_number')
                        ->get(['id', 'label', 'option_text'])
                    : [],
            ],
        ];
    }
}
