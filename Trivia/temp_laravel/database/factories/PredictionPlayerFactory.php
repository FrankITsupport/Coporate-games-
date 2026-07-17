<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\PredictionPlayer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PredictionPlayer>
 */
class PredictionPlayerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'team_name' => fake()->randomElement(['England', 'Argentina']),
            'display_name' => fake()->name(),
            'shirt_number' => (string) fake()->numberBetween(1, 26),
            'position' => fake()->randomElement(['GK', 'DF', 'MF', 'FW']),
            'squad_status' => 'starter',
            'is_available' => true,
            'sort_order' => 0,
        ];
    }
}
