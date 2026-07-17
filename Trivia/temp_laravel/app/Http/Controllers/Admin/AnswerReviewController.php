<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\PlayerAnswer;
use App\Services\ScoreService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AnswerReviewController extends Controller
{
    public function index(Game $game): View
    {
        $pending = PlayerAnswer::where('game_id', $game->id)
            ->where('review_status', 'needs_review')
            ->with(['player', 'question'])
            ->orderBy('answered_at')
            ->get();

        return view('admin.review.index', compact('game', 'pending'));
    }

    public function approve(PlayerAnswer $answer, ScoreService $scoreService): RedirectResponse
    {
        abort_unless($answer->review_status === 'needs_review', 404);

        $scoreService->approveManualReview($answer);

        return back()->with('status', 'Answer approved.');
    }

    public function reject(PlayerAnswer $answer, ScoreService $scoreService): RedirectResponse
    {
        abort_unless($answer->review_status === 'needs_review', 404);

        $scoreService->rejectManualReview($answer);

        return back()->with('status', 'Answer rejected.');
    }
}
