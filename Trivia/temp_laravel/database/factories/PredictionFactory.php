<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\Player;
use App\Models\Prediction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Prediction>
 */
class PredictionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'player_id' => Player::factory(),
            'predicted_outcome' => fake()->randomElement(['team_a', 'team_b', 'draw']),
            'predicted_team_a_score' => fake()->numberBetween(0, 4),
            'predicted_team_b_score' => fake()->numberBetween(0, 4),
            'points' => 0,
            'submitted_at' => now(),
        ];
    }
}
