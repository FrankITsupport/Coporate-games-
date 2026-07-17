<?php

namespace App\Http\Controllers;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Services\PredictionDashboardService;
use App\Services\PredictionLeaderboardService;
use App\Services\TriviaLeaderboardService;
use Illuminate\View\View;

class StageController extends Controller
{
    public function show(Game $game): View
    {
        // Build initial state payload for the Vue component
        $activePrompt = null;
        if ($game->status === GameStatus::QuestionLive && $game->current_question_id) {
            $question = $game->currentQuestion()->with('options')->first();
            if ($question && $question->started_at) {
                $activePrompt = $question->toActivePromptPayload();
            }
        }

        // Get initial leaderboard if in leaderboard state
        $leaderboard = [];
        if ($game->status === GameStatus::Leaderboard) {
            $leaderboard = app(TriviaLeaderboardService::class)->getTop10($game);
        }

        // Get initial podium if in podium state
        $podium = [];
        if ($game->status === GameStatus::Podium) {
            $podium = app(TriviaLeaderboardService::class)->getPodium($game);
        }

        // ── Fan Prediction initial state ──
        $predictionCurrent = null;
        if ($game->prediction_status === 'prediction_prompt_live' && $game->current_prediction_prompt_id) {
            $prompt = $game->currentPredictionPrompt()->with('game')->first();
            if ($prompt && $prompt->started_at) {
                $predictionCurrent = $prompt->toActivePromptPayload($game->predictionSetting);
            }
        }

        $showDashboard = in_array($game->prediction_status, [
            'prediction_dashboard', 'predictions_locked', 'official_results_pending', 'prediction_results_ready',
        ], true);

        $prediction = [
            'status'        => $game->prediction_status,
            'current_prompt'=> $predictionCurrent,
            'dashboard'     => $showDashboard ? app(PredictionDashboardService::class)->getAggregates($game) : null,
            'winners'       => $game->prediction_status === 'prediction_results_ready'
                ? app(PredictionLeaderboardService::class)->getTop10($game)
                : null,
            'locked'        => $game->predictionsAreLocked(),
        ];

        $initialState = [
            'game_id'       => $game->id,
            'game_status'   => $game->status->value,
            'active_prompt' => $activePrompt,
            'leaderboard'   => $leaderboard,
            'podium'        => $podium,
            'prediction'    => $prediction,
        ];

        return view('stage.show', compact('game', 'initialState'));
    }
}
