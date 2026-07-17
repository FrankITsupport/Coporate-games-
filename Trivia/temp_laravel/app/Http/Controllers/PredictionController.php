<?php

namespace App\Http\Controllers;

use App\Events\PredictionSubmitted;
use App\Models\Game;
use App\Models\Player;
use App\Models\PredictionAnswer;
use App\Models\PredictionPrompt;
use App\Services\PredictionScoringService;
use App\Support\Broadcaster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PredictionController extends Controller
{
    public function show(Request $request, Game $game): View|RedirectResponse
    {
        $token = $request->cookie(JoinController::cookieName($game));

        $player = $token
            ? Player::where('game_id', $game->id)->where('session_token', $token)->first()
            : null;

        if (! $player) {
            return redirect()->route('join.show', $game);
        }

        $setting = $game->predictionSetting;

        $currentPrompt = null;
        if ($game->prediction_status === 'prediction_prompt_live' && $game->current_prediction_prompt_id) {
            $prompt = $game->currentPredictionPrompt()->with('game')->first();
            if ($prompt && $prompt->started_at) {
                $currentPrompt = $prompt->toActivePromptPayload($setting);
            }
        }

        $existingAnswers = $game->predictionAnswers()
            ->where('player_id', $player->id)
            ->get(['prediction_prompt_id', 'answer_payload'])
            ->mapWithKeys(fn ($a) => [$a->prediction_prompt_id => $a->answer_payload]);

        $initialState = [
            'game_id'          => $game->id,
            'game_code'        => $game->code,
            'prediction_status'=> $game->prediction_status,
            'locked'           => $game->predictionsAreLocked(),
            'allow_edits'      => (bool) ($setting?->allow_prediction_edits ?? false),
            'config'           => $setting?->toPublicConfig() ?? [
                'team_a_flag' => null, 'team_b_flag' => null,
                'team_a_name' => null, 'team_b_name' => null,
                'winner_rule' => 'points',
            ],
            'current_prompt'   => $currentPrompt,
            'existing_answers' => $existingAnswers->toArray(),
            'player'           => [
                'id'           => $player->id,
                'nickname'     => $player->nickname,
                'session_token'=> $player->session_token,
            ],
        ];

        return view('predict.show', compact('game', 'player', 'initialState'));
    }

    public function submitAnswer(Request $request, Game $game, PredictionPrompt $prompt): JsonResponse
    {
        $token = $request->cookie(JoinController::cookieName($game));
        $player = $token
            ? Player::where('game_id', $game->id)->where('session_token', $token)->first()
            : null;

        if (! $player) {
            return response()->json(['error' => 'Not registered.'], 403);
        }

        if ($player->disqualified_at) {
            return response()->json(['error' => 'You are not eligible to play.'], 403);
        }

        if (! $game->predictionIsOpen()) {
            return response()->json(['error' => 'Predictions are not open.'], 422);
        }

        if ($game->predictionsAreLocked()) {
            return response()->json(['error' => 'Predictions are locked.'], 422);
        }

        if ($prompt->game_id !== $game->id || ! $prompt->is_enabled) {
            abort(404);
        }

        if ($game->current_prediction_prompt_id !== $prompt->id || $game->prediction_status !== 'prediction_prompt_live') {
            return response()->json(['error' => 'This prediction is not live right now.'], 422);
        }

        $payload = $this->validatePayload($request, $prompt);
        if (is_array($payload) && isset($payload['_error'])) {
            return response()->json(['error' => $payload['_error']], 422);
        }

        $existing = PredictionAnswer::where('player_id', $player->id)
            ->where('prediction_prompt_id', $prompt->id)
            ->first();

        if ($existing && (! ($game->predictionSetting?->allow_prediction_edits ?? false) || $game->predictionsAreLocked())) {
            return response()->json(['error' => 'You have already submitted this prediction.'], 422);
        }

        if ($existing) {
            $existing->update([
                'answer_payload' => $payload,
                'last_edited_at' => now(),
            ]);
            $answer = $existing;
        } else {
            $answer = PredictionAnswer::create([
                'game_id'              => $game->id,
                'player_id'            => $player->id,
                'prediction_prompt_id' => $prompt->id,
                'answer_payload'       => $payload,
                'submitted_at'         => now(),
                'last_edited_at'       => now(),
            ]);
        }

        app(PredictionScoringService::class)->syncSummary($game, $player->id, $player);

        Broadcaster::send(new PredictionSubmitted($answer));

        return response()->json([
            'submitted' => true,
            'prompt_id' => $prompt->id,
            'editable'  => (bool) ($game->predictionSetting?->allow_prediction_edits ?? false) && ! $game->predictionsAreLocked(),
        ]);
    }

    private function validatePayload(Request $request, PredictionPrompt $prompt): array
    {
        return match ($prompt->prompt_type) {
            'flag_choice' => $this->validateFlag($request),
            'score_input' => $this->validateScore($request),
            'player_select' => $this->validatePlayer($request, $prompt),
            'number_input' => $this->validateNumber($request),
            default => ['_error' => 'Unsupported prediction type.'],
        };
    }

    private function validateFlag(Request $request): array
    {
        $data = $request->validate([
            'outcome' => ['required', 'in:team_a_win,draw,team_b_win'],
        ]);

        return ['outcome' => $data['outcome']];
    }

    private function validateScore(Request $request): array
    {
        $data = $request->validate([
            'team_a_score' => ['required', 'integer', 'min:0', 'max:50'],
            'team_b_score' => ['required', 'integer', 'min:0', 'max:50'],
        ]);

        return ['team_a_score' => $data['team_a_score'], 'team_b_score' => $data['team_b_score']];
    }

    private function validatePlayer(Request $request, PredictionPrompt $prompt): array
    {
        $data = $request->validate([
            'player_id' => ['required', 'integer', 'exists:prediction_players,id'],
        ]);

        $isAvailable = $prompt->game->predictionPlayers()
            ->where('id', $data['player_id'])
            ->where('is_available', true)
            ->exists();

        if (! $isAvailable) {
            return ['_error' => 'That player is not available.'];
        }

        return ['player_id' => $data['player_id']];
    }

    private function validateNumber(Request $request): array
    {
        $data = $request->validate([
            'minute' => ['required', 'integer', 'min:0', 'max:200'],
        ]);

        return ['minute' => $data['minute']];
    }
}
