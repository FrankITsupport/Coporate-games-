<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PredictionSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_id',
        'team_a_name',
        'team_b_name',
        'team_a_flag',
        'team_b_flag',
        'kickoff_at',
        'prediction_lock_at',
        'allow_prediction_edits',
        'winner_rule',
        'official_team_a_score',
        'official_team_b_score',
        'official_outcome',
        'official_first_goalscorer_id',
        'official_player_of_match_id',
        'official_first_goal_minute',
        'official_results_payload',
        'official_results_entered_at',
    ];

    protected function casts(): array
    {
        return [
            'kickoff_at' => 'datetime',
            'prediction_lock_at' => 'datetime',
            'allow_prediction_edits' => 'boolean',
            'official_results_payload' => 'array',
            'official_results_entered_at' => 'datetime',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function officialFirstGoalscorer(): BelongsTo
    {
        return $this->belongsTo(PredictionPlayer::class, 'official_first_goalscorer_id');
    }

    public function officialPlayerOfMatch(): BelongsTo
    {
        return $this->belongsTo(PredictionPlayer::class, 'official_player_of_match_id');
    }

    /**
     * Safe, public-facing match config: flags only, never internal team names.
     * Consumed by player phones and the stage screen.
     */
    public function toPublicConfig(): array
    {
        return [
            'team_a_flag' => $this->team_a_flag,
            'team_b_flag' => $this->team_b_flag,
            'team_a_name' => $this->team_a_name,
            'team_b_name' => $this->team_b_name,
            'kickoff_at'  => $this->kickoff_at?->toIso8601String(),
            'winner_rule' => $this->winner_rule,
        ];
    }
}
