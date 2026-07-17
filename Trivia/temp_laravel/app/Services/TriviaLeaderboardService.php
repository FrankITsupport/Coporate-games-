<?php

namespace App\Services;

use App\Models\Game;
use App\Models\Player;
use Illuminate\Support\Facades\DB;

class TriviaLeaderboardService
{
    /**
     * Recalculate and update ranks for all active players in a game.
     * Uses tie-breakers: total_score DESC, correct_answers DESC, total_correct_response_ms ASC
     */
    public function updateRanks(Game $game): void
    {
        $players = $game->players()
            ->active()
            ->orderByDesc('total_score')
            ->orderByDesc('correct_answers')
            ->orderBy('total_correct_response_ms')
            ->get();

        DB::transaction(function () use ($players) {
            $rank = 0;
            $previousScore = null;
            $previousCorrect = null;
            $previousResponseTime = null;

            foreach ($players as $player) {
                // Store previous rank before updating
                $previousRank = $player->current_rank;

                // Determine if this player ties with the previous one
                $isTie = $previousScore !== null
                    && $player->total_score === $previousScore
                    && $player->correct_answers === $previousCorrect
                    && $player->total_correct_response_ms === $previousResponseTime;

                if ($isTie) {
                    // Same rank as previous player
                    $player->current_rank = $rank;
                } else {
                    // New rank
                    $rank++;
                    $player->current_rank = $rank;
                }

                $player->previous_rank = $previousRank;
                $player->save();

                // Store current values for next iteration comparison
                $previousScore = $player->total_score;
                $previousCorrect = $player->correct_answers;
                $previousResponseTime = $player->total_correct_response_ms;
            }
        });
    }

    /**
     * Get top 10 players for the leaderboard.
     */
    public function getTop10(Game $game): array
    {
        return $game->players()
            ->active()
            ->orderByDesc('total_score')
            ->orderByDesc('correct_answers')
            ->orderBy('total_correct_response_ms')
            ->limit(10)
            ->get(['id', 'nickname', 'total_score', 'current_rank', 'previous_rank', 'current_streak'])
            ->map(fn ($p) => [
                'id' => $p->id,
                'nickname' => $p->nickname,
                'total_score' => $p->total_score ?? 0,
                'current_rank' => $p->current_rank,
                'previous_rank' => $p->previous_rank,
                'current_streak' => $p->current_streak ?? 0,
            ])
            ->toArray();
    }

    /**
     * Get top 3 players for the podium.
     */
    public function getPodium(Game $game): array
    {
        return $game->players()
            ->active()
            ->orderByDesc('total_score')
            ->orderByDesc('correct_answers')
            ->orderBy('total_correct_response_ms')
            ->limit(3)
            ->get(['id', 'nickname', 'total_score'])
            ->map(fn ($p) => [
                'id' => $p->id,
                'nickname' => $p->nickname,
                'total_score' => $p->total_score ?? 0,
            ])
            ->toArray();
    }

    /**
     * Get a specific player's rank information.
     */
    public function getPlayerRank(Game $game, Player $player): array
    {
        $this->updateRanks($game);
        $player->refresh();

        // Calculate points gap to player above
        $gap = null;
        if ($player->current_rank > 1) {
            $playerAbove = $game->players()
                ->active()
                ->where('id', '!=', $player->id)
                ->orderByDesc('total_score')
                ->orderByDesc('correct_answers')
                ->orderBy('total_correct_response_ms')
                ->first();

            if ($playerAbove) {
                $gap = $playerAbove->total_score - $player->total_score;
            }
        }

        return [
            'current_rank' => $player->current_rank,
            'previous_rank' => $player->previous_rank,
            'points_gap_to_above' => $gap,
        ];
    }
}
