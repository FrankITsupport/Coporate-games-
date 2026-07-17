<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Prediction extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_id',
        'player_id',
        'predicted_outcome',
        'predicted_team_a_score',
        'predicted_team_b_score',
        'predicted_first_goalscorer_id',
        'predicted_player_of_match_id',
        'predicted_first_goal_minute',
        'points',
        'winner_rank',
        'exact_score_correct',
        'correct_outcome',
        'correct_first_goalscorer',
        'correct_player_of_match',
        'first_goal_minute_diff',
        'goal_difference',
        'submitted_at',
        'last_edited_at',
    ];

    protected function casts(): array
    {
        return [
            'exact_score_correct' => 'boolean',
            'correct_outcome' => 'boolean',
            'correct_first_goalscorer' => 'boolean',
            'correct_player_of_match' => 'boolean',
            'first_goal_minute_diff' => 'integer',
            'goal_difference' => 'integer',
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

    public function predictedFirstGoalscorer(): BelongsTo
    {
        return $this->belongsTo(PredictionPlayer::class, 'predicted_first_goalscorer_id');
    }

    public function predictedPlayerOfMatch(): BelongsTo
    {
        return $this->belongsTo(PredictionPlayer::class, 'predicted_player_of_match_id');
    }
}
