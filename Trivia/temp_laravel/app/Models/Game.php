<?php

namespace App\Models;

use App\Enums\GameStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Game extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'event_type',
        'status',
        'paused_from_status',
        'current_question_id',
        'prediction_status',
        'current_prediction_prompt_id',
        'registration_opened_at',
        'predictions_opened_at',
        'predictions_locked_at',
        'started_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => GameStatus::class,
            'registration_opened_at' => 'datetime',
            'predictions_opened_at' => 'datetime',
            'predictions_locked_at' => 'datetime',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function players(): HasMany
    {
        return $this->hasMany(Player::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function playerAnswers(): HasMany
    {
        return $this->hasMany(PlayerAnswer::class);
    }

    public function currentQuestion(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'current_question_id');
    }

    public function predictionSetting(): HasOne
    {
        return $this->hasOne(PredictionSetting::class);
    }

    public function predictionPlayers(): HasMany
    {
        return $this->hasMany(PredictionPlayer::class);
    }

    public function predictionPrompts(): HasMany
    {
        return $this->hasMany(PredictionPrompt::class);
    }

    public function currentPredictionPrompt(): BelongsTo
    {
        return $this->belongsTo(PredictionPrompt::class, 'current_prediction_prompt_id');
    }

    public function predictions(): HasMany
    {
        return $this->hasMany(Prediction::class);
    }

    public function predictionAnswers(): HasMany
    {
        return $this->hasMany(PredictionAnswer::class);
    }

    public function gameEvents(): HasMany
    {
        return $this->hasMany(GameEvent::class);
    }

    // ─── Prediction state helpers ───────────────────────────────────────────

    public function predictionIsOpen(): bool
    {
        return in_array($this->prediction_status, ['predictions_open', 'prediction_prompt_live'], true);
    }

    public function predictionsAreLocked(): bool
    {
        return $this->prediction_status === 'predictions_locked'
            || $this->prediction_status === 'official_results_pending'
            || $this->prediction_status === 'prediction_results_ready'
            || $this->predictions_locked_at !== null;
    }

    public function enabledPredictionPrompts()
    {
        return $this->predictionPrompts()->enabled()->ordered();
    }
}
