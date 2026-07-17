<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'round_name' => fake()->randomElement([
                'General Knowledge', 'FIFA World Cup', 'Visa Questions', 'M-Pesa Questions', 'Campaign Questions',
            ]),
            'question_text' => fake()->sentence().'?',
            'question_type' => 'multiple_choice',
            'duration_seconds' => 20,
            'order_number' => 0,
            'is_double_points' => false,
            'is_sudden_death' => false,
            'requires_manual_review' => false,
        ];
    }

    public function openText(): static
    {
        return $this->state(fn () => [
            'question_type' => 'open_text',
            'accepted_answers' => [fake()->word()],
        ]);
    }
}
