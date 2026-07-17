<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\PredictionPlayer;
use App\Models\PredictionSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PredictionSettingController extends Controller
{
    public function edit(Game $game): View
    {
        $setting = $game->predictionSetting ?? $game->predictionSetting()->create([
            'team_a_name' => 'Team A',
            'team_b_name' => 'Team B',
            'allow_prediction_edits' => true,
            'winner_rule' => 'points',
        ]);

        $roster = $game->predictionPlayers()->available()->ordered()->get();

        return view('admin.predictions.settings', compact('game', 'setting', 'roster'));
    }

    public function update(Request $request, Game $game): RedirectResponse
    {
        $setting = $game->predictionSetting ?? $game->predictionSetting()->make();

        $validated = $request->validate([
            'team_a_name'          => ['required', 'string', 'max:120'],
            'team_b_name'          => ['required', 'string', 'max:120'],
            'team_a_flag'          => ['nullable', 'string', 'max:20'],
            'team_b_flag'          => ['nullable', 'string', 'max:20'],
            'kickoff_at'           => ['nullable', 'date'],
            'prediction_lock_at'   => ['nullable', 'date'],
            'allow_prediction_edits'=> ['boolean'],
            'winner_rule'          => ['required', 'in:points,closest_score'],
        ]);

        $setting->fill($validated);
        $setting->game_id = $game->id;
        $setting->save();

        return back()->with('status', 'Prediction settings saved.');
    }

    /**
     * Admin entry of official match results after the match.
     */
    public function storeResults(Request $request, Game $game): RedirectResponse
    {
        $setting = $game->predictionSetting;

        if (! $setting) {
            return back()->withErrors(['results' => 'Configure prediction settings first.']);
        }

        $validated = $request->validate([
            'official_outcome'           => ['nullable', 'in:team_a_win,draw,team_b_win'],
            'official_team_a_score'      => ['nullable', 'integer', 'min:0'],
            'official_team_b_score'      => ['nullable', 'integer', 'min:0'],
            'official_first_goalscorer_id'=> ['nullable', 'exists:prediction_players,id'],
            'official_player_of_match_id' => ['nullable', 'exists:prediction_players,id'],
            'official_first_goal_minute' => ['nullable', 'integer', 'min:0', 'max:200'],
        ]);

        $setting->update(array_merge($validated, [
            'official_results_entered_at' => now(),
        ]));

        $game->update(['prediction_status' => 'official_results_pending']);

        return back()->with('status', 'Official results saved. You can now calculate prediction winners.');
    }
}
