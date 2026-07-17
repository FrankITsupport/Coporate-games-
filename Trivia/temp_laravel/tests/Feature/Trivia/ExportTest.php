<?php

namespace Tests\Feature\Trivia;

use App\Models\Game;
use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_trivia_csv_export_contains_expected_columns_and_data(): void
    {
        $admin = User::factory()->create();
        $game = Game::factory()->create();
        Player::factory()->for($game)->create([
            'nickname' => 'Frank',
            'phone' => '254712345678',
            'total_score' => 990,
            'correct_answers' => 3,
            'incorrect_answers' => 1,
            'missed_answers' => 0,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.games.results.export.trivia', $game));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $content = $response->streamedContent();
        $this->assertStringContainsString('Rank,Nickname,Phone,Email,Score', $content);
        $this->assertStringContainsString('Accuracy %', $content);
        $this->assertStringContainsString('Frank', $content);
        $this->assertStringContainsString('254712345678', $content);
        $this->assertStringContainsString('990', $content);
        // 3 correct out of 4 answered = 75%
        $this->assertStringContainsString('75', $content);
    }

    public function test_prediction_csv_export_contains_expected_columns(): void
    {
        $admin = User::factory()->create();
        $game = Game::factory()->create();

        $response = $this->actingAs($admin)->get(route('admin.games.results.export.predictions', $game));

        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertStringContainsString('Predicted Outcome', $content);
        $this->assertStringContainsString('Predicted First Goalscorer', $content);
        $this->assertStringContainsString('Winner Rank', $content);
    }

    public function test_export_requires_admin_authentication(): void
    {
        $game = Game::factory()->create();

        $response = $this->get(route('admin.games.results.export.trivia', $game));

        $response->assertRedirect(route('login'));
    }
}
