<?php

namespace Tests\Feature\Prediction;

use App\Models\Game;
use App\Models\Player;
use App\Models\PredictionAnswer;
use App\Models\PredictionPlayer;
use App\Models\PredictionPrompt;
use App\Models\PredictionSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithPlayers;
use Tests\TestCase;

class PredictionSubmissionTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithPlayers;

    private function liveGame(): Game
    {
        return Game::factory()->create(['prediction_status' => 'predictions_open']);
    }

    private function makeLivePrompt(Game $game, array $overrides = []): PredictionPrompt
    {
        $prompt = PredictionPrompt::factory()->for($game)->create(array_merge([
            'started_at' => now(),
        ], $overrides));

        $game->update([
            'prediction_status' => 'prediction_prompt_live',
            'current_prediction_prompt_id' => $prompt->id,
        ]);

        return $prompt;
    }

    public function test_player_can_submit_a_flag_choice_prediction(): void
    {
        $game = $this->liveGame();
        $player = Player::factory()->for($game)->create();
        $prompt = $this->makeLivePrompt($game, ['prompt_type' => 'flag_choice', 'prompt_key' => 'match_outcome']);

        $response = $this->actingAsPlayer($game, $player)
            ->postJson(route('predict.answer', [$game, $prompt]), ['outcome' => 'team_a_win']);

        $response->assertOk();
        $response->assertJson(['submitted' => true]);
        $this->assertDatabaseHas('prediction_answers', [
            'player_id' => $player->id,
            'prediction_prompt_id' => $prompt->id,
        ]);
        $answer = PredictionAnswer::first();
        $this->assertSame('team_a_win', $answer->answer_payload['outcome']);
    }

    public function test_submission_is_rejected_when_no_prompt_is_currently_live(): void
    {
        $game = $this->liveGame();
        $player = Player::factory()->for($game)->create();
        $prompt = PredictionPrompt::factory()->for($game)->create(['prompt_type' => 'flag_choice']);
        // Note: not started, game.current_prediction_prompt_id not set to this prompt.

        $response = $this->actingAsPlayer($game, $player)
            ->postJson(route('predict.answer', [$game, $prompt]), ['outcome' => 'team_a_win']);

        $response->assertStatus(422);
    }

    public function test_disabled_prompt_cannot_receive_submissions(): void
    {
        $game = $this->liveGame();
        $player = Player::factory()->for($game)->create();
        $prompt = PredictionPrompt::factory()->for($game)->create(['prompt_type' => 'flag_choice', 'is_enabled' => false]);
        $game->update(['prediction_status' => 'prediction_prompt_live', 'current_prediction_prompt_id' => $prompt->id]);

        $response = $this->actingAsPlayer($game, $player)
            ->postJson(route('predict.answer', [$game, $prompt]), ['outcome' => 'team_a_win']);

        $response->assertStatus(404);
    }

    public function test_player_can_edit_prediction_before_lock_when_edits_are_allowed(): void
    {
        $game = $this->liveGame();
        PredictionSetting::factory()->for($game)->create(['allow_prediction_edits' => true]);
        $player = Player::factory()->for($game)->create();
        $prompt = $this->makeLivePrompt($game, ['prompt_type' => 'flag_choice']);

        $this->actingAsPlayer($game, $player)
            ->postJson(route('predict.answer', [$game, $prompt]), ['outcome' => 'team_a_win'])
            ->assertOk();

        $response = $this->actingAsPlayer($game, $player)
            ->postJson(route('predict.answer', [$game, $prompt]), ['outcome' => 'draw']);

        $response->assertOk();
        $this->assertSame(1, PredictionAnswer::where('player_id', $player->id)->count());
        $this->assertSame('draw', PredictionAnswer::first()->answer_payload['outcome']);
    }

    public function test_player_cannot_edit_prediction_when_edits_are_disallowed(): void
    {
        $game = $this->liveGame();
        PredictionSetting::factory()->for($game)->create(['allow_prediction_edits' => false]);
        $player = Player::factory()->for($game)->create();
        $prompt = $this->makeLivePrompt($game, ['prompt_type' => 'flag_choice']);

        $this->actingAsPlayer($game, $player)
            ->postJson(route('predict.answer', [$game, $prompt]), ['outcome' => 'team_a_win'])
            ->assertOk();

        $response = $this->actingAsPlayer($game, $player)
            ->postJson(route('predict.answer', [$game, $prompt]), ['outcome' => 'draw']);

        $response->assertStatus(422);
        $this->assertSame('team_a_win', PredictionAnswer::first()->answer_payload['outcome']);
    }

    public function test_submission_is_rejected_once_predictions_are_locked(): void
    {
        $game = $this->liveGame();
        $player = Player::factory()->for($game)->create();
        $prompt = $this->makeLivePrompt($game, ['prompt_type' => 'flag_choice']);
        $game->update(['prediction_status' => 'predictions_locked']);

        $response = $this->actingAsPlayer($game, $player)
            ->postJson(route('predict.answer', [$game, $prompt]), ['outcome' => 'team_a_win']);

        $response->assertStatus(422);
    }

    public function test_player_select_prompt_rejects_unavailable_prediction_player(): void
    {
        $game = $this->liveGame();
        $player = Player::factory()->for($game)->create();
        $unavailable = PredictionPlayer::factory()->for($game)->create(['is_available' => false]);
        $prompt = $this->makeLivePrompt($game, ['prompt_type' => 'player_select', 'prompt_key' => 'first_goalscorer']);

        $response = $this->actingAsPlayer($game, $player)
            ->postJson(route('predict.answer', [$game, $prompt]), ['player_id' => $unavailable->id]);

        $response->assertStatus(422);
    }

    public function test_score_input_prompt_validates_score_bounds(): void
    {
        $game = $this->liveGame();
        $player = Player::factory()->for($game)->create();
        $prompt = $this->makeLivePrompt($game, ['prompt_type' => 'score_input', 'prompt_key' => 'final_score']);

        $response = $this->actingAsPlayer($game, $player)
            ->postJson(route('predict.answer', [$game, $prompt]), ['team_a_score' => -1, 'team_b_score' => 2]);

        $response->assertStatus(422);
    }

    public function test_unregistered_visitor_cannot_submit_a_prediction(): void
    {
        $game = $this->liveGame();
        $prompt = $this->makeLivePrompt($game, ['prompt_type' => 'flag_choice']);

        $response = $this->withCredentials()
            ->postJson(route('predict.answer', [$game, $prompt]), ['outcome' => 'team_a_win']);

        $response->assertStatus(403);
    }
}
