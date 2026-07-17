<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreGameRequest;
use App\Http\Requests\Admin\UpdateGameRequest;
use App\Models\Game;
use Illuminate\Support\Str;
use Illuminate\View\View;

class GameController extends Controller
{
    public function index(): View
    {
        $games = Game::withCount('questions')->latest()->get();

        return view('admin.games.index', compact('games'));
    }

    public function create(): View
    {
        $suggestedCode = strtoupper(Str::random(6));

        return view('admin.games.create', compact('suggestedCode'));
    }

    public function store(StoreGameRequest $request)
    {
        $game = Game::create($request->validated());

        return redirect()->route('admin.games.show', $game)->with('status', 'Game created.');
    }

    public function show(Game $game): View
    {
        $game->loadCount('questions');

        return view('admin.games.show', compact('game'));
    }

    public function edit(Game $game): View
    {
        return view('admin.games.edit', compact('game'));
    }

    public function update(UpdateGameRequest $request, Game $game)
    {
        $game->update($request->validated());

        return redirect()->route('admin.games.show', $game)->with('status', 'Game updated.');
    }
}
