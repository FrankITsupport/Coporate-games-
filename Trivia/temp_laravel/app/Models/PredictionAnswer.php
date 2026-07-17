<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PredictionAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_id',
        'player_id',
        'prediction_prompt_id',
        'answer_payload',
        'points',
        'is_correct',
        'submitted_at',
        'last_edited_at',
    ];

    protected function casts(): array
    {
        return [
            'answer_payload' => 'array',
            'is_correct' => 'boolean',
            'submitted_at' => 'datetime',
            'last_edited_at' => 'datetime',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function predictionPrompt(): BelongsTo
    {
        return $this->belongsTo(PredictionPrompt::class);
    }
}
