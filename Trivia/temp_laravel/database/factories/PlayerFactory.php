<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\Player;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Player>
 */
class PlayerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'nickname' => fake()->firstName(),
            'phone' => fake()->unique()->numerify('2547########'),
            'email' => fake()->optional()->safeEmail(),
            'consent_accepted' => true,
            'mpesa_card_confirmed' => fake()->boolean(),
            'session_token' => Str::random(40),
            'total_score' => 0,
            'current_streak' => 0,
            'correct_answers' => 0,
            'incorrect_answers' => 0,
            'missed_answers' => 0,
            'double_point_correct_answers' => 0,
            'total_correct_response_ms' => 0,
        ];
    }
}
