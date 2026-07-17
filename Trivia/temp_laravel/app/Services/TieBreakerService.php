<?php

namespace App\Services;

use App\Models\Player;
use Illuminate\Database\Eloquent\Collection;

class TieBreakerService
{
    /**
     * Sort players using the trivia tie-breaker rules:
     * 1. Total score (descending)
     * 2. Correct answers count (descending)
     * 3. Total correct response time (ascending - faster is better)
     * 4. Earlier submission time (ascending)
     *
     * @param Collection<Player> $players
     * @return Collection<Player>
     */
    public function sortTriviaPlayers(Collection $players): Collection
    {
        return $players->sortBy([
            ['total_score', 'desc'],
            ['correct_answers', 'desc'],
            ['total_correct_response_ms', 'asc'],
            ['created_at', 'asc'],
        ])->values();
    }

    /**
     * Check if sudden death is required for the top positions.
     * Returns true if there's a tie at the top that cannot be broken by standard tie-breakers.
     */
    public function requiresSuddenDeath(Collection $players, int $topN = 1): bool
    {
        if ($players->count() < $topN + 1) {
            return false;
        }

        $sorted = $this->sortTriviaPlayers($players);
        $cutoffPlayer = $sorted[$topN - 1] ?? null;
        $nextPlayer = $sorted[$topN] ?? null;

        if (!$cutoffPlayer || !$nextPlayer) {
            return false;
        }

        // Check if the cutoff player and next player have identical tie-breaker values
        return $cutoffPlayer->total_score === $nextPlayer->total_score
            && $cutoffPlayer->correct_answers === $nextPlayer->correct_answers
            && $cutoffPlayer->total_correct_response_ms === $nextPlayer->total_correct_response_ms;
    }

    /**
     * Apply score tie-breakers to determine final rankings.
     * This is used when standard tie-breakers aren't sufficient (e.g., for prediction winners).
     *
     * @param Collection<Player> $players
     * @return array - Array of player IDs in winning order
     */
    public function applyScoreTieBreakers(Collection $players): array
    {
        return $this->sortTriviaPlayers($players)
            ->pluck('id')
            ->toArray();
    }

    /**
     * Calculate the points gap between two players.
     */
    public function calculatePointsGap(Player $higher, Player $lower): int
    {
        return ($higher->total_score ?? 0) - ($lower->total_score ?? 0);
    }

    /**
     * Determine rank movement (up, down, or same).
     */
    public function getRankMovement(?int $previousRank, ?int $currentRank): string
    {
        if ($previousRank === null || $currentRank === null) {
            return 'same';
        }

        if ($currentRank < $previousRank) {
            return 'up';
        } elseif ($currentRank > $previousRank) {
            return 'down';
        }

        return 'same';
    }

    /**
     * Get the rank movement difference.
     */
    public function getRankMovementDifference(?int $previousRank, ?int $currentRank): int
    {
        if ($previousRank === null || $currentRank === null) {
            return 0;
        }

        return $previousRank - $currentRank;
    }
}
