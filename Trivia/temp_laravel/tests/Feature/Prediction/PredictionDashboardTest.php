<?php

namespace Tests\Feature\Prediction;

use App\Models\Game;
use App\Models\Player;
use App\Models\PredictionAnswer;
use App\Models\PredictionPlayer;
use App\Models\PredictionPrompt;
use App\Models\PredictionSetting;
use App\Services\PredictionDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PredictionDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_tallies_outcome_votes(): void
    {
        $game = Game::factory()->create();
        PredictionSetting::factory()->for($game)->create();
        $prompt = PredictionPrompt::factory()->for($game)->create(['prompt_key' => 'match_outcome', 'prompt_type' => 'flag_choice']);

        PredictionAnswer::factory()->for($game)->for($prompt, 'predictionPrompt')->create(['answer_payload' => ['outcome' => 'team_a_win']]);
        PredictionAnswer::factory()->for($game)->for($prompt, 'predictionPrompt')->create(['answer_payload' => ['outcome' => 'team_a_win']]);
        PredictionAnswer::factory()->for($game)->for($prompt, 'predictionPrompt')->create(['answer_payload' => ['outcome' => 'draw']]);

        $aggregates = app(PredictionDashboardService::class)->getAggregates($game);

        $this->assertSame(2, $aggregates['outcomes']['team_a_win']);
        $this->assertSame(1, $aggregates['outcomes']['draw']);
    }

    public function test_dashboard_ignores_answers_from_disabled_prompts(): void
    {
        $game = Game::factory()->create();
        PredictionSetting::factory()->for($game)->create();
        $enabled = PredictionPrompt::factory()->for($game)->create(['prompt_key' => 'match_outcome', 'is_enabled' => true]);
        $disabled = PredictionPrompt::factory()->for($game)->create(['prompt_key' => 'match_outcome', 'is_enabled' => false]);

        PredictionAnswer::factory()->for($game)->for($enabled, 'predictionPrompt')->create(['answer_payload' => ['outcome' => 'team_a_win']]);
        PredictionAnswer::factory()->for($game)->for($disabled, 'predictionPrompt')->create(['answer_payload' => ['outcome' => 'team_b_win']]);

        $aggregates = app(PredictionDashboardService::class)->getAggregates($game);

        $this->assertSame(1, $aggregates['outcomes']['team_a_win']);
        $this->assertArrayNotHasKey('team_b_win', $aggregates['outcomes']);
    }

    public function test_dashboard_resolves_predicted_player_names(): void
    {
        $game = Game::factory()->create();
        PredictionSetting::factory()->for($game)->create();
        $scorer = PredictionPlayer::factory()->for($game)->create(['display_name' => 'Harry Kane']);
        $prompt = PredictionPrompt::factory()->for($game)->create(['prompt_key' => 'first_goalscorer', 'prompt_type' => 'player_select']);

        PredictionAnswer::factory()->for($game)->for($prompt, 'predictionPrompt')->create(['answer_payload' => ['player_id' => $scorer->id]]);

        $aggregates = app(PredictionDashboardService::class)->getAggregates($game);

        $this->assertSame(1, $aggregates['first_goalscorer']['Harry Kane']);
    }

    public function test_dashboard_submission_count_only_counts_synced_summaries(): void
    {
        $game = Game::factory()->create();
        PredictionSetting::factory()->for($game)->create();
        $prompt = PredictionPrompt::factory()->for($game)->create(['prompt_key' => 'match_outcome']);
        $player = Player::factory()->for($game)->create();

        PredictionAnswer::factory()->for($game)->for($prompt, 'predictionPrompt')->for($player)->create(['answer_payload' => ['outcome' => 'team_a_win']]);

        // No Prediction summary row synced yet — submission_count should be 0.
        $aggregates = app(PredictionDashboardService::class)->getAggregates($game);
        $this->assertSame(0, $aggregates['submission_count']);

        app(\App\Services\PredictionScoringService::class)->syncSummary($game, $player->id, $player);

        $aggregates = app(PredictionDashboardService::class)->getAggregates($game);
        $this->assertSame(1, $aggregates['submission_count']);
    }

    public function test_admin_dashboard_route_requires_predictions_to_be_open(): void
    {
        $admin = \App\Models\User::factory()->create();
        $game = Game::factory()->create(); // prediction_status null

        $response = $this->actingAs($admin)->post(route('admin.games.predictions.dashboard', $game));

        $response->assertSessionHasErrors('status');
    }

    public function test_admin_dashboard_route_succeeds_once_predictions_are_open(): void
    {
        $admin = \App\Models\User::factory()->create();
        $game = Game::factory()->create(['prediction_status' => 'predictions_open']);

        $response = $this->actingAs($admin)->post(route('admin.games.predictions.dashboard', $game));

        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors();
        $this->assertSame('prediction_dashboard', $game->fresh()->prediction_status);
    }
}
