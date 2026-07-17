<?php

namespace Tests\Feature\Trivia;

use App\Models\Game;
use App\Models\Player;
use App\Services\TieBreakerService;
use App\Services\TriviaLeaderboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaderboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_leaderboard_sorts_by_score_then_correct_answers_then_speed(): void
    {
        $game = Game::factory()->create();

        $highest = Player::factory()->for($game)->create(['total_score' => 300, 'correct_answers' => 3, 'total_correct_response_ms' => 5000]);
        $tiedButMoreCorrect = Player::factory()->for($game)->create(['total_score' => 200, 'correct_answers' => 5, 'total_correct_response_ms' => 9000]);
        $tiedButFewerCorrect = Player::factory()->for($game)->create(['total_score' => 200, 'correct_answers' => 2, 'total_correct_response_ms' => 1000]);
        $lowest = Player::factory()->for($game)->create(['total_score' => 100, 'correct_answers' => 1, 'total_correct_response_ms' => 500]);

        app(TriviaLeaderboardService::class)->updateRanks($game);

        $this->assertSame(1, $highest->fresh()->current_rank);
        $this->assertSame(2, $tiedButMoreCorrect->fresh()->current_rank);
        $this->assertSame(3, $tiedButFewerCorrect->fresh()->current_rank);
        $this->assertSame(4, $lowest->fresh()->current_rank);
    }

    public function test_true_tie_gets_the_same_rank(): void
    {
        $game = Game::factory()->create();

        $a = Player::factory()->for($game)->create(['total_score' => 100, 'correct_answers' => 2, 'total_correct_response_ms' => 1000]);
        $b = Player::factory()->for($game)->create(['total_score' => 100, 'correct_answers' => 2, 'total_correct_response_ms' => 1000]);

        app(TriviaLeaderboardService::class)->updateRanks($game);

        $this->assertSame($a->fresh()->current_rank, $b->fresh()->current_rank);
    }

    public function test_disqualified_players_are_excluded_from_the_leaderboard(): void
    {
        $game = Game::factory()->create();
        $active = Player::factory()->for($game)->create(['total_score' => 100]);
        $disqualified = Player::factory()->for($game)->create(['total_score' => 999, 'disqualified_at' => now()]);

        $top = app(TriviaLeaderboardService::class)->getTop10($game);

        $ids = array_column($top, 'id');
        $this->assertContains($active->id, $ids);
        $this->assertNotContains($disqualified->id, $ids);
    }

    public function test_top10_returns_at_most_ten_players(): void
    {
        $game = Game::factory()->create();
        Player::factory()->for($game)->count(15)->create();

        $top = app(TriviaLeaderboardService::class)->getTop10($game);

        $this->assertCount(10, $top);
    }

    public function test_podium_returns_at_most_three_players(): void
    {
        $game = Game::factory()->create();
        Player::factory()->for($game)->count(5)->create();

        $podium = app(TriviaLeaderboardService::class)->getPodium($game);

        $this->assertCount(3, $podium);
    }

    public function test_tie_breaker_service_sort_order_matches_leaderboard_service(): void
    {
        $game = Game::factory()->create();
        $best = Player::factory()->for($game)->create(['total_score' => 300, 'correct_answers' => 3, 'total_correct_response_ms' => 5000]);
        $worst = Player::factory()->for($game)->create(['total_score' => 100, 'correct_answers' => 1, 'total_correct_response_ms' => 500]);

        $sorted = app(TieBreakerService::class)->sortTriviaPlayers($game->players()->get());

        $this->assertSame($best->id, $sorted->first()->id);
        $this->assertSame($worst->id, $sorted->last()->id);
    }

    public function test_sudden_death_is_required_when_top_players_are_fully_tied(): void
    {
        $game = Game::factory()->create();
        Player::factory()->for($game)->create(['total_score' => 100, 'correct_answers' => 2, 'total_correct_response_ms' => 1000]);
        Player::factory()->for($game)->create(['total_score' => 100, 'correct_answers' => 2, 'total_correct_response_ms' => 1000]);

        $requiresSuddenDeath = app(TieBreakerService::class)->requiresSuddenDeath($game->players()->get(), topN: 1);

        $this->assertTrue($requiresSuddenDeath);
    }

    public function test_sudden_death_is_not_required_when_tie_breakers_resolve_it(): void
    {
        $game = Game::factory()->create();
        Player::factory()->for($game)->create(['total_score' => 100, 'correct_answers' => 3, 'total_correct_response_ms' => 1000]);
        Player::factory()->for($game)->create(['total_score' => 100, 'correct_answers' => 2, 'total_correct_response_ms' => 1000]);

        $requiresSuddenDeath = app(TieBreakerService::class)->requiresSuddenDeath($game->players()->get(), topN: 1);

        $this->assertFalse($requiresSuddenDeath);
    }
}
