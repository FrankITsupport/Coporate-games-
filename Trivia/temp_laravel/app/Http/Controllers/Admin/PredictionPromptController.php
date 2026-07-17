<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\PredictionPrompt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PredictionPromptController extends Controller
{
    public function index(Game $game): View
    {
        $prompts = $game->predictionPrompts()->ordered()->get();

        $availableKeys = [
            'match_outcome'      => 'Match outcome (win/draw)',
            'final_score'        => 'Final score',
            'first_goalscorer'   => 'First goalscorer',
            'player_of_match'    => 'Player of the Match',
            'first_goal_minute'  => 'First goal minute',
        ];

        $availableTypes = [
            'flag_choice'   => 'Flag choice (team A / draw / team B)',
            'score_input'   => 'Score input',
            'player_select' => 'Player select (roster)',
            'number_input'  => 'Number input',
        ];

        return view('admin.prediction-prompts.index', compact('game', 'prompts', 'availableKeys', 'availableTypes'));
    }

    public function store(Request $request, Game $game): RedirectResponse
    {
        $validated = $request->validate([
            'prompt_key'     => ['required', 'string', 'max:60'],
            'prompt_label'   => ['required', 'string', 'max:120'],
            'prompt_type'    => ['required', 'in:flag_choice,score_input,player_select,number_input,range_choice,yes_no'],
            'duration_seconds'=> ['nullable', 'integer', 'min:5'],
            'is_enabled'     => ['boolean'],
        ]);

        $order = $game->predictionPrompts()->max('order_number') ?? 0;

        $game->predictionPrompts()->create(array_merge($validated, [
            'order_number' => $order + 1,
            'is_enabled'   => $request->boolean('is_enabled', true),
        ]));

        return back()->with('status', 'Prediction prompt added.');
    }

    public function update(Request $request, Game $game, PredictionPrompt $predictionPrompt): RedirectResponse
    {
        abort_unless($predictionPrompt->game_id === $game->id, 404);

        $validated = $request->validate([
            'prompt_key'     => ['required', 'string', 'max:60'],
            'prompt_label'   => ['required', 'string', 'max:120'],
            'prompt_type'    => ['required', 'in:flag_choice,score_input,player_select,number_input,range_choice,yes_no'],
            'duration_seconds'=> ['nullable', 'integer', 'min:5'],
            'is_enabled'     => ['boolean'],
        ]);

        $predictionPrompt->update($validated);

        return back()->with('status', 'Prediction prompt updated.');
    }

    public function destroy(Game $game, PredictionPrompt $predictionPrompt): RedirectResponse
    {
        abort_unless($predictionPrompt->game_id === $game->id, 404);

        $predictionPrompt->delete();

        return back()->with('status', 'Prediction prompt removed.');
    }

    public function moveUp(Game $game, PredictionPrompt $predictionPrompt): RedirectResponse
    {
        abort_unless($predictionPrompt->game_id === $game->id, 404);

        $this->swapOrder($predictionPrompt, -1);

        return back();
    }

    public function moveDown(Game $game, PredictionPrompt $predictionPrompt): RedirectResponse
    {
        abort_unless($predictionPrompt->game_id === $game->id, 404);

        $this->swapOrder($predictionPrompt, 1);

        return back();
    }

    private function swapOrder(PredictionPrompt $prompt, int $direction): void
    {
        $siblings = $prompt->game->predictionPrompts()->ordered()->get();
        $index = $siblings->search(fn ($p) => $p->id === $prompt->id);

        $targetIndex = $index + $direction;

        if ($targetIndex < 0 || $targetIndex >= $siblings->count()) {
            return;
        }

        $target = $siblings[$targetIndex];

        $promptOrder = $prompt->order_number;
        $prompt->update(['order_number' => $target->order_number]);
        $target->update(['order_number' => $promptOrder]);
    }
}
