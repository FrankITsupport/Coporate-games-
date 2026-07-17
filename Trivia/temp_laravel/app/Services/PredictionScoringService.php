<?php

namespace App\Services;

use App\Models\Game;
use App\Models\Prediction;
use App\Models\PredictionAnswer;
use App\Models\PredictionPrompt;
use App\Models\PredictionSetting;
use Illuminate\Support\Collection;

/**
 * Compares each player's submitted prediction answers against the official
 * match results and awards prediction points. Reads only the prediction tables
 * (predictions, prediction_answers, prediction_settings) — never trivia data.
 */
class PredictionScoringService
{
    public const MINUTE_TOLERANCE = 5;

    /**
     * Score every submitted prediction for a game against the official results
     * and persist the per-player points + breakdown used for ranking.
     */
    public function score(Game $game): void
    {
        $setting = $game->predictionSetting;

        if (! $setting) {
            return;
        }

        $prompts = $game->predictionPrompts()->enabled()->get();
        $keyById = $prompts->mapWithKeys(fn (PredictionPrompt $p) => [$p->id => $p->prompt_key]);

        $answersByPlayer = $game->predictionAnswers()->get()->groupBy('player_id');
        $submittedPlayerIds = $game->predictions()->whereNotNull('submitted_at')->pluck('player_id');

        foreach ($submittedPlayerIds as $playerId) {
            $playerAnswers = $answersByPlayer->get($playerId, collect());
            $answerByKey = $this->answersByKey($playerAnswers, $keyById);

            $result = $this->scorePlayer($setting, $answerByKey);

            $game->predictions()->where('player_id', $playerId)->update([
                'points'                  => $result['points'],
                'exact_score_correct'     => $result['exact_score_correct'],
                'correct_outcome'         => $result['correct_outcome'],
                'correct_first_goalscorer'=> $result['correct_first_goalscorer'],
                'correct_player_of_match' => $result['correct_player_of_match'],
                'first_goal_minute_diff'  => $result['first_goal_minute_diff'],
                'goal_difference'         => $result['goal_difference'],
            ]);
        }

        app(PredictionLeaderboardService::class)->updateRanks($game);
    }

    /**
     * Build (or refresh) the per-player Prediction summary row from their
     * submitted prompt answers. Called on every submission/edit so the summary
     * row always reflects the latest answers even before official scoring.
     */
    public function syncSummary(Game $game, int $playerId, ?\App\Models\Player $player = null): Prediction
    {
        $prompts = $game->predictionPrompts()->enabled()->get();
        $keyById = $prompts->mapWithKeys(fn (PredictionPrompt $p) => [$p->id => $p->prompt_key]);

        $playerAnswers = $game->predictionAnswers()->where('player_id', $playerId)->get();
        $answerByKey = $this->answersByKey($playerAnswers, $keyById);

        $existing = $game->predictions()->where('player_id', $playerId)->first();

        $attributes = [
            'predicted_outcome'           => $answerByKey['match_outcome']['outcome'] ?? null,
            'predicted_team_a_score'      => $answerByKey['final_score']['team_a_score'] ?? null,
            'predicted_team_b_score'      => $answerByKey['final_score']['team_b_score'] ?? null,
            'predicted_first_goalscorer_id' => $answerByKey['first_goalscorer']['player_id'] ?? null,
            'predicted_player_of_match_id'   => $answerByKey['player_of_match']['player_id'] ?? null,
            'predicted_first_goal_minute'    => $answerByKey['first_goal_minute']['minute'] ?? null,
            'submitted_at'   => $existing?->submitted_at ?? now(),
            'last_edited_at' => now(),
        ];

        if ($existing) {
            $existing->update($attributes);

            return $existing->refresh();
        }

        return $game->predictions()->create(array_merge(
            ['player_id' => $playerId],
            $attributes,
        ));
    }

    /**
     * @param Collection<PredictionAnswer> $playerAnswers
     */
    private function answersByKey(Collection $playerAnswers, Collection $keyById): array
    {
        $answerByKey = [];

        foreach ($playerAnswers as $answer) {
            $key = $keyById->get($answer->prediction_prompt_id);
            if ($key) {
                $answerByKey[$key] = is_array($answer->answer_payload) ? $answer->answer_payload : [];
            }
        }

        return $answerByKey;
    }

    private function scorePlayer(PredictionSetting $setting, array $a): array
    {
        $officialA = $setting->official_team_a_score;
        $officialB = $setting->official_team_b_score;

        $outcome = $a['match_outcome']['outcome'] ?? null;
        $outcomeCorrect = $outcome !== null && $outcome === $setting->official_outcome;

        $aScore = $a['final_score']['team_a_score'] ?? null;
        $bScore = $a['final_score']['team_b_score'] ?? null;
        $exactScore = $aScore !== null && $bScore !== null
            && $aScore === $officialA && $bScore === $officialB;

        $goalDifference = ($aScore !== null && $bScore !== null && $officialA !== null && $officialB !== null)
            ? abs(($aScore - $bScore) - ($officialA - $officialB))
            : null;

        $gsId = $a['first_goalscorer']['player_id'] ?? null;
        $gsCorrect = $gsId !== null && $gsId == $setting->official_first_goalscorer_id;

        $potmId = $a['player_of_match']['player_id'] ?? null;
        $potmCorrect = $potmId !== null && $potmId == $setting->official_player_of_match_id;

        $min = $a['first_goal_minute']['minute'] ?? null;
        $minDiff = ($min !== null && $setting->official_first_goal_minute !== null)
            ? abs($min - $setting->official_first_goal_minute)
            : null;

        $minutePoints = 0;
        if ($minDiff !== null) {
            $minutePoints = $minDiff === 0 ? 5 : (($minDiff <= self::MINUTE_TOLERANCE) ? 2 : 0);
        }

        if ($setting->winner_rule === 'closest_score') {
            $points = ($outcomeCorrect ? 2 : 0) + ($gsCorrect ? 3 : 0) + ($potmCorrect ? 3 : 0) + $minutePoints;
        } else {
            $points = ($exactScore ? 5 : 0) + ($outcomeCorrect ? 2 : 0) + ($gsCorrect ? 3 : 0) + ($potmCorrect ? 3 : 0) + $minutePoints;
        }

        return [
            'points'                     => $points,
            'exact_score_correct'        => $exactScore,
            'correct_outcome'            => $outcomeCorrect,
            'correct_first_goalscorer'   => $gsCorrect,
            'correct_player_of_match'    => $potmCorrect,
            'first_goal_minute_diff'     => $minDiff,
            'goal_difference'            => $goalDifference,
        ];
    }
}
