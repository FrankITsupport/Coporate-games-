<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Player extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_id',
        'nickname',
        'phone',
        'email',
        'consent_accepted',
        'mpesa_card_confirmed',
        'session_token',
        'total_score',
        'current_streak',
        'correct_answers',
        'incorrect_answers',
        'missed_answers',
        'double_point_correct_answers',
        'total_correct_response_ms',
        'previous_rank',
        'current_rank',
        'disqualified_at',
    ];

    protected function casts(): array
    {
        return [
            'consent_accepted' => 'boolean',
            'mpesa_card_confirmed' => 'boolean',
            'disqualified_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->whereNull('disqualified_at');
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function playerAnswers(): HasMany
    {
        return $this->hasMany(PlayerAnswer::class);
    }

    public function prediction(): HasOne
    {
        return $this->hasOne(Prediction::class);
    }

    public function predictionAnswers(): HasMany
    {
        return $this->hasMany(PredictionAnswer::class);
    }
}
