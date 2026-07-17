<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\PredictionSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PredictionSetting>
 */
class PredictionSettingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'team_a_name' => 'England',
            'team_b_name' => 'Argentina',
            'team_a_flag' => '🏴󠁧󠁢󠁥󠁮󠁧󠁿',
            'team_b_flag' => '🇦🇷',
            'kickoff_at' => now()->addHours(2),
            'prediction_lock_at' => now()->addHours(2),
            'allow_prediction_edits' => true,
            'winner_rule' => 'points',
        ];
    }
}
