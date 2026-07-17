<?php

namespace App\Services;

use App\Models\Game;
use App\Models\Prediction;
use Illuminate\Support\Facades\DB;

/**
 * Ranks Fan Prediction participants. Trivia and prediction leaderboards are
 * kept completely separate — this only reads the predictions table, never
 * player_answers.
 */
class PredictionLeaderboardService
{
    /**
     * Sort order follows the prediction winner rules from plans/design.md:
     *   points desc
     *   exact_score_correct desc
     *   correct_outcome desc
     *   goal_difference asc (closest score)
     *   correct_first_goalscorer desc
     *   correct_player_of_match desc
     *   first_goal_minute_diff asc (closest minute)
     *   submitted_at asc (earliest submission)
     */
    public function updateRanks(Game $game): void
    {
        $predictions = $game->predictions()
            ->whereNotNull('submitted_at')
            ->orderByDesc('points')
            ->orderByDesc('exact_score_correct')
            ->orderByDesc('correct_outcome')
            ->orderBy('goal_difference')
            ->orderByDesc('correct_first_goalscorer')
            ->orderByDesc('correct_player_of_match')
            ->orderBy('first_goal_minute_diff')
            ->orderBy('submitted_at')
            ->get();

        DB::transaction(function () use ($predictions) {
            $rank = 0;
            $previous = null;

            foreach ($predictions as $prediction) {
                $isTie = $previous !== null
                    && $prediction->points === $previous['points']
                    && $prediction->exact_score_correct === $previous['exact_score_correct']
                    && $prediction->correct_outcome === $previous['correct_outcome']
                    && $prediction->goal_difference === $previous['goal_difference']
                    && $prediction->correct_first_goalscorer === $previous['correct_first_goalscorer']
                    && $prediction->correct_player_of_match === $previous['correct_player_of_match']
                    && $prediction->first_goal_minute_diff === $previous['first_goal_minute_diff'];

                if ($isTie) {
                    $prediction->winner_rank = $rank;
                } else {
                    $rank++;
                    $prediction->winner_rank = $rank;
                }

                $prediction->save();

                $previous = [
                    'points' => $prediction->points,
                    'exact_score_correct' => $prediction->exact_score_correct,
                    'correct_outcome' => $prediction->correct_outcome,
                    'goal_difference' => $prediction->goal_difference,
                    'correct_first_goalscorer' => $prediction->correct_first_goalscorer,
                    'correct_player_of_match' => $prediction->correct_player_of_match,
                    'first_goal_minute_diff' => $prediction->first_goal_minute_diff,
                ];
            }
        });
    }

    public function getTop10(Game $game): array
    {
        return $game->predictions()
            ->whereNotNull('submitted_at')
            ->with('player')
            ->orderByDesc('points')
            ->orderByDesc('exact_score_correct')
            ->orderByDesc('correct_outcome')
            ->orderBy('goal_difference')
            ->orderByDesc('correct_first_goalscorer')
            ->orderByDesc('correct_player_of_match')
            ->orderBy('first_goal_minute_diff')
            ->orderBy('submitted_at')
            ->limit(10)
            ->get(['id', 'player_id', 'points', 'winner_rank', 'exact_score_correct', 'correct_outcome', 'correct_first_goalscorer', 'correct_player_of_match'])
            ->map(function (Prediction $p) {
                return [
                    'player_id' => $p->player_id,
                    'nickname' => $p->player?->nickname,
                    'points' => $p->points ?? 0,
                    'winner_rank' => $p->winner_rank,
                    'exact_score_correct' => $p->exact_score_correct,
                    'correct_outcome' => $p->correct_outcome,
                    'correct_first_goalscorer' => $p->correct_first_goalscorer,
                    'correct_player_of_match' => $p->correct_player_of_match,
                ];
            })
            ->toArray();
    }

    public function getPodium(Game $game): array
    {
        return $game->predictions()
            ->whereNotNull('submitted_at')
            ->with('player')
            ->orderByDesc('points')
            ->orderByDesc('exact_score_correct')
            ->orderByDesc('correct_outcome')
            ->orderBy('goal_difference')
            ->orderByDesc('correct_first_goalscorer')
            ->orderByDesc('correct_player_of_match')
            ->orderBy('first_goal_minute_diff')
            ->orderBy('submitted_at')
            ->limit(3)
            ->get(['id', 'player_id', 'points', 'winner_rank'])
            ->map(function (Prediction $p) {
                return [
                    'player_id' => $p->player_id,
                    'nickname' => $p->player?->nickname,
                    'points' => $p->points ?? 0,
                    'winner_rank' => $p->winner_rank,
                ];
            })
            ->toArray();
    }

    public function getPlayerResult(Game $game, \App\Models\Player $player): array
    {
        $this->updateRanks($game);

        $prediction = $game->predictions()->where('player_id', $player->id)->first();

        return [
            'has_prediction' => (bool) $prediction,
            'points' => $prediction?->points ?? 0,
            'winner_rank' => $prediction?->winner_rank,
        ];
    }
}
