<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\Player;
use App\Models\PlayerAnswer;
use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlayerAnswer>
 */
class PlayerAnswerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'player_id' => Player::factory(),
            'question_id' => Question::factory(),
            'is_correct' => false,
            'review_status' => 'auto_scored',
            'response_ms' => fake()->numberBetween(500, 20000),
            'base_points' => 0,
            'speed_bonus' => 0,
            'streak_bonus' => 0,
            'total_points' => 0,
            'answered_at' => now(),
        ];
    }
}
