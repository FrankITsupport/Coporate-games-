<?php

namespace Tests\Feature\Prediction;

use App\Models\Game;
use App\Models\Player;
use App\Models\PredictionAnswer;
use App\Models\PredictionPlayer;
use App\Models\PredictionPrompt;
use App\Models\PredictionSetting;
use App\Services\PredictionScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PredictionScoringTest extends TestCase
{
    use RefreshDatabase;

    private function makePrompts(Game $game): void
    {
        PredictionPrompt::factory()->for($game)->create(['prompt_key' => 'match_outcome', 'prompt_type' => 'flag_choice', 'order_number' => 1]);
        PredictionPrompt::factory()->for($game)->create(['prompt_key' => 'final_score', 'prompt_type' => 'score_input', 'order_number' => 2]);
        PredictionPrompt::factory()->for($game)->create(['prompt_key' => 'first_goalscorer', 'prompt_type' => 'player_select', 'order_number' => 3]);
        PredictionPrompt::factory()->for($game)->create(['prompt_key' => 'player_of_match', 'prompt_type' => 'player_select', 'order_number' => 4]);
        PredictionPrompt::factory()->for($game)->create(['prompt_key' => 'first_goal_minute', 'prompt_type' => 'number_input', 'order_number' => 5]);
    }

    private function submitPredictionAnswers(Game $game, Player $player, array $answers): void
    {
        foreach ($answers as $promptKey => $payload) {
            $prompt = PredictionPrompt::where('game_id', $game->id)->where('prompt_key', $promptKey)->first();
            PredictionAnswer::create([
                'game_id' => $game->id,
                'player_id' => $player->id,
                'prediction_prompt_id' => $prompt->id,
                'answer_payload' => $payload,
                'submitted_at' => now(),
                'last_edited_at' => now(),
            ]);
        }

        app(PredictionScoringService::class)->syncSummary($game, $player->id, $player);
    }

    public function test_points_rule_awards_full_points_for_a_perfect_prediction(): void
    {
        $game = Game::factory()->create();
        $this->makePrompts($game);
        $scorer = PredictionPlayer::factory()->for($game)->create();
        $potm = PredictionPlayer::factory()->for($game)->create();
        PredictionSetting::factory()->for($game)->create([
            'winner_rule' => 'points',
            'official_outcome' => 'team_a_win',
            'official_team_a_score' => 2,
            'official_team_b_score' => 1,
            'official_first_goalscorer_id' => $scorer->id,
            'official_player_of_match_id' => $potm->id,
            'official_first_goal_minute' => 23,
        ]);
        $player = Player::factory()->for($game)->create();

        $this->submitPredictionAnswers($game, $player, [
            'match_outcome' => ['outcome' => 'team_a_win'],
            'final_score' => ['team_a_score' => 2, 'team_b_score' => 1],
            'first_goalscorer' => ['player_id' => $scorer->id],
            'player_of_match' => ['player_id' => $potm->id],
            'first_goal_minute' => ['minute' => 23],
        ]);

        app(PredictionScoringService::class)->score($game);

        $prediction = $game->predictions()->where('player_id', $player->id)->first();
        // exact score 5 + outcome 2 + goalscorer 3 + potm 3 + minute exact 5 = 18
        $this->assertSame(18, $prediction->points);
        $this->assertTrue($prediction->exact_score_correct);
        $this->assertTrue($prediction->correct_outcome);
        $this->assertTrue($prediction->correct_first_goalscorer);
        $this->assertTrue($prediction->correct_player_of_match);
        $this->assertSame(0, $prediction->first_goal_minute_diff);
        $this->assertSame(1, $prediction->winner_rank);
    }

    public function test_closest_score_rule_does_not_award_exact_score_bonus(): void
    {
        $game = Game::factory()->create();
        $this->makePrompts($game);
        PredictionSetting::factory()->for($game)->create([
            'winner_rule' => 'closest_score',
            'official_outcome' => 'team_a_win',
            'official_team_a_score' => 2,
            'official_team_b_score' => 1,
        ]);
        $player = Player::factory()->for($game)->create();

        $this->submitPredictionAnswers($game, $player, [
            'match_outcome' => ['outcome' => 'team_a_win'],
            'final_score' => ['team_a_score' => 2, 'team_b_score' => 1],
        ]);

        app(PredictionScoringService::class)->score($game);

        $prediction = $game->predictions()->where('player_id', $player->id)->first();
        // closest_score rule ignores the exact-score bonus entirely, even on an exact match.
        $this->assertSame(2, $prediction->points); // outcome only
        $this->assertTrue($prediction->exact_score_correct);
        $this->assertSame(0, $prediction->goal_difference);
    }

    public function test_minute_prediction_within_tolerance_scores_partial_points(): void
    {
        $game = Game::factory()->create();
        $this->makePrompts($game);
        PredictionSetting::factory()->for($game)->create([
            'winner_rule' => 'points',
            'official_first_goal_minute' => 20,
        ]);
        $player = Player::factory()->for($game)->create();

        $this->submitPredictionAnswers($game, $player, [
            'first_goal_minute' => ['minute' => 24], // diff = 4, within tolerance of 5
        ]);

        app(PredictionScoringService::class)->score($game);

        $prediction = $game->predictions()->where('player_id', $player->id)->first();
        $this->assertSame(2, $prediction->points);
        $this->assertSame(4, $prediction->first_goal_minute_diff);
    }

    public function test_minute_prediction_outside_tolerance_scores_zero(): void
    {
        $game = Game::factory()->create();
        $this->makePrompts($game);
        PredictionSetting::factory()->for($game)->create([
            'winner_rule' => 'points',
            'official_first_goal_minute' => 20,
        ]);
        $player = Player::factory()->for($game)->create();

        $this->submitPredictionAnswers($game, $player, [
            'first_goal_minute' => ['minute' => 40], // diff = 20, outside tolerance
        ]);

        app(PredictionScoringService::class)->score($game);

        $prediction = $game->predictions()->where('player_id', $player->id)->first();
        $this->assertSame(0, $prediction->points);
        $this->assertSame(20, $prediction->first_goal_minute_diff);
    }

    public function test_leaderboard_ranks_predictions_by_points_descending(): void
    {
        $game = Game::factory()->create();
        $this->makePrompts($game);
        PredictionSetting::factory()->for($game)->create([
            'winner_rule' => 'points',
            'official_outcome' => 'team_a_win',
        ]);
        $winner = Player::factory()->for($game)->create();
        $loser = Player::factory()->for($game)->create();

        $this->submitPredictionAnswers($game, $winner, ['match_outcome' => ['outcome' => 'team_a_win']]);
        $this->submitPredictionAnswers($game, $loser, ['match_outcome' => ['outcome' => 'team_b_win']]);

        app(PredictionScoringService::class)->score($game);

        $winnerPrediction = $game->predictions()->where('player_id', $winner->id)->first();
        $loserPrediction = $game->predictions()->where('player_id', $loser->id)->first();

        $this->assertSame(1, $winnerPrediction->winner_rank);
        $this->assertGreaterThan($winnerPrediction->winner_rank, $loserPrediction->winner_rank);
    }

    public function test_full_admin_flow_from_results_entry_to_calculated_winners(): void
    {
        $admin = \App\Models\User::factory()->create();
        $game = Game::factory()->create();
        $this->makePrompts($game);
        $scorer = PredictionPlayer::factory()->for($game)->create();
        PredictionSetting::factory()->for($game)->create(['winner_rule' => 'points']);
        $player = Player::factory()->for($game)->create();
        $this->submitPredictionAnswers($game, $player, [
            'match_outcome' => ['outcome' => 'team_a_win'],
            'first_goalscorer' => ['player_id' => $scorer->id],
        ]);

        $this->actingAs($admin)->post(route('admin.games.predictions.results', $game), [
            'official_outcome' => 'team_a_win',
            'official_first_goalscorer_id' => $scorer->id,
        ])->assertRedirect();

        $this->assertSame('official_results_pending', $game->fresh()->prediction_status);

        $this->actingAs($admin)->post(route('admin.games.predictions.calculate', $game))
            ->assertRedirect();

        $this->assertSame('prediction_results_ready', $game->fresh()->prediction_status);
        $prediction = $game->predictions()->where('player_id', $player->id)->first();
        $this->assertSame(5, $prediction->points); // outcome 2 + goalscorer 3
    }

    public function test_calculate_winners_is_blocked_until_official_results_are_entered(): void
    {
        $admin = \App\Models\User::factory()->create();
        $game = Game::factory()->create();
        PredictionSetting::factory()->for($game)->create();

        $response = $this->actingAs($admin)->post(route('admin.games.predictions.calculate', $game));

        $response->assertSessionHasErrors('status');
        $this->assertNotSame('prediction_results_ready', $game->fresh()->prediction_status);
    }
}
