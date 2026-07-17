<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\Player;
use Illuminate\Support\Facades\Response;
use Illuminate\View\View;

class ResultsController extends Controller
{
    /**
     * Show the final results page for a game.
     */
    public function show(Game $game): View
    {
        $players = $game->players()
            ->withCount('playerAnswers')
            ->orderByDesc('total_score')
            ->orderByDesc('correct_answers')
            ->orderBy('total_correct_response_ms')
            ->get();

        return view('admin.results.show', compact('game', 'players'));
    }

    /**
     * Export trivia results as CSV.
     */
    public function exportTrivia(Game $game)
    {
        $players = $game->players()
            ->withCount('playerAnswers')
            ->orderByDesc('total_score')
            ->orderByDesc('correct_answers')
            ->orderBy('total_correct_response_ms')
            ->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="trivia-results-' . $game->code . '.csv"',
        ];

        $callback = function () use ($players) {
            $file = fopen('php://output', 'w');
            
            // Header row
            fputcsv($file, [
                'Rank',
                'Nickname',
                'Phone',
                'Email',
                'Score',
                'Correct Answers',
                'Incorrect Answers',
                'Missed Answers',
                'Accuracy %',
                'Double Point Correct',
                'Avg Response Time (ms)',
                'Streak',
                'Disqualified',
            ]);

            foreach ($players as $player) {
                $answered = ($player->correct_answers ?? 0) + ($player->incorrect_answers ?? 0) + ($player->missed_answers ?? 0);

                fputcsv($file, [
                    $player->current_rank ?? '—',
                    $player->nickname,
                    $player->phone,
                    $player->email ?? '',
                    $player->total_score ?? 0,
                    $player->correct_answers ?? 0,
                    $player->incorrect_answers ?? 0,
                    $player->missed_answers ?? 0,
                    $answered > 0 ? round(($player->correct_answers ?? 0) / $answered * 100, 1) : 0,
                    $player->double_point_correct_answers ?? 0,
                    $player->correct_answers > 0
                        ? round($player->total_correct_response_ms / $player->correct_answers)
                        : 0,
                    $player->current_streak ?? 0,
                    $player->disqualified_at ? 'Yes' : 'No',
                ]);
            }

            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }

    /**
     * Export prediction results as CSV.
     */
    public function exportPredictions(Game $game)
    {
        $predictions = $game->predictions()
            ->with(['player', 'predictedFirstGoalscorer', 'predictedPlayerOfMatch'])
            ->orderByDesc('points')
            ->orderBy('winner_rank')
            ->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="prediction-results-' . $game->code . '.csv"',
        ];

        $callback = function () use ($predictions) {
            $file = fopen('php://output', 'w');

            fputcsv($file, [
                'Rank',
                'Nickname',
                'Phone',
                'Email',
                'Predicted Outcome',
                'Predicted Team A Score',
                'Predicted Team B Score',
                'Predicted First Goalscorer',
                'Predicted Player of the Match',
                'Predicted First Goal Minute',
                'Points',
                'Winner Rank',
                'Submitted At',
                'Last Edited At',
            ]);

            foreach ($predictions as $prediction) {
                fputcsv($file, [
                    $prediction->winner_rank ?? '—',
                    $prediction->player?->nickname ?? '',
                    $prediction->player?->phone ?? '',
                    $prediction->player?->email ?? '',
                    $prediction->predicted_outcome ?? '',
                    $prediction->predicted_team_a_score ?? '',
                    $prediction->predicted_team_b_score ?? '',
                    $prediction->predictedFirstGoalscorer?->display_name ?? '',
                    $prediction->predictedPlayerOfMatch?->display_name ?? '',
                    $prediction->predicted_first_goal_minute ?? '',
                    $prediction->points ?? 0,
                    $prediction->winner_rank ?? '',
                    $prediction->submitted_at?->toDateTimeString() ?? '',
                    $prediction->last_edited_at?->toDateTimeString() ?? '',
                ]);
            }

            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }
}
