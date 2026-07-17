<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\Player;
use App\Models\PredictionAnswer;
use App\Models\PredictionPrompt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PredictionAnswer>
 */
class PredictionAnswerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'player_id' => Player::factory(),
            'prediction_prompt_id' => PredictionPrompt::factory(),
            'answer_payload' => ['choice' => 'team_a'],
            'points' => 0,
            'is_correct' => false,
            'submitted_at' => now(),
        ];
    }
}
