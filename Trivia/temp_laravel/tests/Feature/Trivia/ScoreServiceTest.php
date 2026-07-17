<?php

namespace Tests\Feature\Trivia;

use App\Models\Game;
use App\Models\Player;
use App\Models\PlayerAnswer;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Services\ScoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ScoreServiceTest extends TestCase
{
    use RefreshDatabase;

    private function submittedAnswer(Question $question, Player $player, ?QuestionOption $option, ?string $text = null, int $elapsedMs = 1000): PlayerAnswer
    {
        return PlayerAnswer::create([
            'game_id' => $question->game_id,
            'player_id' => $player->id,
            'question_id' => $question->id,
            'question_option_id' => $option?->id,
            'answer_text' => $text,
            'review_status' => 'auto_scored',
            'answered_at' => $question->started_at->clone()->addMilliseconds($elapsedMs),
        ]);
    }

    public function test_correct_answer_scores_base_plus_speed_bonus(): void
    {
        $game = Game::factory()->create();
        $player = Player::factory()->for($game)->create();
        $question = Question::factory()->for($game)->create(['duration_seconds' => 20, 'started_at' => now()]);
        $correct = QuestionOption::factory()->for($question)->correct()->create();

        // Answered after 1s of a 20s window: speed_bonus = 200 * (19/20) = 190
        $answer = $this->submittedAnswer($question, $player, $correct, elapsedMs: 1000);

        $scored = app(ScoreService::class)->scoreAnswer($answer);

        $this->assertTrue($scored->is_correct);
        $this->assertSame(800, $scored->base_points);
        $this->assertSame(190, $scored->speed_bonus);
        $this->assertSame(0, $scored->streak_bonus); // first correct answer, streak = 1, no bonus yet
        $this->assertSame(990, $scored->total_points);
        $this->assertSame(990, $player->fresh()->total_score);
    }

    public function test_double_points_question_doubles_base_and_speed(): void
    {
        $game = Game::factory()->create();
        $player = Player::factory()->for($game)->create();
        $question = Question::factory()->for($game)->create([
            'duration_seconds' => 20,
            'started_at' => now(),
            'is_double_points' => true,
        ]);
        $correct = QuestionOption::factory()->for($question)->correct()->create();

        $answer = $this->submittedAnswer($question, $player, $correct, elapsedMs: 1000);
        $scored = app(ScoreService::class)->scoreAnswer($answer);

        $this->assertSame(1600, $scored->base_points);
        $this->assertSame(380, $scored->speed_bonus); // 400 * (19/20)
        $this->assertSame(1, $player->fresh()->double_point_correct_answers);
    }

    public function test_incorrect_answer_scores_zero_and_resets_streak(): void
    {
        $game = Game::factory()->create();
        $player = Player::factory()->for($game)->create(['current_streak' => 3]);
        $question = Question::factory()->for($game)->create(['duration_seconds' => 20, 'started_at' => now()]);
        QuestionOption::factory()->for($question)->correct()->create();
        $wrong = QuestionOption::factory()->for($question)->create();

        $answer = $this->submittedAnswer($question, $player, $wrong);
        $scored = app(ScoreService::class)->scoreAnswer($answer);

        $this->assertFalse($scored->is_correct);
        $this->assertSame(0, $scored->total_points);
        $this->assertSame(0, $player->fresh()->current_streak);
        $this->assertSame(1, $player->fresh()->incorrect_answers);
    }

    #[DataProvider('streakBonusProvider')]
    public function test_streak_bonus_table(int $priorStreak, int $expectedBonus): void
    {
        $game = Game::factory()->create();
        $player = Player::factory()->for($game)->create(['current_streak' => $priorStreak]);
        $question = Question::factory()->for($game)->create(['duration_seconds' => 20, 'started_at' => now()]);
        $correct = QuestionOption::factory()->for($question)->correct()->create();

        $answer = $this->submittedAnswer($question, $player, $correct);
        $scored = app(ScoreService::class)->scoreAnswer($answer);

        $this->assertSame($expectedBonus, $scored->streak_bonus);
    }

    public static function streakBonusProvider(): array
    {
        return [
            'streak becomes 1 (first correct)' => [0, 0],
            'streak becomes 2' => [1, 50],
            'streak becomes 3' => [2, 100],
            'streak becomes 4' => [3, 150],
            'streak becomes 5' => [4, 200],
            'streak becomes 6 (still capped at 200)' => [5, 200],
        ];
    }

    public function test_missed_answer_scores_zero_and_resets_streak(): void
    {
        $game = Game::factory()->create();
        $player = Player::factory()->for($game)->create(['current_streak' => 2]);
        $question = Question::factory()->for($game)->create(['duration_seconds' => 20, 'started_at' => now(), 'closed_at' => now()]);

        app(ScoreService::class)->scoreMissedAnswers($question);

        $this->assertDatabaseHas('player_answers', [
            'player_id' => $player->id,
            'question_id' => $question->id,
            'is_correct' => false,
            'total_points' => 0,
        ]);
        $this->assertSame(0, $player->fresh()->current_streak);
        $this->assertSame(1, $player->fresh()->missed_answers);
    }

    public function test_open_text_matches_case_insensitively_and_trims_whitespace(): void
    {
        $game = Game::factory()->create();
        $player = Player::factory()->for($game)->create();
        $question = Question::factory()->for($game)->create([
            'question_type' => 'open_text',
            'accepted_answers' => ['Paris'],
            'duration_seconds' => 20,
            'started_at' => now(),
        ]);

        $answer = $this->submittedAnswer($question, $player, null, text: '  paris  ');
        $scored = app(ScoreService::class)->scoreAnswer($answer);

        $this->assertTrue($scored->is_correct);
    }

    public function test_open_text_without_manual_review_scores_incorrect_on_no_match(): void
    {
        $game = Game::factory()->create();
        $player = Player::factory()->for($game)->create();
        $question = Question::factory()->for($game)->create([
            'question_type' => 'open_text',
            'accepted_answers' => ['Paris'],
            'duration_seconds' => 20,
            'started_at' => now(),
            'requires_manual_review' => false,
        ]);

        $answer = $this->submittedAnswer($question, $player, null, text: 'London');
        $scored = app(ScoreService::class)->scoreAnswer($answer);

        $this->assertFalse($scored->is_correct);
        $this->assertSame('auto_scored', $scored->review_status);
    }

    public function test_open_text_manual_review_defers_scoring_until_admin_decides(): void
    {
        $game = Game::factory()->create();
        $player = Player::factory()->for($game)->create();
        $question = Question::factory()->for($game)->create([
            'question_type' => 'open_text',
            'accepted_answers' => ['Paris'],
            'duration_seconds' => 20,
            'started_at' => now(),
            'requires_manual_review' => true,
        ]);

        $answer = $this->submittedAnswer($question, $player, null, text: 'Pariss');
        $scored = app(ScoreService::class)->scoreAnswer($answer);

        $this->assertSame('needs_review', $scored->review_status);
        $this->assertSame(0, $scored->total_points);
        // Nothing about the player should change yet — scoring is fully deferred.
        $this->assertSame(0, $player->fresh()->total_score);
        $this->assertSame(0, $player->fresh()->correct_answers);
        $this->assertSame(0, $player->fresh()->incorrect_answers);
    }

    public function test_manual_review_approve_scores_the_answer_as_correct(): void
    {
        $game = Game::factory()->create();
        $player = Player::factory()->for($game)->create();
        $question = Question::factory()->for($game)->create([
            'question_type' => 'open_text',
            'accepted_answers' => ['Paris'],
            'duration_seconds' => 20,
            'started_at' => now(),
            'requires_manual_review' => true,
        ]);
        $answer = $this->submittedAnswer($question, $player, null, text: 'Pariss', elapsedMs: 2000);
        app(ScoreService::class)->scoreAnswer($answer);

        $approved = app(ScoreService::class)->approveManualReview($answer->fresh());

        $this->assertTrue($approved->is_correct);
        $this->assertSame('approved', $approved->review_status);
        $this->assertSame(800 + 180, $approved->total_points); // 200*(18/20)=180 speed bonus
        $this->assertSame($approved->total_points, $player->fresh()->total_score);
        $this->assertSame(1, $player->fresh()->correct_answers);
    }

    public function test_manual_review_reject_scores_the_answer_as_incorrect(): void
    {
        $game = Game::factory()->create();
        $player = Player::factory()->for($game)->create();
        $question = Question::factory()->for($game)->create([
            'question_type' => 'open_text',
            'accepted_answers' => ['Paris'],
            'duration_seconds' => 20,
            'started_at' => now(),
            'requires_manual_review' => true,
        ]);
        $answer = $this->submittedAnswer($question, $player, null, text: 'Nowhere');
        app(ScoreService::class)->scoreAnswer($answer);

        $rejected = app(ScoreService::class)->rejectManualReview($answer->fresh());

        $this->assertFalse($rejected->is_correct);
        $this->assertSame('rejected', $rejected->review_status);
        $this->assertSame(0, $rejected->total_points);
        $this->assertSame(0, $player->fresh()->total_score);
        $this->assertSame(1, $player->fresh()->incorrect_answers);
    }

    public function test_scores_are_whole_numbers(): void
    {
        $game = Game::factory()->create();
        $player = Player::factory()->for($game)->create();
        $question = Question::factory()->for($game)->create(['duration_seconds' => 17, 'started_at' => now()]);
        $correct = QuestionOption::factory()->for($question)->correct()->create();

        // 17s window, answered after 3.333s — forces a non-integer speed bonus before rounding.
        $answer = $this->submittedAnswer($question, $player, $correct, elapsedMs: 3333);
        $scored = app(ScoreService::class)->scoreAnswer($answer);

        $this->assertIsInt($scored->speed_bonus);
        $this->assertIsInt($scored->total_points);
    }

    public function test_total_score_accumulates_across_multiple_questions(): void
    {
        $game = Game::factory()->create();
        $player = Player::factory()->for($game)->create();

        $q1 = Question::factory()->for($game)->create(['duration_seconds' => 20, 'started_at' => now()]);
        $opt1 = QuestionOption::factory()->for($q1)->correct()->create();
        $scored1 = app(ScoreService::class)->scoreAnswer($this->submittedAnswer($q1, $player, $opt1));

        $q2 = Question::factory()->for($game)->create(['duration_seconds' => 20, 'started_at' => now()]);
        $opt2 = QuestionOption::factory()->for($q2)->correct()->create();
        $scored2 = app(ScoreService::class)->scoreAnswer($this->submittedAnswer($q2, $player, $opt2));

        $this->assertSame($scored1->total_points + $scored2->total_points, $player->fresh()->total_score);
        $this->assertSame(2, $player->fresh()->current_streak);
    }
}
