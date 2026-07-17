<?php

namespace Tests\Concerns;

use App\Http\Controllers\JoinController;
use App\Models\Game;
use App\Models\Player;
use Illuminate\Cookie\CookieValuePrefix;

trait InteractsWithPlayers
{
    /**
     * Set the signed join cookie for $player so subsequent requests in this test resolve as
     * that player, mirroring how JoinController/PlayController authenticate players in
     * production (a per-game cookie holding their session_token, not the Laravel auth guard).
     *
     * Encrypts the cookie value by hand rather than using the built-in withCookie() test
     * helper: on this Laravel version withCookie()'s round-trip silently fails (EncryptCookies
     * decrypts it to null server-side even though the test helper's own encryption path is the
     * documented one) — confirmed by reproducing the same failure with a trivial unrelated
     * cookie. Replicating CookieValuePrefix + encrypt() exactly as
     * MakesHttpRequests::prepareCookiesForRequest() does, then sending it via
     * withUnencryptedCookies() (i.e. "don't re-encrypt, I already did"), works reliably.
     */
    protected function actingAsPlayer(Game $game, Player $player): static
    {
        $cookieName = JoinController::cookieName($game);

        $encrypted = encrypt(
            CookieValuePrefix::create($cookieName, app('encrypter')->getKey()).$player->session_token,
            false,
        );

        // postJson()/getJson() only attach cookies when withCredentials() is set — otherwise
        // prepareCookiesForJsonRequest() sends none at all (a CORS-safety default).
        return $this->withCredentials()->withUnencryptedCookies([$cookieName => $encrypted]);
    }
}
