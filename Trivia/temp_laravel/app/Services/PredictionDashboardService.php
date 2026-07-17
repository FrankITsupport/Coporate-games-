<?php

namespace App\Services;

use App\Models\Game;
use App\Models\PredictionPlayer;
use Illuminate\Support\Collection;

/**
 * Aggregates submitted prediction answers into safe, public display data for
 * the stage dashboard. Only enabled prediction prompts are counted, and no
 * internal team names or player PII leak through.
 */
class PredictionDashboardService
{
    public function getAggregates(Game $game): array
    {
        $setting = $game->predictionSetting;
        $prompts = $game->predictionPrompts()->enabled()->get()->keyBy('id');
        $answers = $game->predictionAnswers()->get()->groupBy('prediction_prompt_id');

        $players = $game->predictionPlayers()->get()->keyBy('id');

        $outcomes   = [];
        $scorelines = [];
        $goalscorer = [];
        $potm       = [];
        $minutes    = [];

        foreach ($prompts as $prompt) {
            $list = $answers->get($prompt->id, collect());

            foreach ($list as $answer) {
                $payload = $answer->answer_payload ?? [];
                if (! is_array($payload)) {
                    continue;
                }

                switch ($prompt->prompt_key) {
                    case 'match_outcome':
                        $v = $payload['outcome'] ?? null;
                        if ($v) {
                            $outcomes[$v] = ($outcomes[$v] ?? 0) + 1;
                        }
                        break;

                    case 'final_score':
                        $a = $payload['team_a_score'] ?? null;
                        $b = $payload['team_b_score'] ?? null;
                        if ($a !== null && $b !== null) {
                            $key = "{$a}-{$b}";
                            $scorelines[$key] = ($scorelines[$key] ?? 0) + 1;
                        }
                        break;

                    case 'first_goalscorer':
                        $id = $payload['player_id'] ?? null;
                        $name = $this->playerName($players, $id);
                        if ($name) {
                            $goalscorer[$name] = ($goalscorer[$name] ?? 0) + 1;
                        }
                        break;

                    case 'player_of_match':
                        $id = $payload['player_id'] ?? null;
                        $name = $this->playerName($players, $id);
                        if ($name) {
                            $potm[$name] = ($potm[$name] ?? 0) + 1;
                        }
                        break;

                    case 'first_goal_minute':
                        $m = $payload['minute'] ?? null;
                        if ($m !== null) {
                            $minutes[(int) $m] = ($minutes[(int) $m] ?? 0) + 1;
                        }
                        break;
                }
            }
        }

        arsort($scorelines);
        arsort($goalscorer);
        arsort($potm);
        ksort($minutes);

        return [
            'submission_count' => $game->predictions()->whereNotNull('submitted_at')->count(),
            'outcomes'         => $outcomes,
            'scorelines'       => $scorelines,
            'first_goalscorer' => $goalscorer,
            'player_of_match'  => $potm,
            'first_goal_minute'=> $minutes,
            'flags'            => [
                'team_a_flag' => $setting?->team_a_flag,
                'team_b_flag' => $setting?->team_b_flag,
                'team_a_name' => $setting?->team_a_name,
                'team_b_name' => $setting?->team_b_name,
            ],
        ];
    }

    private function playerName(Collection $players, ?int $id): ?string
    {
        if (! $id) {
            return null;
        }

        $player = $players->get($id);

        return $player ? $player->display_name : null;
    }
}
