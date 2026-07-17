<?php

namespace App\Http\Controllers\Admin;

use App\Events\PredictionDashboardUpdated;
use App\Events\PredictionLeaderboardUpdated;
use App\Events\PredictionPromptClosed;
use App\Events\PredictionPromptStarted;
use App\Events\PredictionWinnersCalculated;
use App\Events\PredictionsLocked;
use App\Events\PredictionsOpened;
use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\PredictionPrompt;
use App\Services\PredictionScoringService;
use App\Support\Broadcaster;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class PredictionControlController extends Controller
{
    public function openPredictions(Game $game): RedirectResponse
    {
        if (in_array($game->prediction_status, ['prediction_results_ready', 'official_results_pending'], true)) {
            return back()->withErrors(['status' => 'Predictions are already finalised.']);
        }

        $game->update([
            'prediction_status'     => 'predictions_open',
            'predictions_opened_at' => $game->predictions_opened_at ?? now(),
        ]);

        $this->logEvent($game, 'predictions_opened');
        Broadcaster::send(new PredictionsOpened($game));

        return back()->with('status', 'Fan Predictions opened.');
    }

    public function startPrompt(Game $game, PredictionPrompt $predictionPrompt): RedirectResponse
    {
        abort_unless($predictionPrompt->game_id === $game->id, 404);

        if (! $game->predictionIsOpen()) {
            return back()->withErrors(['status' => 'Open Fan Predictions before starting a prompt.']);
        }

        if (! $predictionPrompt->is_enabled) {
            return back()->withErrors(['status' => 'That prompt is disabled.']);
        }

        $predictionPrompt->update(['started_at' => now(), 'closed_at' => null]);
        $game->update([
            'prediction_status' => 'prediction_prompt_live',
            'current_prediction_prompt_id' => $predictionPrompt->id,
        ]);

        $this->logEvent($game, 'prediction_prompt_started', ['prompt_id' => $predictionPrompt->id]);
        Broadcaster::send(new PredictionPromptStarted($predictionPrompt));

        return back()->with('status', 'Prediction prompt started.');
    }

    public function closePrompt(Game $game, PredictionPrompt $predictionPrompt): RedirectResponse
    {
        abort_unless($predictionPrompt->game_id === $game->id, 404);

        if ($game->prediction_status !== 'prediction_prompt_live' || $game->current_prediction_prompt_id !== $predictionPrompt->id) {
            return back()->withErrors(['status' => 'This prompt is not currently live.']);
        }

        $predictionPrompt->update(['closed_at' => now()]);
        $game->update([
            'prediction_status' => 'predictions_open',
            'current_prediction_prompt_id' => null,
        ]);

        $this->logEvent($game, 'prediction_prompt_closed', ['prompt_id' => $predictionPrompt->id]);
        Broadcaster::send(new PredictionPromptClosed($predictionPrompt));
        Broadcaster::send(new PredictionDashboardUpdated($game));

        return back()->with('status', 'Prediction prompt closed.');
    }

    public function lockPredictions(Game $game): RedirectResponse
    {
        if (! $game->predictionIsOpen()) {
            return back()->withErrors(['status' => 'Predictions are not open.']);
        }

        $game->update([
            'prediction_status' => 'predictions_locked',
            'predictions_locked_at' => $game->predictions_locked_at ?? now(),
        ]);

        $this->logEvent($game, 'predictions_locked');
        Broadcaster::send(new PredictionsLocked($game));
        Broadcaster::send(new PredictionDashboardUpdated($game));

        return back()->with('status', 'Predictions locked.');
    }

    public function showDashboard(Game $game): RedirectResponse
    {
        if (! in_array($game->prediction_status, ['predictions_open', 'prediction_prompt_live', 'predictions_locked', 'prediction_dashboard'], true)) {
            return back()->withErrors(['status' => 'Open predictions before showing the dashboard.']);
        }

        $game->update(['prediction_status' => 'prediction_dashboard']);

        $this->logEvent($game, 'prediction_dashboard_shown');
        Broadcaster::send(new PredictionDashboardUpdated($game));

        return back()->with('status', 'Prediction dashboard shown on stage.');
    }

    public function calculateWinners(Game $game): RedirectResponse
    {
        $setting = $game->predictionSetting;

        if (! $setting || $setting->official_results_entered_at === null) {
            return back()->withErrors(['status' => 'Enter official results before calculating winners.']);
        }

        app(PredictionScoringService::class)->score($game);

        $game->update(['prediction_status' => 'prediction_results_ready']);

        $this->logEvent($game, 'prediction_winners_calculated');
        Broadcaster::send(new PredictionWinnersCalculated($game));
        Broadcaster::send(new PredictionLeaderboardUpdated($game));

        return back()->with('status', 'Prediction winners calculated.');
    }

    private function logEvent(Game $game, string $eventType, array $payload = []): void
    {
        GameEvent::create([
            'game_id' => $game->id,
            'admin_user_id' => Auth::id(),
            'event_type' => $eventType,
            'payload' => $payload ?: null,
        ]);
    }
}
