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

class AnswerSubmissionTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithPlayers;

    private function liveMultipleChoiceQuestion(Game $game): Question
    {
        $question = Question::factory()->for($game)->create([
            'duration_seconds' => 20,
            'started_at' => now(),
            'closed_at' => null,
        ]);
        QuestionOption::factory()->for($question)->correct()->create(['option_text' => 'Right']);
        QuestionOption::factory()->for($question)->create(['option_text' => 'Wrong']);
        QuestionOption::factory()->for($question)->create(['option_text' => 'Disabled', 'is_enabled' => false]);

        $game->update(['status' => GameStatus::QuestionLive, 'current_question_id' => $question->id]);

        return $question->fresh();
    }

    public function test_player_can_submit_a_multiple_choice_answer(): void
    {
        $game = Game::factory()->create();
        $player = Player::factory()->for($game)->create();
        $question = $this->liveMultipleChoiceQuestion($game);
        $correctOption = $question->options()->where('is_correct', true)->first();

        $response = $this->actingAsPlayer($game, $player)
            ->postJson(route('play.answer', [$game, $question]), ['option_id' => $correctOption->id]);

        $response->assertOk()->assertJson(['submitted' => true, 'is_correct' => true]);
        $this->assertDatabaseHas('player_answers', [
            'player_id' => $player->id,
            'question_id' => $question->id,
            'question_option_id' => $correctOption->id,
        ]);
    }

    public function test_player_cannot_submit_the_same_question_twice(): void
    {
        $game = Game::factory()->create();
        $player = Player::factory()->for($game)->create();
        $question = $this->liveMultipleChoiceQuestion($game);
        $option = $question->options()->first();

        $this->actingAsPlayer($game, $player)
            ->postJson(route('play.answer', [$game, $question]), ['option_id' => $option->id]);

        $response = $this->actingAsPlayer($game, $player)
            ->postJson(route('play.answer', [$game, $question]), ['option_id' => $option->id]);

        $response->assertStatus(422);
        $this->assertSame(1, PlayerAnswer::where('player_id', $player->id)->where('question_id', $question->id)->count());
    }

    public function test_answer_is_rejected_when_no_question_is_live(): void
    {
        $game = Game::factory()->create(['status' => GameStatus::Waiting]);
        $player = Player::factory()->for($game)->create();
        $question = Question::factory()->for($game)->create();
        QuestionOption::factory()->for($question)->correct()->create();

        $response = $this->actingAsPlayer($game, $player)
            ->postJson(route('play.answer', [$game, $question]), ['option_id' => $question->options()->first()->id]);

        $response->assertStatus(422);
    }

    public function test_answer_is_rejected_for_a_question_that_is_not_the_active_one(): void
    {
        $game = Game::factory()->create();
        $player = Player::factory()->for($game)->create();
        $liveQuestion = $this->liveMultipleChoiceQuestion($game);
        $otherQuestion = Question::factory()->for($game)->create(['started_at' => now()]);
        QuestionOption::factory()->for($otherQuestion)->correct()->create();

        $response = $this->actingAsPlayer($game, $player)
            ->postJson(route('play.answer', [$game, $otherQuestion]), ['option_id' => $otherQuestion->options()->first()->id]);

        $response->assertStatus(422);
    }

    public function test_disabled_option_cannot_be_selected(): void
    {
        $game = Game::factory()->create();
        $player = Player::factory()->for($game)->create();
        $question = $this->liveMultipleChoiceQuestion($game);
        $disabledOption = $question->options()->where('is_enabled', false)->first();

        $response = $this->actingAsPlayer($game, $player)
            ->postJson(route('play.answer', [$game, $question]), ['option_id' => $disabledOption->id]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('player_answers', ['player_id' => $player->id, 'question_id' => $question->id]);
    }

    public function test_late_answer_is_rejected_after_the_timer_expires(): void
    {
        $game = Game::factory()->create();
        $player = Player::factory()->for($game)->create();
        $question = Question::factory()->for($game)->create([
            'duration_seconds' => 10,
            'started_at' => now()->subSeconds(20),
        ]);
        $option = QuestionOption::factory()->for($question)->correct()->create();
        $game->update(['status' => GameStatus::QuestionLive, 'current_question_id' => $question->id]);

        $response = $this->actingAsPlayer($game, $player)
            ->postJson(route('play.answer', [$game, $question]), ['option_id' => $option->id]);

        $response->assertStatus(422);
    }

    public function test_open_text_answer_requires_non_empty_text(): void
    {
        $game = Game::factory()->create();
        $player = Player::factory()->for($game)->create();
        $question = Question::factory()->openText()->for($game)->create([
            'duration_seconds' => 20,
            'started_at' => now(),
        ]);
        $game->update(['status' => GameStatus::QuestionLive, 'current_question_id' => $question->id]);

        $response = $this->actingAsPlayer($game, $player)
            ->postJson(route('play.answer', [$game, $question]), ['answer_text' => '']);

        $response->assertStatus(422);
    }

    public function test_open_text_answer_is_stored_and_matched(): void
    {
        $game = Game::factory()->create();
        $player = Player::factory()->for($game)->create();
        $question = Question::factory()->for($game)->create([
            'question_type' => 'open_text',
            'accepted_answers' => ['Mobile'],
            'duration_seconds' => 20,
            'started_at' => now(),
        ]);
        $game->update(['status' => GameStatus::QuestionLive, 'current_question_id' => $question->id]);

        $response = $this->actingAsPlayer($game, $player)
            ->postJson(route('play.answer', [$game, $question]), ['answer_text' => '  mobile  ']);

        $response->assertOk()->assertJson(['is_correct' => true]);
    }

    public function test_unregistered_visitor_cannot_submit_an_answer(): void
    {
        $game = Game::factory()->create();
        $question = $this->liveMultipleChoiceQuestion($game);

        $response = $this->postJson(route('play.answer', [$game, $question]), [
            'option_id' => $question->options()->first()->id,
        ]);

        $response->assertStatus(403);
    }
}
