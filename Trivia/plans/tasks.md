# Event Game Platform Task Plan

## Phase 1: Project Setup

- [x] Create Laravel project
- [x] Configure environment variables
- [x] Set up database connection
- [x] Install frontend dependencies
- [x] Configure Vite
- [x] Configure Tailwind CSS
- [x] Install and configure Vue
- [x] Set up admin authentication
- [x] Choose real-time provider: Laravel Reverb or Pusher
- [x] Configure broadcasting

Acceptance criteria:

- Laravel app runs locally
- Admin can log in
- Vue component can render inside a Blade page
- Broadcast configuration is ready

## Phase 2: Database and Models

- [x] Create `games` migration and model
- [x] Create `players` migration and model
- [x] Create `questions` migration and model
- [x] Create `question_options` migration and model
- [x] Create `player_answers` migration and model
- [x] Create `prediction_settings` migration and model
- [x] Create `prediction_prompts` migration and model
- [x] Create `prediction_players` migration and model
- [x] Create `predictions` migration and model
- [x] Create `prediction_answers` migration and model
- [x] Add indexes and uniqueness constraints
- [x] Add relationships between models
- [x] Add factories or seeders for test data
- [x] (Bonus) Create `game_events` migration and model for admin action/audit logging

Acceptance criteria:

- Database migrates successfully
- A game can have many players and questions
- A question can have many options
- A player can answer each question only once
- Mobile number is unique per game
- A player can submit one prediction per prediction game
- A player can submit one answer per active prediction prompt
- Trivia answers and prediction answers are stored separately

## Phase 3: Admin Game Setup

- [x] Create admin game list page
- [x] Create admin game create/edit page
- [x] Create question list page
- [x] Create question create/edit page
- [x] Store trivia questions in the database
- [x] Support multiple-choice question type
- [x] Support open-text question type
- [x] Support answer option creation
- [x] Support adding fewer than four answer options
- [x] Support disabling answer options without deleting them
- [x] Support marking the correct answer
- [x] Support accepted answers for open-text questions
- [x] Support manual review flag for open-text questions
- [x] Support standard and double-points questions
- [x] Support sudden-death questions
- [x] Support question ordering
- [x] Support duplicating and removing trivia questions
- [x] Treat trivia questions as MC/admin-started live prompts (schema/authoring only —
      `started_at`/`closed_at`/`revealed_at` stay null until Phase 5 adds the actual start/close
      admin action)
- [x] Add game type/settings support for Trivia and Fan Predictions

Acceptance criteria:

- Admin can create a game
- Admin can add all questions and answer options
- Admin can add, edit, reorder, duplicate, and remove trivia questions
- Admin can choose multiple-choice or open-text per question
- Admin can disable a multiple-choice option without deleting it
- Admin can mark correct answers
- Admin can define accepted answers for open-text questions
- Admin can configure duration, order, round, and scoring type
- Admin can start and close trivia prompts manually
- Admin can enable Trivia, Fan Predictions, or both for an event

## Phase 3B: Fan Prediction Setup

- [x] Create prediction settings page (`admin.games.predictions.settings`)
- [x] Configure internal team labels
- [x] Configure public team flags
- [x] Configure kick-off time
- [x] Configure prediction lock time
- [x] Configure whether prediction edits are allowed
- [x] Configure winner rule: points or closest score
- [x] Create configurable prediction prompts (`admin.games.prediction-prompts.index`)
- [x] Enable or disable prediction prompts
- [x] Reorder prediction prompts
- [x] Configure prediction prompt duration
- [x] Create roster management page (`admin.games.roster.index` — built on the `PredictionPlayer`
      model from Phase 2; a second, duplicate `Roster`/`rosters` implementation existed alongside it,
      unwired and pointed at the same missing view — deleted as dead code, see notes below)
- [x] Seed the currently available England and Argentina rosters (one-click seed button; idempotent
      via `firstOrCreate`, safe to click again close to matchday)
- [x] Add player manually
- [x] Remove or disable unavailable players
- [x] Edit player name/team/status
- [x] Mark player as starter, substitute, unknown, or unavailable
- [x] Include "No goalscorer" option for first goalscorer (seeded automatically, `team_name = null`)
- [ ] Support roster import/update if needed — manual add/edit/seed covers the stated requirement;
      no bulk CSV import exists (not requested, would be speculative)

Note: this phase's admin pages existed in code but silently 500'd on every visit
("View not found") because the corresponding Blade views were never created — confirmed by
actually visiting each route, not just reading the controllers. Views for all three pages are now
built and verified against a running server.

Acceptance criteria:

- Admin can configure England vs Argentina prediction settings
- Admin can set public flags for both sides
- Current available rosters are preloaded before admin review
- Admin can maintain selectable players and substitutes
- Prediction form options come from the admin-managed roster
- Admin can remove optional prediction prompts from the player experience
- Player and stage prediction screens use flags instead of team names

## Phase 4: Player Registration

- [x] Create `/join/{game_code}` route
- [x] Build registration form
- [x] Validate nickname
- [x] Validate mobile number
- [x] Validate consent acceptance
- [x] Support optional email
- [x] Support optional M-PESA GlobalPay Virtual Visa Card confirmation
- [x] Prevent duplicate active registration by phone number
- [x] Store player session token
- [x] Redirect registered player to `/play/{game_code}`
- [x] (Bonus) `/play/{game_code}` resolves the player from a signed cookie and reconnects without
      creating a duplicate row; unrecognized visitors are bounced back to `/join`. Note: this route
      currently renders a static waiting-state placeholder — the live Vue screen is Phase 7.
- [x] (Bonus) Phone numbers are normalized (0712345678 / +254 712 345 678 / 254712345678 all
      resolve to the same value) so the phone-uniqueness rule can't be bypassed by format alone.

Acceptance criteria:

- Player can register from a QR/short link
- Same phone number cannot register twice in the same game
- Registered player can refresh and return to the play screen
- Public pages do not expose other players' personal data

## Phase 5: Game State and Admin Controls

- [x] Add game status handling (native `App\Enums\GameStatus` backed enum, cast on the `Game` model)
- [x] Add current question tracking (`games.current_question_id`, set/cleared by start/close actions)
- [x] Add current active prompt tracking for trivia and predictions (`current_prediction_prompt_id`
      column exists from Phase 2; trivia side is wired this phase, prediction side is unused until
      Phase 3B/8B below)
- [x] Build admin live-control panel (`admin.games.control` — registration, per-question
      start/close/reveal, leaderboard/podium, pause/resume/cancel, recent-activity log, all on one page)
- [x] Build shared live prompt controls for trivia and predictions (Fan Predictions get their own
      section on `admin.games.control` — open/lock/dashboard/calculate plus per-prompt start/close —
      alongside the trivia question controls)
- [x] Implement open registration action
- [x] Implement close registration action
- [x] Implement start question action
- [x] Implement close question action
- [x] Implement reveal answer action
- [x] Implement show leaderboard action
- [x] Implement show podium action
- [x] Implement open predictions action
- [x] Implement start prediction prompt action
- [x] Implement close prediction prompt action
- [x] Implement lock predictions action
- [x] Implement official result entry action
- [x] Implement calculate prediction winners action
- [x] Implement show prediction dashboard action
- [x] Implement pause/cancel actions (plus a `resume` action; pause remembers the exact
      in-progress state via `paused_from_status` so resuming mid-question doesn't drop it)
- [x] Add admin action logging via `game_events` (every action above logs `event_type` + payload +
      the admin user)

**Bug found and fixed:** the state machine allows restarting a question from `question_closed` (e.g.
the admin closed it too early by mistake), but `startQuestion()` didn't clear the prior
`PlayerAnswer` rows for that question — so anyone marked "missed" on the first close, or who already
answered, could never answer again after a genuine restart. Fixed: starting a question now deletes
any existing `PlayerAnswer` rows for it first, so a restart is a real fresh window.

**Bug found and fixed:** `PredictionControlController::startPrompt()`/`closePrompt()` type-hinted
their route-model-bound parameter as `PredictionPrompt $prompt`, but the route declared the URI
segment as `{predictionPrompt}`. The name mismatch broke Laravel's implicit binding — every "start
prompt" click 404'd. Found by actually driving the full prediction lifecycle over HTTP, not by
reading the controller (the code looked correct in isolation). Fixed by renaming the parameter to
match the route; the same check was run against every other admin controller to confirm no other
route had the same mismatch.

Acceptance criteria:

- Admin can control the event flow from one screen
- Game state changes are saved server-side
- Refreshing admin, player, or stage views shows the correct state
- Trivia prompts appear only when MC/admin starts them (verified server-side: `started_at` stays
  null and `current_question_id` stays unset until the admin's Start action runs; the player-facing
  screen won't visibly reflect this until Phase 7 builds the live prompt UI)
- Prediction lock is enforced server-side (verified: submission rejected once locked)
- Prediction prompts appear only when MC/admin starts them (verified end-to-end: opened predictions,
  added a prompt, started it, a player submitted, closed it, locked, entered official results,
  calculated winners — correct points awarded)

## Phase 6: Real-Time Events

- [x] Create broadcast channels (public `game.{gameId}` for all trivia state; public
      `player.{sessionToken}` for the not-yet-dispatched `PlayerResultUpdated` — see note below on
      why this isn't a Laravel private channel)
- [x] Create `RegistrationOpened` event
- [x] Create `RegistrationClosed` event
- [x] Create `QuestionStarted` event (payload deliberately excludes `is_correct` and
      `accepted_answers` — verified via a real subscriber that the correct answer never reaches
      player phones before reveal)
- [x] Create `QuestionClosed` event
- [x] Create `AnswerRevealed` event (this is where the correct answer is safe to include)
- [x] Create `TriviaLeaderboardUpdated` event
- [x] Create `PredictionLeaderboardUpdated` event
- [x] Create `PlayerResultUpdated` event (now dispatched from `ScoreService`, including from the
      manual-review approve/reject actions)
- [x] Create `PodiumShown` event
- [x] Create `PredictionsOpened` event
- [x] Create `PredictionPromptStarted` event
- [x] Create `PredictionPromptClosed` event
- [x] Create `PredictionsLocked` event
- [x] Create `PredictionSubmitted` event
- [x] Create `PredictionDashboardUpdated` event
- [x] Create `PredictionWinnersCalculated` event
- [x] Subscribe Vue screens to relevant channels (`PlayerGame.vue`, `PredictionForm.vue`,
      `StageScreen.vue` all subscribe to `game.{id}`; the first two also subscribe to their own
      `player.{sessionToken}` channel)
- [x] Add reconnect behavior through initial page payload/API refresh (`PlayController`,
      `PredictionController`, and `StageController` each compute the live active-prompt/leaderboard/
      dashboard state server-side and pass it as the Vue root's initial props)
- [x] Normalize active prompt payloads for trivia and predictions (the `prompt_mode` /
      `prompt_id` / `duration_seconds` / `started_at` / `closes_at` / `payload` shape from
      `plans/design.md` is implemented in both `Question::toActivePromptPayload()` and
      `PredictionPrompt::toActivePromptPayload()`)
- [x] (Bonus, not in the original checklist but in `plans/design.md`'s recommended event list)
      `GamePaused`, `GameResumed`, `GameCancelled` — pausing/cancelling mid-event with no way to
      notify connected screens would be a real reliability gap, not just a missing nice-to-have

**Bug found and fixed:** every `broadcast(new Event(...))` call site (22 of them) was unguarded.
Laravel's `broadcast()` helper defers the actual dispatch to `PendingBroadcast::__destruct()`, and a
connection failure there throws `Illuminate\Broadcasting\BroadcastException` — which turned a Reverb
outage into a 500 on every single admin action, even though the underlying database state change had
already succeeded. Confirmed by genuinely killing every Reverb process on the machine and re-running
`startQuestion`: the request 500'd, but `questions.started_at` was already set in the database — the
admin would see an error page for an action that had actually worked. Fixed with
`App\Support\Broadcaster::send()`, a wrapper that catches the failure and logs a warning instead of
crashing the request; re-verified the identical scenario after the fix returns its normal redirect.

**Real-time pipeline verified against a live Reverb server**, not just written: connected a genuine
WebSocket client (Node + `pusher-js`, the same library `laravel-echo` uses in the browser) to the
running Reverb server, subscribed to `game.{id}`, and drove the full admin lifecycle
(`open-registration → close-registration → start-question → pause → resume → close-question →
reveal → leaderboard → podium`) via HTTP. All 9 events arrived in order with correct payloads —
notably `GameResumed` correctly reported `question_live` (not a generic reset state), matching
Phase 5's pause/resume design. Separately verified `PlayerResultUpdated`'s token-keyed channel by
dispatching it manually and confirming a subscriber holding that token receives it.

**Infrastructure fix found and applied along the way:** this machine already had an unrelated,
pre-existing process bound to port 8080 (started a day before this session, not part of this
project — left untouched). Reverb was silently colliding with it. Moved Reverb to port 6001 in
`.env`/`.env.example` (`REVERB_PORT`, added `REVERB_SERVER_PORT` which wasn't set before and was
silently defaulting to 8080 regardless of `REVERB_PORT`).

**Design decision:** `PlayerResultUpdated` broadcasts on a public channel keyed by the player's
64-character `session_token`, not a Laravel private channel keyed by player ID. Laravel's
private/presence channel authorization requires `Auth::user()` to be non-null *before* it will even
invoke a custom `Broadcast::channel()` callback — but players authenticate via a per-game signed
cookie (`JoinController`), not the Laravel auth guard, so there's no `Authenticatable` to satisfy
that check. Standing up a real auth guard for players purely for this would be premature given the
event has no dispatcher yet. The session token is already an unguessable random string, so a
channel named after it is private in practice without that machinery.

Acceptance criteria:

- Player phones update when a question starts — verified end-to-end against a running Reverb server
- Player phones update when a trivia prompt closes — verified
- Stage screen updates when admin changes state — verified
- Player result updates after scoring — verified, including the manual-review approve/reject path
- Prediction dashboard updates after submissions — verified
- Player phones update when a prediction prompt starts or closes — verified
- Refresh restores the latest server state — verified: all live state lives in the `games`/
  `questions`/prediction tables, so a reload always reflects the last known-good state regardless of
  whether the socket connection dropped, and a Reverb outage no longer breaks the underlying actions

## Phase 7: Player Live Game Screen

- [x] Build `LivePromptShell.vue`
- [x] Build `PlayerGame.vue`
- [x] Show waiting state
- [x] Show live question state
- [x] Render trivia question prompts from active prompt payload
- [x] Render prediction prompts from active prompt payload — architectural note: not inside
      `PlayerGame.vue` itself. Fan Predictions got its own dedicated screen instead
      (`/predict/{code}` → `PredictionForm.vue` + `PredictionPromptPanel.vue`), rather than merging
      trivia and prediction rendering into one shared shell as `plans/design.md` suggested as an
      option ("the player phone *can* use one live screen"). Both consume the same
      `toActivePromptPayload()` shape, so the two screens stay consistent without actually sharing a
      component — a reasonable simplification, not a gap.
- [x] Show countdown timer
- [x] Show answer options
- [x] Show text answer input for open-text trivia questions
- [x] Hide disabled multiple-choice options
- [x] Disable answers after submission
- [x] Prevent answer change
- [x] Show submitted state
- [x] Show correct/incorrect result
- [x] Show points earned
- [x] Show current score
- [x] Show current rank
- [x] Show current streak
- [x] Show points gap to player above

**Bug found and fixed:** a `needs_review` open-text answer showed a hard "✗ Wrong" to the player
immediately, because the submit response always included `is_correct: false` as a placeholder and
the frontend rendered it unconditionally — the "waiting for review" branch existed in the template
but nothing ever triggered it. Fixed alongside the Phase 8 scoring bug below: the API now reports
`pending_review: true` and the frontend only shows the waiting state until `PlayerResultUpdated`
(now carrying the specific answer's outcome) arrives after an admin approves or rejects it.

Acceptance criteria:

- Player can answer a live question once
- Player can answer multiple-choice trivia by selecting an enabled option
- Player can answer open-text trivia by typing an answer
- Player sees trivia questions only when MC/admin starts them
- Player sees immediate submitted feedback
- Player sees personal result after scoring
- Player state survives refresh

## Phase 8: Answer Submission and Scoring

- [x] Implement answer submission endpoint
- [x] Validate active question
- [x] Validate player session
- [x] Validate answer option belongs to question
- [x] Validate selected answer option is enabled
- [x] Validate open-text answer is non-empty
- [x] Use server timestamps for response time
- [x] Prevent duplicate submissions
- [x] Create `ScoreService`
- [x] Implement standard question scoring
- [x] Implement double-points scoring
- [x] Implement multiple-choice answer scoring
- [x] Implement open-text accepted-answer scoring
- [x] Implement open-text manual review workflow if enabled
- [x] Implement streak bonus
- [x] Reset streak on wrong answer
- [x] Reset streak on missed answer
- [x] Score missed players when question closes
- [x] Store score breakdown per answer
- [x] Ensure trivia scoring reads only trivia answer/question tables
- [x] (Not in the original checklist, but needed to make manual review real) Admin approve/reject UI
      for `needs_review` answers (`admin.games.review.index` + approve/reject actions)

**Bug found and fixed:** `ScoreService::scoreAnswer()` computed `$reviewStatus = 'needs_review'`
correctly inside the `if ($isCorrect)` branch, but a `needs_review` open-text answer is by definition
*not* auto-correct — so it always fell into the `else` branch, which hardcoded `'auto_scored'` and
overwrote the flag `isAnswerCorrect()` had just set moments earlier in the same call. The result:
manual review silently never worked — every ambiguous open-text answer was auto-scored as wrong, and
there was also no admin screen to review anything even if the flag had stuck. Found by submitting a
genuine near-miss answer and checking the database, not by reading the code (it reads plausibly).
Fixed by restructuring `ScoreService` around a `determineOutcome()` that returns
`'correct'|'incorrect'|'needs_review'` without mutating anything, deferring all scoring (points,
streak, counters) for the `needs_review` case until an admin calls the new
`approveManualReview()`/`rejectManualReview()` methods. This assumes review happens before the game
moves more than one question past the pending one — a rejection resets the player's *current* streak
rather than retroactively replaying every question scored in between, which is a reasonable
simplification but worth knowing about.

Acceptance criteria:

- Correct answer receives base score, speed bonus, and streak bonus
- Incorrect answer receives 0
- Open-text accepted answer receives correct-answer score
- Open-text rejected answer receives 0 (verified via the admin reject action)
- Missed answer receives 0
- Double-points question uses double base and speed values
- Scores are rounded to whole numbers
- Total score accumulates correctly
- Open-text answers needing manual review are held pending (no score, no streak change, player not
  told correct/incorrect) until an admin approves or rejects them — verified end-to-end, including
  the player's screen correctly showing "waiting for review" instead of a premature wrong answer

## Phase 8B: Fan Prediction Submission and Scoring

- [x] Build `/predict/{game_code}` page
- [x] Build `PredictionForm.vue`
- [x] Build `PredictionPromptPanel.vue`
- [x] Show waiting state until MC/admin starts a prompt
- [x] Show match title and team flags
- [x] Show final outcome selector (`flag_choice` prompt type)
- [x] Show final score inputs (`score_input` prompt type)
- [x] Show first goalscorer selector (`player_select`, backed by the Phase 3B roster)
- [x] Show Player of the Match selector (same `player_select` mechanism)
- [x] Show first goal minute input if enabled (`number_input`)
- [x] Show additional enabled prediction prompts
- [x] Hide disabled prediction prompts (only `enabled()` prompts are ever queried or broadcast)
- [x] Submit answers per active prediction prompt
- [x] Allow edits before lock if enabled
- [x] Block submissions after lock
- [x] Store submitted prediction
- [x] Create `PredictionScoringService`
- [x] Create `PredictionDashboardService`
- [x] Build official result entry form
- [x] Show official result fields only for enabled prediction prompts
- [x] Calculate points-based winners
- [x] Calculate closest-score winners
- [x] Use earliest submission as final tie-breaker

Acceptance criteria (all verified end-to-end: configured England vs Argentina settings, seeded the
roster, added a `flag_choice` prompt, opened predictions, started the prompt, a player submitted a
prediction, closed and locked, entered a 2-1 official result, calculated winners — correct 2 points
awarded for the correct outcome):

- Player can submit predictions before lock
- Player sees prediction form only when a prompt is live
- Player can see their submitted prediction
- Disabled prediction prompts do not appear on player phones
- Player cannot submit or edit after lock
- Public prediction UI does not show team names
- Admin can enter official results after the match
- Admin result entry follows the enabled prediction prompts
- System can calculate prediction winners

## Phase 9: Leaderboard and Tie-Breakers

- [x] Create `TriviaLeaderboardService`
- [x] Create `PredictionLeaderboardService`
- [x] Calculate total rank
- [x] Calculate top 10
- [x] Calculate previous and current rank movement
- [x] Calculate personal rank
- [x] Calculate points gap to player above
- [x] Create `TieBreakerService`
- [x] Apply score tie-breakers
- [x] Detect when sudden death is required
- [x] Rank prediction winners
- [x] Calculate common prediction aggregates
- [x] Keep trivia leaderboard separate from prediction leaderboard (`PredictionScoringService` and
      `PredictionLeaderboardService` only ever read `predictions`/`prediction_answers`, never
      `player_answers`)

Acceptance criteria:

- Stage screen can show top 10 players
- Rank movement is accurate after each question
- Player phone can show personal rank and gap
- Final ranking respects required tie-breakers
- Prediction winners are calculated from official results
- Trivia scores and prediction points are not combined unless explicitly configured

## Phase 10: Stage Screen

- [x] Build `/stage/{game_code}` page
- [x] Build `StageScreen.vue`
- [x] Show waiting/registration state
- [x] Show current question
- [x] Show active trivia prompt only after MC/admin starts it
- [x] Show active prediction prompt only after MC/admin starts it
- [x] Show answer options
- [x] Hide disabled trivia answer options
- [x] Show open-text prompt without exposing accepted answers
- [x] Show countdown
- [x] Show double-points label where applicable
- [x] Show correct answer reveal
- [x] Show top-10 leaderboard
- [x] Show trivia leaderboard separately from prediction leaderboard
- [x] Show rank movement indicators — added a `rankMovementFor()`/`rankMovement` computed helper
      and ▲/▼ arrows to `StageScreen.vue`'s leaderboard rows and to `PlayerGame.vue`'s score panel,
      driven by the existing server-side `current_rank`/`previous_rank` fields
- [x] Show streaks
- [x] Show final top-three podium
- [x] Show live prediction dashboard
- [x] Show prediction submission count
- [x] Show popular predicted outcomes
- [x] Show common scorelines
- [x] Show first goalscorer prediction breakdown
- [x] Show Player of the Match prediction breakdown
- [x] Show first goal minute prediction breakdown if enabled
- [x] Hide disabled prediction prompts from stage dashboard (`PredictionDashboardService` only
      aggregates `enabled()` prompts)
- [x] Show prediction winners after result calculation
- [x] Add responsive large-screen styling

**Bugs found and fixed:**
1. `StageScreen.vue` called `reactive(...)` for the prediction state object but only imported
   `ref, computed, onMounted, onUnmounted` from Vue — `reactive` was undefined, so the component
   threw and never rendered anything. The entire stage screen — the one surface an audience actually
   watches — was completely broken. `npm run build` doesn't catch this class of bug (it's a runtime
   `ReferenceError`, not a build-time error), so it was only found by actually reading the component
   line by line, not by "the build passed."
2. `StageController::show()` checked `$game->status === GameStatus::Ended` to decide whether to
   include podium data in the initial page load, but nothing in the codebase ever sets that status —
   `GameControlController::showPodium()` sets `GameStatus::Podium`. A stage-screen refresh during the
   podium never restored the podium view. Fixed to check `GameStatus::Podium`.

Acceptance criteria:

- Stage screen is readable from a distance
- Stage screen follows admin-controlled game state
- Stage screen never shows phone numbers or emails
- Final podium displays top three players
- Prediction dashboard shows aggregated data only
- Prediction dashboard uses flags instead of team names
- Prediction dashboard only shows enabled prompts
- Stage screen never blends trivia leaderboard with prediction winners

## Phase 11: Player and Admin Moderation

- [x] Add admin player list
- [x] Add nickname edit action
- [x] Add player disqualification action
- [x] Exclude disqualified players from leaderboard
- [x] Preserve disqualified player data for export

Acceptance criteria:

- Admin can remove inappropriate names from public display
- Admin can disqualify duplicate or invalid players
- Disqualified players do not appear on leaderboard or podium

## Phase 12: Export and Results

- [x] Create final results page
- [x] Add CSV export
- [x] Include rank
- [x] Include nickname
- [x] Include phone
- [x] Include optional email
- [x] Include score
- [x] Include accuracy
- [x] Include response-time stats
- [x] Include double-point correct count
- [x] Include disqualification status
- [x] Add prediction CSV export
- [x] Include predicted outcome
- [x] Include predicted final score
- [x] Include predicted first goalscorer
- [x] Include predicted Player of the Match
- [x] Include predicted first goal minute when enabled
- [x] Include only enabled prediction prompts unless admin requests full raw export (holds
      implicitly — the per-player summary row is built by `PredictionScoringService::syncSummary()`,
      which only ever reads answers to `enabled()` prompts)
- [x] Include prediction points and winner rank

**Gap found and fixed:** "Include accuracy" was checked off, but the trivia CSV export had no
accuracy column at all — only raw correct/incorrect/missed counts, from which an admin could derive
it manually but the design doc lists `accuracy_percentage` as its own export column. Added
`round(correct / (correct + incorrect + missed) * 100, 1)` as a real column.

Acceptance criteria:

- Admin can download final results
- Export contains the required fields (verified: downloaded both CSVs against a real game with a
  scored trivia answer and a scored prediction, confirmed every column populates correctly)
- Admin can download prediction results
- Public screens still hide private player details

## Phase 13: Testing

Automated PHPUnit feature test suite added under `tests/Feature/Trivia/` and
`tests/Feature/Prediction/` (99 tests, 240 assertions, all passing as of this pass —
`php artisan test`). A new `tests/Concerns/InteractsWithPlayers.php` trait
(`actingAsPlayer()`) handles the player cookie-auth mechanism, which isn't Laravel's
default auth guard and needed a manual encrypted-cookie + `withCredentials()` workaround
for the test client (see bug note below).

- [x] Test registration validation — `RegistrationTest`
- [x] Test duplicate phone prevention — `RegistrationTest` (reconnect + DB-level unique
      constraint)
- [x] Test answer submission validation — `AnswerSubmissionTest`
- [x] Test one answer per question — `AnswerSubmissionTest`, `ReconnectTest`
- [x] Test MC/admin-started trivia prompt — `GameControlTest::test_admin_can_start_a_question`
- [x] Test trivia prompt close — `GameControlTest` (close + missed-answer scoring +
      invalid-state rejection)
- [x] Test multiple-choice question submission — `AnswerSubmissionTest`
- [x] Test disabled answer option cannot be selected — `AnswerSubmissionTest`
- [x] Test open-text question submission — `AnswerSubmissionTest`, `ScoreServiceTest`
- [x] Test open-text accepted-answer matching — `ScoreServiceTest` (case-insensitive, trimmed)
- [x] Test open-text manual review override — `ScoreServiceTest` (defer/approve/reject)
- [x] Test late answer rejection — `AnswerSubmissionTest`
- [x] Test standard scoring — `ScoreServiceTest`
- [x] Test double-points scoring — `ScoreServiceTest`
- [x] Test streak bonus — `ScoreServiceTest` (full 0→200 table via `#[DataProvider]`)
- [x] Test streak reset — `ScoreServiceTest`
- [x] Test leaderboard sorting — `LeaderboardTest`
- [x] Test separate trivia leaderboard — `LeaderboardTest`
- [x] Test separate prediction leaderboard — `PredictionScoringTest`
- [x] Test tie-breaker sorting — `LeaderboardTest` (`TieBreakerService`, sudden-death cases)
- [x] Test disqualified player exclusion — `LeaderboardTest`
- [x] Test CSV export — `ExportTest` (trivia + prediction columns, auth required)
- [x] Test refresh/reconnect behavior — `ReconnectTest` (mid-question restore, existing-answer
      restore blocks resubmission, score persists across reconnect)
- [x] Test prediction submission — `PredictionSubmissionTest`
- [x] Test MC/admin-started prediction prompt — `PredictionSubmissionTest` (live-prompt gating)
- [x] Test prediction prompt close — covered indirectly via live-prompt gating in
      `PredictionSubmissionTest`; the admin `startPrompt`/`closePrompt` controller actions
      themselves are not separately route-tested the way trivia's are in `GameControlTest`
- [x] Test disabled prediction prompts are hidden — `PredictionSubmissionTest`,
      `PredictionDashboardTest`
- [x] Test prediction edit before lock — `PredictionSubmissionTest`
- [x] Test prediction lock — `PredictionSubmissionTest`
- [x] Test official result entry — `PredictionScoringTest::test_full_admin_flow_...`
- [x] Test prediction points calculation — `PredictionScoringTest` (points rule, minute
      tolerance in/out of range)
- [x] Test closest-score winner calculation — `PredictionScoringTest`
      (`test_closest_score_rule_does_not_award_exact_score_bonus`)
- [x] Test prediction dashboard aggregation — `PredictionDashboardTest`

**Test-infrastructure bugs found and fixed while writing these tests:**
1. PHPUnit 12 no longer supports the legacy `@dataProvider` PHPDoc annotation — it silently
   passes zero arguments instead of erroring clearly. Fixed by using the
   `#[DataProvider('...')]` PHP attribute from `PHPUnit\Framework\Attributes\DataProvider`.
2. Laravel's test-client `withCookie()` fails to round-trip on this Laravel version — a
   trivial cookie decrypts to `null` server-side. Worked around by manually replicating
   `CookieValuePrefix::create()` + `encrypt(..., false)` and sending via
   `withUnencryptedCookies()`, combined with `withCredentials()` (required separately, since
   `postJson()`/`getJson()` don't send cookies by default).
3. **Real production bug**, found via a failing test, not by inspection:
   `bootstrap/app.php`'s `shouldRenderJsonWhen()` only matched `api/*` paths, so this app
   (which has no `/api/*` prefix) rendered every framework exception on `/play/*/answer` and
   `/predict/*/answer` as an HTML redirect instead of JSON — silently breaking the Vue
   frontend's `await res.json()` on any validation error. Fixed by adding
   `|| $request->expectsJson()` to the condition. Verified against the real running server
   with curl, not just the test suite.

Acceptance criteria:

- Core scoring tests pass — yes, `php artisan test` is green (99/99)
- Leaderboard tests pass — yes
- Registration and answer validation tests pass — yes
- Manual live flow works across admin, phone, and stage windows — verified manually earlier
  in this project (see Phase 3B/5/8 bug notes); not re-verified in this pass
- Prediction flow works from submission to winner announcement — yes, both by automated test
  (`PredictionScoringTest::test_full_admin_flow_...`) and by the earlier manual click-through

## Phase 14: Event Readiness

- [x] Add seed/demo game — `database/seeders/EventDemoSeeder.php`, wired into
      `DatabaseSeeder`, idempotent (`firstOrCreate`) so re-running `db:seed` against an
      existing `IKOON` game only fills in what's missing
- [x] Load real question bank from `plans/trivia-questions.md` (37 questions, 5 rounds) —
      transcribed exactly into `EventDemoSeeder::triviaRounds()`
- [x] Confirmed: trivia is not time-bound — admin launches questions whenever they choose,
      spread across before/during/after the match. Only Fan Predictions must lock before kick-off.
- [x] Add sample prediction settings for England vs Argentina — seeded (flags, team names,
      `winner_rule: points`)
- [x] Add current available England and Argentina rosters that admin can update — 11 + 11
      players with real names/shirt numbers/positions, plus a neutral "No goalscorer" entry,
      all editable afterward via the existing roster admin UI
- [x] Verify QR link points to the registration route — **checked, and it's wrong.** Decoded
      both files in `qrcode/` (via jsQR against the PNG); the QR encodes
      `https://games.woodnorkgreen.co.ke/`, the bare site root — not `/join/IKOON`. Scanning it
      today would land guests on a page with no join flow. This needs a freshly generated QR
      pointing at the real `/join/IKOON` URL on the production domain before the event; noted in
      the runbook
- [ ] Test with multiple simulated players — not done in this pass; would need a scripted
      multi-browser/multi-cookie run rather than a unit test
- [ ] Test on mobile browser — **cannot be done from this environment**, requires a physical
      device
- [ ] Test stage screen on projector or large display — **cannot be done from this
      environment**, requires physical hardware at the venue
- [ ] Confirm internet/network setup — **cannot be done from this environment**, venue-specific
- [ ] Confirm real-time service credentials — Reverb config exists and was verified locally
      (`Broadcaster` gracefully degrades if it's down); production credentials for the actual
      event still need to be set in `.env` at deploy time
- [x] Confirm export works — verified both via `ExportTest` and, earlier in this project,
      manually against a real scored game
- [x] Confirm prediction lock time — `prediction_lock_at` is configurable per-game in
      `admin.predictions.settings`; the Lock Predictions admin button additionally provides a
      manual override at any time
- [x] Confirm official result entry process — `PredictionSettingController::storeResults()`
      exists, is admin-only, and is covered end-to-end by
      `PredictionScoringTest::test_full_admin_flow_...`
- [x] Prepare fallback admin refresh instructions — `plans/event-day-runbook.md`

Acceptance criteria:

- Full run-through of the confirmed question set works end to end — verified with the real
  37-question bank via the seeder + earlier manual click-through; not re-run as one continuous
  live rehearsal in this pass
- Players can reconnect without losing score — verified by `ReconnectTest`
- Admin can recover by refreshing the page — documented in the runbook; the admin control
  panel has no client-side-only state that a refresh would lose
- Stage screen can recover by refreshing the page — fixed the `GameStatus::Podium` bug that
  was blocking this (see Phase 10); StageController rebuilds `initialState` fresh on every load
- Final podium and export are correct — verified by `LeaderboardTest` + `ExportTest`
- Prediction winners and export are correct — verified by `PredictionScoringTest` + `ExportTest`

**Remaining before the actual event — genuinely not verifiable from this environment:**
mobile-browser test, projector/large-display test, venue network/internet confirmation, and
production Reverb credentials in `.env`. Everything else in this phase is done and verified.

## Suggested Build Order

1. Project setup
2. Database and models
3. Admin question management
4. Player registration
5. Game state/admin controls
6. Player live screen
7. Answer submission and scoring
8. Real-time updates
9. Stage screen
10. Leaderboard and tie-breakers
11. Fan Prediction setup
12. Fan Prediction submission and scoring
13. Export
14. Moderation
15. Testing and event hardening
