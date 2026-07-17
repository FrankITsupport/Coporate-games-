<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\PredictionPrompt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PredictionPrompt>
 */
class PredictionPromptFactory extends Factory
{
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'prompt_key' => 'match_outcome',
            'prompt_label' => 'Who will win?',
            'prompt_type' => 'flag_choice',
            'is_enabled' => true,
            'duration_seconds' => 30,
            'order_number' => 0,
        ];
    }
}
