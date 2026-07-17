<?php

namespace App\Http\Controllers;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Player;
use App\Models\PlayerAnswer;
use App\Models\Question;
use App\Services\ScoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class PlayController extends Controller
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

        // Build initial state payload for the Vue component so a mid-game refresh
        // restores the correct screen without waiting for the next broadcast.
        $activePrompt = null;
        if ($game->status === GameStatus::QuestionLive && $game->current_question_id) {
            $question = $game->currentQuestion()->with('options')->first();
            if ($question && $question->started_at) {
                $activePrompt = $question->toActivePromptPayload();
            }
        }

        // Existing answer for the current question (so player can't re-submit on refresh)
        $existingAnswer = null;
        if ($game->current_question_id) {
            $existingAnswer = PlayerAnswer::where('player_id', $player->id)
                ->where('question_id', $game->current_question_id)
                ->first(['question_option_id', 'answer_text', 'is_correct', 'total_points']);
        }

        // Calculate points gap to player above
        $pointsGap = null;
        if ($player->current_rank && $player->current_rank > 1) {
            $playerAbove = Player::where('game_id', $game->id)
                ->where('current_rank', $player->current_rank - 1)
                ->whereNull('disqualified_at')
                ->first(['total_score']);
            if ($playerAbove) {
                $pointsGap = $playerAbove->total_score - ($player->total_score ?? 0);
            }
        }

        $initialState = [
            'game_id'         => $game->id,
            'game_status'     => $game->status->value,
            'active_prompt'   => $activePrompt,
            'existing_answer' => $existingAnswer,
            'points_gap'      => $pointsGap,
            'player'          => [
                'id'            => $player->id,
                'nickname'      => $player->nickname,
                'session_token' => $player->session_token,
                'total_score'   => $player->total_score ?? 0,
                'current_rank'  => $player->current_rank,
                'previous_rank' => $player->previous_rank,
                'current_streak'=> $player->current_streak ?? 0,
            ],
        ];

        return view('play.show', compact('game', 'player', 'initialState'));
    }

    /**
     * Submit an answer for the current live question.
     */
    public function submitAnswer(Request $request, Game $game, Question $question): JsonResponse
    {
        // Resolve the player from their cookie
        $token  = $request->cookie(JoinController::cookieName($game));
        $player = $token
            ? Player::where('game_id', $game->id)->where('session_token', $token)->first()
            : null;

        if (! $player) {
            return response()->json(['error' => 'Not registered.'], 403);
        }

        if ($player->disqualified_at) {
            return response()->json(['error' => 'You are not eligible to play.'], 403);
        }

        // Validate the game/question state
        if ($game->status !== GameStatus::QuestionLive) {
            return response()->json(['error' => 'No question is live right now.'], 422);
        }

        if ($game->current_question_id !== $question->id) {
            return response()->json(['error' => 'This is not the active question.'], 422);
        }

        if ($question->game_id !== $game->id) {
            abort(404);
        }

        // Check the timer — question must still be within its window
        $closesAt = $question->started_at->clone()->addSeconds($question->duration_seconds);
        if (now()->isAfter($closesAt)) {
            return response()->json(['error' => 'Time is up. Answer not accepted.'], 422);
        }

        // Prevent duplicate submissions
        $alreadyAnswered = PlayerAnswer::where('player_id', $player->id)
            ->where('question_id', $question->id)
            ->exists();

        if ($alreadyAnswered) {
            return response()->json(['error' => 'You have already answered this question.'], 422);
        }

        // Validate the submitted value
        $data = $request->validate(
            $question->question_type === 'multiple_choice'
                ? ['option_id' => ['required', 'integer']]
                : ['answer_text' => ['required', 'string', 'max:500']]
        );

        if ($question->question_type === 'multiple_choice') {
            // Ensure the option belongs to this question and is enabled
            $option = $question->options()
                ->where('id', $data['option_id'])
                ->where('is_enabled', true)
                ->first();

            if (! $option) {
                return response()->json(['error' => 'Invalid answer option.'], 422);
            }
        }

        // Record the answer with a server-side timestamp
        $answer = PlayerAnswer::create([
            'game_id'            => $game->id,
            'player_id'          => $player->id,
            'question_id'        => $question->id,
            'question_option_id' => $question->question_type === 'multiple_choice' ? $data['option_id'] : null,
            'answer_text'        => $question->question_type === 'open_text' ? trim($data['answer_text']) : null,
            'review_status'      => 'auto_scored',
            'answered_at'        => now(),
        ]);

        // Score immediately (or leave pending if it needs manual review)
        $scored = app(ScoreService::class)->scoreAnswer($answer);

        return response()->json([
            'submitted'      => true,
            'pending_review' => $scored->review_status === 'needs_review',
            'is_correct'     => $scored->is_correct,
            'total_points'   => $scored->total_points,
            'base_points'    => $scored->base_points,
            'speed_bonus'    => $scored->speed_bonus,
            'streak_bonus'   => $scored->streak_bonus,
            'player'         => [
                'total_score'    => $player->fresh()->total_score,
                'current_rank'   => $player->fresh()->current_rank,
                'current_streak' => $player->fresh()->current_streak,
            ],
        ]);
    }
}
