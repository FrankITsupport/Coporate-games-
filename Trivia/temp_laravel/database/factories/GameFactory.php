<?php

namespace Database\Factories;

use App\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Game>
 */
class GameFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Game Iko On – Semi-Final Watch Party',
            'code' => strtoupper(Str::random(6)),
            'event_type' => 'both',
            'status' => 'draft',
        ];
    }
}
