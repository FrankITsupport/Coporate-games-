<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\PredictionPlayer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Roster management for Fan Predictions. The roster lives in the
 * prediction_players table (what the predictions table references) and is
 * editable because matchday lineups change.
 */
class PredictionPlayerController extends Controller
{
    public function index(Game $game): View
    {
        $setting = $game->predictionSetting;

        $teamAName = $setting?->team_a_name ?: 'Team A';
        $teamBName = $setting?->team_b_name ?: 'Team B';

        $teamA = $game->predictionPlayers()
            ->where('team_name', $teamAName)
            ->ordered()
            ->get();

        $teamB = $game->predictionPlayers()
            ->where('team_name', $teamBName)
            ->ordered()
            ->get();

        $neutral = $game->predictionPlayers()
            ->whereNull('team_name')
            ->ordered()
            ->get();

        return view('admin.rosters.index', compact('game', 'teamAName', 'teamBName', 'teamA', 'teamB', 'neutral'));
    }

    public function store(Request $request, Game $game): RedirectResponse
    {
        $validated = $request->validate([
            'team_name'     => ['nullable', 'string', 'max:120'],
            'display_name'  => ['required', 'string', 'max:120'],
            'shirt_number'  => ['nullable', 'string', 'max:10'],
            'position'      => ['nullable', 'string', 'max:20'],
            'squad_status'  => ['required', 'in:starter,substitute,unknown,unavailable'],
        ]);

        $teamName = $validated['team_name'] ?: null;

        $sortOrder = $game->predictionPlayers()->where('team_name', $teamName)->max('sort_order') ?? 0;

        PredictionPlayer::create([
            'game_id'      => $game->id,
            'team_name'    => $teamName,
            'display_name' => $validated['display_name'],
            'shirt_number' => $validated['shirt_number'],
            'position'     => $validated['position'],
            'squad_status' => $validated['squad_status'],
            'is_available' => $validated['squad_status'] !== 'unavailable',
            'sort_order'   => $sortOrder + 1,
        ]);

        return back()->with('status', 'Player added to roster.');
    }

    public function update(Request $request, Game $game, PredictionPlayer $predictionPlayer): RedirectResponse
    {
        abort_unless($predictionPlayer->game_id === $game->id, 404);

        $validated = $request->validate([
            'team_name'     => ['nullable', 'string', 'max:120'],
            'display_name'  => ['required', 'string', 'max:120'],
            'shirt_number'  => ['nullable', 'string', 'max:10'],
            'position'      => ['nullable', 'string', 'max:20'],
            'squad_status'  => ['required', 'in:starter,substitute,unknown,unavailable'],
        ]);

        $predictionPlayer->update([
            'team_name'    => $validated['team_name'] ?: null,
            'display_name' => $validated['display_name'],
            'shirt_number' => $validated['shirt_number'],
            'position'     => $validated['position'],
            'squad_status' => $validated['squad_status'],
            'is_available' => $validated['squad_status'] !== 'unavailable',
        ]);

        return back()->with('status', 'Roster player updated.');
    }

    public function destroy(Game $game, PredictionPlayer $predictionPlayer): RedirectResponse
    {
        abort_unless($predictionPlayer->game_id === $game->id, 404);

        $predictionPlayer->delete();

        return back()->with('status', 'Roster player removed.');
    }

    /**
     * Seed the currently available England and Argentina squads plus the
     * "No goalscorer" neutral option. Idempotent via firstOrCreate.
     */
    public function seed(Game $game): RedirectResponse
    {
        $setting = $game->predictionSetting;
        $teamAName = $setting?->team_a_name ?: 'Team A';
        $teamBName = $setting?->team_b_name ?: 'Team B';

        $england = [
            ['display_name' => 'Harry Kane', 'shirt_number' => '9', 'position' => 'FW', 'squad_status' => 'starter'],
            ['display_name' => 'Jude Bellingham', 'shirt_number' => '5', 'position' => 'MF', 'squad_status' => 'starter'],
            ['display_name' => 'Phil Foden', 'shirt_number' => '20', 'position' => 'MF', 'squad_status' => 'starter'],
            ['display_name' => 'Bukayo Saka', 'shirt_number' => '7', 'position' => 'FW', 'squad_status' => 'starter'],
            ['display_name' => 'Declan Rice', 'shirt_number' => '4', 'position' => 'MF', 'squad_status' => 'starter'],
            ['display_name' => 'Kyle Walker', 'shirt_number' => '2', 'position' => 'DF', 'squad_status' => 'starter'],
            ['display_name' => 'John Stones', 'shirt_number' => '6', 'position' => 'DF', 'squad_status' => 'starter'],
            ['display_name' => 'Luke Shaw', 'shirt_number' => '3', 'position' => 'DF', 'squad_status' => 'starter'],
            ['display_name' => 'Jordan Pickford', 'shirt_number' => '1', 'position' => 'GK', 'squad_status' => 'starter'],
            ['display_name' => 'Marcus Rashford', 'shirt_number' => '11', 'position' => 'FW', 'squad_status' => 'substitute'],
            ['display_name' => 'Jack Grealish', 'shirt_number' => '10', 'position' => 'FW', 'squad_status' => 'substitute'],
        ];

        $argentina = [
            ['display_name' => 'Lionel Messi', 'shirt_number' => '10', 'position' => 'FW', 'squad_status' => 'starter'],
            ['display_name' => 'Julián Álvarez', 'shirt_number' => '9', 'position' => 'FW', 'squad_status' => 'starter'],
            ['display_name' => 'Rodrigo De Paul', 'shirt_number' => '7', 'position' => 'MF', 'squad_status' => 'starter'],
            ['display_name' => 'Enzo Fernández', 'shirt_number' => '8', 'position' => 'MF', 'squad_status' => 'starter'],
            ['display_name' => 'Alexis Mac Allister', 'shirt_number' => '20', 'position' => 'MF', 'squad_status' => 'starter'],
            ['display_name' => 'Nicolás Otamendi', 'shirt_number' => '19', 'position' => 'DF', 'squad_status' => 'starter'],
            ['display_name' => 'Cristian Romero', 'shirt_number' => '4', 'position' => 'DF', 'squad_status' => 'starter'],
            ['display_name' => 'Nahuel Molina', 'shirt_number' => '26', 'position' => 'DF', 'squad_status' => 'starter'],
            ['display_name' => 'Emiliano Martínez', 'shirt_number' => '1', 'position' => 'GK', 'squad_status' => 'starter'],
            ['display_name' => 'Ángel Di María', 'shirt_number' => '11', 'position' => 'FW', 'squad_status' => 'substitute'],
            ['display_name' => 'Lautaro Martínez', 'shirt_number' => '22', 'position' => 'FW', 'squad_status' => 'substitute'],
        ];

        $this->seedTeam($game, $teamAName, $england);
        $this->seedTeam($game, $teamBName, $argentina);

        PredictionPlayer::firstOrCreate([
            'game_id' => $game->id,
            'team_name' => null,
            'display_name' => 'No goalscorer',
        ], [
            'shirt_number' => null,
            'position' => null,
            'squad_status' => 'starter',
            'is_available' => true,
            'sort_order' => 999,
        ]);

        return back()->with('status', 'Default England vs Argentina rosters seeded.');
    }

    private function seedTeam(Game $game, string $teamName, array $players): void
    {
        $order = 1;
        foreach ($players as $player) {
            PredictionPlayer::firstOrCreate([
                'game_id' => $game->id,
                'team_name' => $teamName,
                'display_name' => $player['display_name'],
            ], [
                'shirt_number' => $player['shirt_number'],
                'position' => $player['position'],
                'squad_status' => $player['squad_status'],
                'is_available' => $player['squad_status'] !== 'unavailable',
                'sort_order' => $order++,
            ]);
        }
    }
}
