<?php

namespace App\Services;

use App\Events\PlayerResultUpdated;
use App\Models\Player;
use App\Models\PlayerAnswer;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Support\Broadcaster;
use Illuminate\Support\Facades\DB;

class ScoreService
{
    /**
     * Score a single submitted answer.
     *
     * Multiple-choice and auto-matched open-text answers are scored immediately.
     * An open-text answer that doesn't match and requires manual review is left
     * fully pending — no points, no streak change, no counters touched — until
     * an admin calls approveManualReview()/rejectManualReview(). This assumes
     * review happens before the game moves past the next question; a rejection
     * resets the player's *current* streak rather than retroactively replaying
     * every question scored in between.
     *
     * Returns the saved PlayerAnswer with all breakdown fields populated.
     */
    public function scoreAnswer(PlayerAnswer $answer): PlayerAnswer
    {
        $question = $answer->question;
        $player = $answer->player;

        $answeredAt = $answer->answered_at ?? now();
        $elapsedMs = (int) $question->started_at->diffInMilliseconds($answeredAt);

        $outcome = $this->determineOutcome($answer, $question);

        if ($outcome === 'needs_review') {
            $answer->update([
                'is_correct' => false,
                'review_status' => 'needs_review',
                'response_ms' => $elapsedMs,
            ]);

            return $answer->refresh();
        }

        $this->applyScore($answer, $question, $player, $outcome === 'correct', $elapsedMs, 'auto_scored');

        return $answer->refresh();
    }

    /**
     * Admin approves a needs_review open-text answer: score it as correct,
     * using the response time captured at submission.
     */
    public function approveManualReview(PlayerAnswer $answer): PlayerAnswer
    {
        $this->applyScore($answer, $answer->question, $answer->player, true, $answer->response_ms ?? 0, 'approved');

        return $answer->refresh();
    }

    /**
     * Admin rejects a needs_review open-text answer: score it as incorrect.
     */
    public function rejectManualReview(PlayerAnswer $answer): PlayerAnswer
    {
        $this->applyScore($answer, $answer->question, $answer->player, false, $answer->response_ms ?? 0, 'rejected');

        return $answer->refresh();
    }

    /**
     * Award 0 points to every active player who did not submit an answer for this question.
     */
    public function scoreMissedAnswers(Question $question): void
    {
        $game = $question->game;

        $answeredPlayerIds = PlayerAnswer::where('question_id', $question->id)->pluck('player_id');

        $missedPlayers = $game->players()
            ->active()
            ->whereNotIn('id', $answeredPlayerIds)
            ->get();

        foreach ($missedPlayers as $player) {
            PlayerAnswer::create([
                'game_id' => $game->id,
                'player_id' => $player->id,
                'question_id' => $question->id,
                'is_correct' => false,
                'review_status' => 'auto_scored',
                'response_ms' => null,
                'base_points' => 0,
                'speed_bonus' => 0,
                'streak_bonus' => 0,
                'total_points' => 0,
                'answered_at' => null,
            ]);

            $player->update([
                'current_streak' => 0,
                'missed_answers' => ($player->missed_answers ?? 0) + 1,
            ]);

            $player->refresh();
            Broadcaster::send(new PlayerResultUpdated($player));
        }

        app(TriviaLeaderboardService::class)->updateRanks($game);
    }

    /**
     * 'correct' | 'incorrect' | 'needs_review'. Does not mutate the answer.
     */
    private function determineOutcome(PlayerAnswer $answer, Question $question): string
    {
        if ($question->question_type === 'multiple_choice') {
            if (! $answer->question_option_id) {
                return 'incorrect';
            }

            $option = QuestionOption::find($answer->question_option_id);

            return ($option && $option->is_correct && $option->is_enabled) ? 'correct' : 'incorrect';
        }

        // open_text
        $submitted = mb_strtolower(trim((string) $answer->answer_text));

        if ($submitted === '') {
            return 'incorrect';
        }

        $accepted = collect($question->accepted_answers ?? [])
            ->map(fn ($a) => mb_strtolower(trim((string) $a)));

        if ($accepted->contains($submitted)) {
            return 'correct';
        }

        return $question->requires_manual_review ? 'needs_review' : 'incorrect';
    }

    private function applyScore(
        PlayerAnswer $answer,
        Question $question,
        Player $player,
        bool $isCorrect,
        int $elapsedMs,
        string $reviewStatus,
    ): void {
        if ($isCorrect) {
            $totalMs = $question->duration_seconds * 1000;
            $remainingMs = max(0, $totalMs - $elapsedMs);

            $base = $question->is_double_points ? 1600 : 800;
            $maxSpeed = $question->is_double_points ? 400 : 200;
            $speedBonus = (int) round($maxSpeed * ($remainingMs / $totalMs));

            $newStreak = ($player->current_streak ?? 0) + 1;
            $streakBonus = $this->calculateStreakBonus($newStreak);

            $totalPoints = $base + $speedBonus + $streakBonus;
        } else {
            $base = $speedBonus = $streakBonus = $totalPoints = 0;
            $newStreak = 0;
        }

        DB::transaction(function () use (
            $answer, $player, $question,
            $isCorrect, $base, $speedBonus, $streakBonus, $totalPoints,
            $newStreak, $reviewStatus, $elapsedMs
        ) {
            $answer->update([
                'is_correct' => $isCorrect,
                'review_status' => $reviewStatus,
                'response_ms' => $elapsedMs,
                'base_points' => $base,
                'speed_bonus' => $speedBonus,
                'streak_bonus' => $streakBonus,
                'total_points' => $totalPoints,
            ]);

            $playerUpdates = [
                'total_score' => ($player->total_score ?? 0) + $totalPoints,
                'current_streak' => $newStreak,
            ];

            if ($isCorrect) {
                $playerUpdates['correct_answers'] = ($player->correct_answers ?? 0) + 1;
                $playerUpdates['total_correct_response_ms'] = ($player->total_correct_response_ms ?? 0) + $elapsedMs;
                if ($question->is_double_points) {
                    $playerUpdates['double_point_correct_answers'] = ($player->double_point_correct_answers ?? 0) + 1;
                }
            } else {
                $playerUpdates['incorrect_answers'] = ($player->incorrect_answers ?? 0) + 1;
            }

            $player->update($playerUpdates);
        });

        $player->refresh();
        $answer->refresh();
        Broadcaster::send(new PlayerResultUpdated($player, $answer));

        app(TriviaLeaderboardService::class)->updateRanks($question->game);
    }

    private function calculateStreakBonus(int $streak): int
    {
        return match (true) {
            $streak >= 5 => 200,
            $streak === 4 => 150,
            $streak === 3 => 100,
            $streak === 2 => 50,
            default => 0,
        };
    }
}
