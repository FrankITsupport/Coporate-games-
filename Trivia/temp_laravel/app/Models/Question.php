<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Question extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_id',
        'round_name',
        'question_text',
        'question_type',
        'duration_seconds',
        'order_number',
        'is_double_points',
        'is_sudden_death',
        'accepted_answers',
        'requires_manual_review',
        'started_at',
        'closed_at',
        'revealed_at',
    ];

    protected function casts(): array
    {
        return [
            'accepted_answers' => 'array',
            'is_double_points' => 'boolean',
            'is_sudden_death' => 'boolean',
            'requires_manual_review' => 'boolean',
            'started_at' => 'datetime',
            'closed_at' => 'datetime',
            'revealed_at' => 'datetime',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class);
    }

    public function playerAnswers(): HasMany
    {
        return $this->hasMany(PlayerAnswer::class);
    }

    /**
     * The active-prompt payload shape from plans/design.md, shared by the QuestionStarted
     * broadcast and PlayController's initial page load (so a mid-question refresh sees the
     * same prompt without waiting for the next broadcast). Never include is_correct or
     * accepted_answers here — this reaches player phones before the question closes.
     */
    public function toActivePromptPayload(): array
    {
        $closesAt = $this->started_at->clone()->addSeconds($this->duration_seconds);

        return [
            'prompt_mode' => 'trivia',
            'prompt_id' => $this->id,
            'duration_seconds' => $this->duration_seconds,
            'started_at' => $this->started_at->toIso8601String(),
            'closes_at' => $closesAt->toIso8601String(),
            'payload' => [
                'round_name' => $this->round_name,
                'question_text' => $this->question_text,
                'question_type' => $this->question_type,
                'is_double_points' => $this->is_double_points,
                'options' => $this->question_type === 'multiple_choice'
                    ? $this->options()
                        ->where('is_enabled', true)
                        ->orderBy('order_number')
                        ->get(['id', 'label', 'option_text'])
                    : [],
            ],
        ];
    }
}
