<?php

namespace Tests\Feature\Trivia;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Player;
use App\Models\PlayerAnswer;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_start_a_question(): void
    {
        $admin = User::factory()->create();
        $game = Game::factory()->create(['status' => GameStatus::Waiting]);
        $question = Question::factory()->for($game)->create();

        $response = $this->actingAs($admin)->post(route('admin.games.questions.start', [$game, $question]));

        $response->assertRedirect();
        $game->refresh();
        $this->assertSame(GameStatus::QuestionLive, $game->status);
        $this->assertSame($question->id, $game->current_question_id);
        $this->assertNotNull($question->fresh()->started_at);
    }

    public function test_starting_a_question_clears_prior_answers_for_a_genuine_restart(): void
    {
        $admin = User::factory()->create();
        $game = Game::factory()->create(['status' => GameStatus::QuestionClosed]);
        $question = Question::factory()->for($game)->create(['started_at' => now()->subMinute(), 'closed_at' => now()]);
        $player = Player::factory()->for($game)->create();
        PlayerAnswer::create([
            'game_id' => $game->id,
            'player_id' => $player->id,
            'question_id' => $question->id,
            'review_status' => 'auto_scored',
            'answered_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('admin.games.questions.start', [$game, $question]));

        $this->assertDatabaseCount('player_answers', 0);
    }

    public function test_admin_cannot_start_a_question_from_an_invalid_state(): void
    {
        $admin = User::factory()->create();
        $game = Game::factory()->create(['status' => GameStatus::Draft]);
        $question = Question::factory()->for($game)->create();

        $response = $this->actingAs($admin)->post(route('admin.games.questions.start', [$game, $question]));

        $response->assertSessionHasErrors('status');
        $this->assertSame(GameStatus::Draft, $game->fresh()->status);
    }

    public function test_admin_can_close_the_live_question_and_missed_answers_are_scored(): void
    {
        $admin = User::factory()->create();
        $game = Game::factory()->create(['status' => GameStatus::QuestionLive]);
        $question = Question::factory()->for($game)->create(['started_at' => now(), 'closed_at' => null]);
        $game->update(['current_question_id' => $question->id]);
        $player = Player::factory()->for($game)->create(['current_streak' => 2]);

        $response = $this->actingAs($admin)->post(route('admin.games.questions.close', [$game, $question]));

        $response->assertRedirect();
        $this->assertSame(GameStatus::QuestionClosed, $game->fresh()->status);
        $this->assertNotNull($question->fresh()->closed_at);
        $this->assertDatabaseHas('player_answers', [
            'player_id' => $player->id,
            'question_id' => $question->id,
            'is_correct' => false,
        ]);
        $this->assertSame(0, $player->fresh()->current_streak);
    }

    public function test_admin_cannot_close_a_question_that_is_not_live(): void
    {
        $admin = User::factory()->create();
        $game = Game::factory()->create(['status' => GameStatus::Waiting]);
        $question = Question::factory()->for($game)->create();

        $response = $this->actingAs($admin)->post(route('admin.games.questions.close', [$game, $question]));

        $response->assertSessionHasErrors('status');
    }

    public function test_admin_can_reveal_the_answer_after_closing(): void
    {
        $admin = User::factory()->create();
        $game = Game::factory()->create(['status' => GameStatus::QuestionClosed]);
        $question = Question::factory()->for($game)->create(['closed_at' => now()]);
        $game->update(['current_question_id' => $question->id]);

        $response = $this->actingAs($admin)->post(route('admin.games.questions.reveal', [$game, $question]));

        $response->assertRedirect();
        $this->assertSame(GameStatus::AnswerReveal, $game->fresh()->status);
        $this->assertNotNull($question->fresh()->revealed_at);
    }
}
