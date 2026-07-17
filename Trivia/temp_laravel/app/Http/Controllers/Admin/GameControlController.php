<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GameStatus;
use App\Events\AnswerRevealed;
use App\Events\GameCancelled;
use App\Events\GamePaused;
use App\Events\GameResumed;
use App\Events\PodiumShown;
use App\Events\QuestionClosed;
use App\Events\QuestionStarted;
use App\Events\RegistrationClosed;
use App\Events\RegistrationOpened;
use App\Events\TriviaLeaderboardUpdated;
use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\Question;
use App\Services\ScoreService;
use App\Support\Broadcaster;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class GameControlController extends Controller
{
    public function index(Game $game): View
    {
        $game->load(['questions' => fn ($query) => $query->orderBy('order_number')->with('options')]);

        $recentEvents = $game->gameEvents()->with('adminUser')->latest()->limit(10)->get();
        $predictionPrompts = $game->predictionPrompts()->ordered()->get();
        $canCalculateWinners = (bool) $game->predictionSetting?->official_results_entered_at;

        return view('admin.games.control', compact('game', 'recentEvents', 'predictionPrompts', 'canCalculateWinners'));
    }

    public function openRegistration(Game $game): RedirectResponse
    {
        if (! in_array($game->status, [GameStatus::Draft, GameStatus::Paused], true)) {
            return back()->withErrors(['status' => 'Registration can only be opened from draft or paused.']);
        }

        $game->update([
            'status' => GameStatus::RegistrationOpen,
            'registration_opened_at' => $game->registration_opened_at ?? now(),
        ]);

        $this->logEvent($game, 'registration_opened');
        Broadcaster::send(new RegistrationOpened($game));

        return back()->with('status', 'Registration opened.');
    }

    public function closeRegistration(Game $game): RedirectResponse
    {
        if ($game->status !== GameStatus::RegistrationOpen) {
            return back()->withErrors(['status' => 'Registration is not open.']);
        }

        $game->update(['status' => GameStatus::Waiting]);
        $this->logEvent($game, 'registration_closed');
        Broadcaster::send(new RegistrationClosed($game));

        return back()->with('status', 'Registration closed.');
    }

    public function startQuestion(Game $game, Question $question): RedirectResponse
    {
        abort_unless($question->game_id === $game->id, 404);

        $allowed = [GameStatus::Waiting, GameStatus::QuestionClosed, GameStatus::AnswerReveal, GameStatus::Leaderboard];

        if (! in_array($game->status, $allowed, true)) {
            return back()->withErrors(['status' => 'Cannot start a question from the current game state.']);
        }

        // A genuine restart (e.g. admin closed it too early by mistake) should give
        // everyone a fresh window — otherwise players marked "missed" on the first
        // close, or who already answered, can never answer again.
        $question->playerAnswers()->delete();

        $question->update([
            'started_at' => now(),
            'closed_at' => null,
            'revealed_at' => null,
        ]);

        $game->update([
            'status' => GameStatus::QuestionLive,
            'current_question_id' => $question->id,
        ]);

        $this->logEvent($game, 'question_started', ['question_id' => $question->id]);
        Broadcaster::send(new QuestionStarted($question));

        return back()->with('status', 'Question started.');
    }

    public function closeQuestion(Game $game, Question $question): RedirectResponse
    {
        abort_unless($question->game_id === $game->id, 404);

        if ($game->status !== GameStatus::QuestionLive || $game->current_question_id !== $question->id) {
            return back()->withErrors(['status' => 'This question is not currently live.']);
        }

        $question->update(['closed_at' => now()]);
        $game->update(['status' => GameStatus::QuestionClosed]);

        $this->logEvent($game, 'question_closed', ['question_id' => $question->id]);
        Broadcaster::send(new QuestionClosed($question));

        // Score any players who didn't answer (0 points, streak reset)
        app(ScoreService::class)->scoreMissedAnswers($question);

        return back()->with('status', 'Question closed.');
    }

    public function revealQuestion(Game $game, Question $question): RedirectResponse
    {
        abort_unless($question->game_id === $game->id, 404);

        if ($game->status !== GameStatus::QuestionClosed || $game->current_question_id !== $question->id) {
            return back()->withErrors(['status' => 'Close the question before revealing it.']);
        }

        $question->update(['revealed_at' => now()]);
        $game->update(['status' => GameStatus::AnswerReveal]);

        $this->logEvent($game, 'answer_revealed', ['question_id' => $question->id]);
        Broadcaster::send(new AnswerRevealed($question));

        return back()->with('status', 'Answer revealed.');
    }

    public function showLeaderboard(Game $game): RedirectResponse
    {
        $allowed = [GameStatus::QuestionClosed, GameStatus::AnswerReveal, GameStatus::Leaderboard];

        if (! in_array($game->status, $allowed, true)) {
            return back()->withErrors(['status' => 'Leaderboard is not available from the current game state.']);
        }

        $game->update(['status' => GameStatus::Leaderboard]);
        $this->logEvent($game, 'leaderboard_shown');
        Broadcaster::send(new TriviaLeaderboardUpdated($game));

        return back()->with('status', 'Leaderboard shown.');
    }

    public function showPodium(Game $game): RedirectResponse
    {
        $allowed = [GameStatus::Leaderboard, GameStatus::Podium];

        if (! in_array($game->status, $allowed, true)) {
            return back()->withErrors(['status' => 'Podium is not available from the current game state.']);
        }

        $game->update([
            'status' => GameStatus::Podium,
            'ended_at' => $game->ended_at ?? now(),
        ]);

        $this->logEvent($game, 'podium_shown');
        Broadcaster::send(new PodiumShown($game));

        return back()->with('status', 'Podium shown.');
    }

    public function pause(Game $game): RedirectResponse
    {
        if ($game->status === GameStatus::Paused) {
            return back();
        }

        $game->update([
            'status' => GameStatus::Paused,
            'paused_from_status' => $game->status->value,
        ]);

        $this->logEvent($game, 'game_paused');
        Broadcaster::send(new GamePaused($game));

        return back()->with('status', 'Game paused.');
    }

    public function resume(Game $game): RedirectResponse
    {
        if ($game->status !== GameStatus::Paused) {
            return back()->withErrors(['status' => 'Game is not paused.']);
        }

        $game->update([
            'status' => $game->paused_from_status ?? GameStatus::Waiting->value,
            'paused_from_status' => null,
        ]);

        $this->logEvent($game, 'game_resumed');
        Broadcaster::send(new GameResumed($game));

        return back()->with('status', 'Game resumed.');
    }

    public function cancel(Game $game): RedirectResponse
    {
        $game->update(['status' => GameStatus::Cancelled]);
        $this->logEvent($game, 'game_cancelled');
        Broadcaster::send(new GameCancelled($game));

        return back()->with('status', 'Game cancelled.');
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
