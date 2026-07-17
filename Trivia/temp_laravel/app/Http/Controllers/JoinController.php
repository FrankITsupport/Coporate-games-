<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Player;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Illuminate\View\View;

class JoinController extends Controller
{
    public function show(Game $game): View
    {
        return view('join.show', compact('game'));
    }

    public function store(Request $request, Game $game): RedirectResponse
    {
        $validated = $request->validate([
            'nickname' => ['required', 'string', 'max:50'],
            'phone' => ['required', 'string'],
            'email' => ['nullable', 'email', 'max:255'],
            'consent_accepted' => ['accepted'],
        ]);

        $phone = $this->normalizePhone($validated['phone']);

        if (! preg_match('/^254\d{9}$/', $phone)) {
            return back()->withErrors(['phone' => 'Enter a valid mobile number.'])->withInput();
        }

        $player = Player::where('game_id', $game->id)->where('phone', $phone)->first();

        if ($player && $player->disqualified_at) {
            return back()->withErrors(['phone' => 'This mobile number is not eligible to join.'])->withInput();
        }

        if (! $player) {
            try {
                $player = Player::create([
                    'game_id' => $game->id,
                    'nickname' => $validated['nickname'],
                    'phone' => $phone,
                    'email' => $validated['email'] ?? null,
                    'consent_accepted' => true,
                    'mpesa_card_confirmed' => $request->boolean('mpesa_card_confirmed'),
                    'session_token' => Str::random(64),
                ]);
            } catch (QueryException $e) {
                // Two requests raced for the same phone number; the other one won.
                $player = Player::where('game_id', $game->id)->where('phone', $phone)->firstOrFail();
            }
        }

        Cookie::queue(Cookie::make(
            $this->cookieName($game),
            $player->session_token,
            60 * 24 * 7,
        ));

        return redirect()->route('play.show', $game);
    }

    private function normalizePhone(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if (str_starts_with($digits, '0')) {
            $digits = '254'.substr($digits, 1);
        }

        return $digits;
    }

    public static function cookieName(Game $game): string
    {
        return "player_token_{$game->code}";
    }
}
