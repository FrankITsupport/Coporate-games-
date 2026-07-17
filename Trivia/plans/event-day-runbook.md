# Event Day Runbook

Quick-reference for the MC/admin running the Game Iko On watch party live. Written for someone
under pressure at the event, not someone reading documentation calmly beforehand — keep this open
on a second tab during the event.

## Before doors open

1. Confirm the production `.env` has real `ADMIN_EMAIL` / `ADMIN_PASSWORD` (not the dev defaults
   `admin@example.com` / `password`).
2. Confirm `php artisan reverb:start` (or your process manager's equivalent) is running and stays
   running for the whole event — see "If real-time breaks" below for what happens if it isn't.
3. Log in at `/login`, go to **Games**, open the seeded **Game Iko On** game (code `IKOON`).
4. Open **Prediction Settings** and confirm the kick-off time and prediction lock time are set
   correctly for the actual match — the seeder does not set these (it doesn't know the real
   kick-off time), so this must be done manually before the event.
5. Open **Roster** and confirm the England/Argentina lineups still match the actual matchday squads
   — rosters are seeded from a plans-time snapshot and can go stale if the squad changes.
6. Open **Prediction Prompts** and disable any of the 5 seeded prompts you don't want to run live
   (all 5 are enabled by default: match outcome, final score, first goalscorer, Player of the Match,
   first goal minute).
7. **Do not use the QR files in `qrcode/` as-is.** Decoded them directly — they point to
   `https://games.woodnorkgreen.co.ke/` (the bare site root), not `/join/IKOON`. A guest scanning
   the existing QR lands on a page with no join flow. Generate a fresh QR code pointing at
   `/join/IKOON` on the real production domain before doors open.

## Core rule: the browser tab is not the source of truth, the database is

Every action (open registration, start a question, lock predictions, calculate winners) writes to
the database *before* it tries to notify anyone in real time. If a screen looks frozen, wrong, or
throws an error, **refreshing it is almost always the fix** — it re-reads the actual current state
from the server rather than trusting whatever the page last had in memory. This holds for the admin
control panel, every player's phone, and the stage screen.

## Running trivia

- Trivia is not time-boxed. Launch questions whenever you want, in whatever order you want — you
  are not required to go through all 37 in one sitting. Spread them across before kickoff,
  half-time, or full-time as fits the room.
- **Admin → Games → Game Iko On → Live Control** is the one screen for the whole trivia flow:
  Open Registration → Close Registration → Start (per question) → Close → Reveal → repeat →
  Show Leaderboard → Show Podium.
- You cannot start a second question while one is live — the Start button is disabled. If you
  genuinely need to reopen a question you closed too early, hitting Start again on it clears any
  answers already recorded for it and gives everyone a fresh window (this is intentional, not a
  bug — don't restart a question after revealing its answer unless you mean to fully redo it).
- **Pause** freezes exactly where you are — if a question is live when you pause, resuming puts you
  back in that same live question, it does not reset to a neutral waiting state. Safe to hit if
  something goes wrong mid-question.
- Open-text questions with "manual review" enabled don't score automatically when the answer isn't
  an exact match. Check **Admin → Games → Game Iko On → Review Answers** after closing an open-text
  question — anything sitting there needs an Approve or Reject click before it counts. Do this
  before revealing/leaderboard so the numbers are final when players see them.

## Running Fan Predictions

- Predictions are the *only* mode with a hard deadline: **submissions must close before kick-off.**
  The **Lock Predictions** button does this manually if you don't trust the automatic lock time.
- Flow: Open Predictions → Start (per prompt) → Close → repeat for each prompt → Lock Predictions
  → [match happens] → enter the official score/outcome/goalscorer/etc. on the **Prediction
  Settings** page → **Calculate Winners**.
- You cannot calculate winners until official results are entered — the button stays disabled.
- The stage prediction dashboard only ever shows aggregated counts (how many people picked each
  outcome), never who picked what — this is deliberate, not a missing feature.

## If real-time breaks

If the WebSocket server (Reverb) goes down mid-event, admin actions **still work** — the database
update always happens first, and the app logs a warning instead of crashing the request. What stops
working is *live* push updates: player phones and the stage screen will show stale state until they
refresh. Symptom: admin clicks "Start Question," gets a normal success response, but phones don't
update. Fix: get Reverb back up, then have players refresh (or just wait for the next admin action,
which will push again).

## If something looks badly wrong

1. Refresh the screen in question first — see "Core rule" above.
2. Check **Live Control**'s "Recent Activity" log — it shows exactly what actions were taken, when,
   and by whom, which is usually enough to figure out what state you're actually in.
3. Nothing here deletes data destructively except **Cancel Game**, which is why it asks for
   confirmation — everything else (pause, close, reveal) is safe to click while figuring out what
   happened.

## After the event

- **Admin → Games → Game Iko On → Results & Export** has both CSV downloads (trivia results,
  prediction results) — download both before tearing anything down.
- Disqualified players are excluded from the leaderboard/podium but still appear in the export with
  their disqualification flagged, so nobody's data silently disappears.
