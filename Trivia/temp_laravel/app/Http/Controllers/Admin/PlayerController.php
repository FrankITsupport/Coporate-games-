<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\Player;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlayerController extends Controller
{
    /**
     * Show the player list for a game.
     */
    public function index(Game $game): View
    {
        $players = $game->players()
            ->withCount('playerAnswers')
            ->orderBy('created_at')
            ->get();

        return view('admin.players.index', compact('game', 'players'));
    }

    /**
     * Update a player's nickname.
     */
    public function updateNickname(Request $request, Game $game, Player $player)
    {
        $request->validate([
            'nickname' => ['required', 'string', 'max:50'],
        ]);

        $player->update(['nickname' => $request->nickname]);

        return back()->with('status', 'Nickname updated.');
    }

    /**
     * Disqualify a player.
     */
    public function disqualify(Request $request, Game $game, Player $player)
    {
        $player->update(['disqualified_at' => now()]);

        return back()->with('status', 'Player disqualified.');
    }

    /**
     * Reinstate a disqualified player.
     */
    public function reinstate(Request $request, Game $game, Player $player)
    {
        $player->update(['disqualified_at' => null]);

        return back()->with('status', 'Player reinstated.');
    }
}
