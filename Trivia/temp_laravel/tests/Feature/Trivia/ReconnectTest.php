<?php

namespace Tests\Feature\Trivia;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Player;
use App\Models\PlayerAnswer;
use App\Models\Question;
use App\Models\QuestionOption;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithPlayers;
use Tests\TestCase;

class ReconnectTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithPlayers;

    public function test_refreshing_mid_question_restores_the_active_prompt_and_score(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::QuestionLive]);
        $question = Question::factory()->for($game)->create(['started_at' => now(), 'duration_seconds' => 20]);
        $game->update(['current_question_id' => $question->id]);
        $player = Player::factory()->for($game)->create(['total_score' => 450, 'current_rank' => 2]);

        $response = $this->actingAsPlayer($game, $player)->get(route('play.show', $game));

        $response->assertOk();
        $response->assertViewHas('initialState', function (array $state) use ($question, $player) {
            return $state['game_status'] === 'question_live'
                && $state['active_prompt']['prompt_id'] === $question->id
                && $state['player']['total_score'] === 450
                && $state['player']['current_rank'] === 2;
        });
    }

    public function test_refreshing_after_answering_shows_the_existing_answer_and_prevents_resubmission(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::QuestionLive]);
        $question = Question::factory()->for($game)->create(['started_at' => now(), 'duration_seconds' => 20]);
        $game->update(['current_question_id' => $question->id]);
        $option = QuestionOption::factory()->for($question)->correct()->create();
        $player = Player::factory()->for($game)->create();
        PlayerAnswer::create([
            'game_id' => $game->id,
            'player_id' => $player->id,
            'question_id' => $question->id,
            'question_option_id' => $option->id,
            'is_correct' => true,
            'total_points' => 950,
            'review_status' => 'auto_scored',
            'answered_at' => now(),
        ]);

        // The page load restores the existing answer...
        $page = $this->actingAsPlayer($game, $player)->get(route('play.show', $game));
        $page->assertOk();
        $page->assertViewHas('initialState', function (array $state) use ($option) {
            return $state['existing_answer']->question_option_id === $option->id;
        });

        // ...and the server still rejects a second submission after "refresh."
        $resubmit = $this->actingAsPlayer($game, $player)
            ->postJson(route('play.answer', [$game, $question]), ['option_id' => $option->id]);
        $resubmit->assertStatus(422);
    }

    public function test_player_score_persists_across_reconnect_via_the_same_join_token(): void
    {
        $game = Game::factory()->create();
        $player = Player::factory()->for($game)->create(['total_score' => 1200, 'current_streak' => 3]);

        $first = $this->actingAsPlayer($game, $player)->get(route('play.show', $game));
        $first->assertOk();

        // Simulate a fresh page load (new request, same cookie) — score must be identical.
        $second = $this->actingAsPlayer($game, $player)->get(route('play.show', $game));
        $second->assertOk();
        $second->assertViewHas('initialState', function (array $state) {
            return $state['player']['total_score'] === 1200 && $state['player']['current_streak'] === 3;
        });
    }

    public function test_unregistered_visitor_is_redirected_to_join_instead_of_seeing_the_game(): void
    {
        $game = Game::factory()->create();

        $response = $this->get(route('play.show', $game));

        $response->assertRedirect(route('join.show', $game));
    }

    public function test_site_root_redirects_to_admin_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }
}
