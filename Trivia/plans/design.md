# Event Game Platform Design

## Architecture Overview

The platform should be implemented as a Laravel application using Blade for normal pages and Vue for live interactive areas.

The system supports two game types:

- Trivia
- Fan Predictions

Both game types share three main interfaces:

- Player phone interface
- Admin dashboard
- Stage screen interface

Laravel owns all persistent state and scoring decisions. Vue components receive real-time events, render countdowns, collect trivia answers, collect fan predictions, and update the UI.

Both game types use the same live prompt interaction pattern:

```txt
MC/admin starts prompt
    -> Prompt appears on phones and stage
    -> Countdown starts
    -> Players submit one response
    -> Prompt closes by admin action or timer
    -> Server stores and processes the response
    -> Screens return to waiting/result state
```

Trivia questions and prediction prompts should share UI concepts and admin controls, but their data, scoring, and leaderboards remain separate.

## High-Level Flow

```txt
Admin action
    -> Laravel updates game state
    -> Laravel broadcasts event
    -> Player phones update
    -> Stage screen updates
    -> Players submit responses
    -> Laravel validates and stores responses
    -> Laravel processes the closed prompt
    -> Laravel broadcasts the relevant result, dashboard, or leaderboard
```

For Trivia:

```txt
MC/admin starts a trivia question
    -> Question appears on player phones
    -> Stage screen shows active question
    -> Players submit one answer
    -> MC/admin closes question or timer expires
    -> Laravel scores immediately
    -> Stage screen shows reveal and trivia leaderboard
```

For Fan Predictions:

```txt
Admin opens prediction mode
    -> MC/admin starts a prediction prompt
    -> Prompt appears on player phones
    -> Players submit answers within the active prompt window
    -> MC/admin closes the prompt or timer expires
    -> Stage screen shows aggregated prediction dashboard
    -> MC/admin can start another prediction prompt later
    -> Admin locks predictions at configured lock time
    -> Admin enters official results after match
    -> Laravel calculates winners
    -> Stage screen shows prediction winners
```

## Suggested Routes

```txt
GET  /admin
GET  /admin/games
POST /admin/games
GET  /admin/games/{game}
GET  /admin/games/{game}/questions
POST /admin/games/{game}/questions
PATCH /admin/questions/{question}
DELETE /admin/questions/{question}
GET  /admin/games/{game}/predictions
PATCH /admin/games/{game}/predictions/settings
POST /admin/games/{game}/prediction-prompts
PATCH /admin/prediction-prompts/{predictionPrompt}
DELETE /admin/prediction-prompts/{predictionPrompt}
POST /admin/games/{game}/predictions/players
PATCH /admin/prediction-players/{predictionPlayer}
DELETE /admin/prediction-players/{predictionPlayer}

POST /admin/games/{game}/registration/open
POST /admin/games/{game}/registration/close
POST /admin/games/{game}/questions/{question}/start
POST /admin/games/{game}/questions/{question}/close
POST /admin/games/{game}/questions/{question}/reveal
POST /admin/games/{game}/leaderboard/show
POST /admin/games/{game}/podium/show
POST /admin/games/{game}/predictions/open
POST /admin/games/{game}/prediction-prompts/{predictionPrompt}/start
POST /admin/games/{game}/prediction-prompts/{predictionPrompt}/close
POST /admin/games/{game}/predictions/lock
POST /admin/games/{game}/predictions/results
POST /admin/games/{game}/predictions/calculate
POST /admin/games/{game}/predictions/show
POST /admin/games/{game}/pause
POST /admin/games/{game}/cancel
GET  /admin/games/{game}/export

GET  /join/{game:code}
POST /join/{game:code}
GET  /play/{game:code}
POST /play/{game:code}/questions/{question}/answer
GET  /predict/{game:code}
POST /predict/{game:code}

GET  /stage/{game:code}
```

Exact route names can change during implementation, but the separation should stay clear:

- `/admin/*` for event control
- `/join/*` for registration
- `/play/*` for player phones
- `/predict/*` for fan prediction submission
- `/stage/*` for the big screen

The player phone can use one live screen that listens for either active prompt type. The active prompt payload determines whether it renders trivia answer options or a prediction form.

## Database Design

### `games`

Stores event-level configuration and current state.

Suggested fields:

```txt
id
name
code
event_type
status
current_question_id
prediction_status
registration_opened_at
predictions_opened_at
current_prediction_prompt_id
predictions_locked_at
started_at
ended_at
created_at
updated_at
```

### `players`

Stores registered participants.

Suggested fields:

```txt
id
game_id
nickname
phone
email
consent_accepted
mpesa_card_confirmed
session_token
total_score
current_streak
correct_answers
incorrect_answers
missed_answers
double_point_correct_answers
total_correct_response_ms
previous_rank
current_rank
disqualified_at
created_at
updated_at
```

Unique index:

```txt
game_id + phone
```

### `questions`

Stores each trivia question. A trivia question is a live prompt controlled by MC/admin.

Suggested fields:

```txt
id
game_id
round_name
question_text
question_type
duration_seconds
order_number
is_double_points
is_sudden_death
accepted_answers
requires_manual_review
started_at
closed_at
revealed_at
created_at
updated_at
```

`question_type` can be:

```txt
multiple_choice
open_text
```

`accepted_answers` should store an array/JSON list for open-text questions. Matching should be case-insensitive and trim extra whitespace. If `requires_manual_review` is enabled, admin can review submitted open-text answers before finalizing scoring.

Trivia questions and prediction prompts remain in separate tables because they have different configuration and scoring needs. They should still expose similar runtime fields such as `started_at`, `closed_at`, `duration_seconds`, and `order_number`.

### `question_options`

Stores possible answers.

Suggested fields:

```txt
id
question_id
label
option_text
is_correct
is_enabled
order_number
created_at
updated_at
```

Disabled options remain in the database for editing/audit purposes but should not appear on player phones or stage screen.

### `player_answers`

Stores submitted answers.

Suggested fields:

```txt
id
game_id
player_id
question_id
question_option_id
answer_text
is_correct
review_status
response_ms
base_points
speed_bonus
streak_bonus
total_points
answered_at
created_at
updated_at
```

For multiple-choice questions, `question_option_id` is required and `answer_text` can be null.

For open-text questions, `answer_text` is required and `question_option_id` can be null.

`review_status` can be:

```txt
auto_scored
needs_review
approved
rejected
```

Unique index:

```txt
player_id + question_id
```

### `prediction_settings`

Stores match-level Fan Prediction configuration.

Suggested fields:

```txt
id
game_id
team_a_name
team_b_name
team_a_flag
team_b_flag
kickoff_at
prediction_lock_at
allow_prediction_edits
winner_rule
official_team_a_score
official_team_b_score
official_outcome
official_first_goalscorer_id
official_player_of_match_id
official_first_goal_minute
official_results_payload
official_results_entered_at
created_at
updated_at
```

`team_a_name` and `team_b_name` are internal/admin labels. Public player and stage screens should use `team_a_flag` and `team_b_flag` instead of team names.

Flags can be stored as:

```txt
emoji
asset_path
country_code
```

Use `asset_path` if the event design requires specific flag artwork. Use `country_code` or emoji only if that matches the final visual direction.

`official_results_payload` can store official answers for optional prediction prompts that do not deserve dedicated columns.

`winner_rule` can be:

```txt
points
closest_score
```

### `prediction_prompts`

Stores admin-configurable prediction prompts. These behave like trivia questions: MC/admin starts one, it appears on phones, then it closes.

Suggested fields:

```txt
id
game_id
prompt_key
prompt_label
prompt_type
is_enabled
duration_seconds
order_number
started_at
closed_at
created_at
updated_at
```

Suggested `prompt_key` values:

```txt
match_outcome
final_score
first_goalscorer
player_of_match
first_goal_minute
first_team_to_score
both_teams_to_score
total_goals_range
yellow_cards_range
corners_range
first_card
winner_after_penalties
```

Suggested `prompt_type` values:

```txt
flag_choice
score_input
player_select
number_input
range_choice
yes_no
```

### `prediction_players`

Stores selectable match players for first goalscorer and Player of the Match predictions.

Suggested fields:

```txt
id
game_id
team_name
display_name
shirt_number
position
squad_status
is_available
sort_order
created_at
updated_at
```

`squad_status` can be:

```txt
starter
substitute
unknown
unavailable
```

The roster should be seeded with the currently available England and Argentina players during setup. It should remain editable because matchday lineups and substitutes can change.

### `predictions`

Stores each player's overall Fan Prediction score/result summary.

Suggested fields:

```txt
id
game_id
player_id
predicted_outcome
predicted_team_a_score
predicted_team_b_score
predicted_first_goalscorer_id
predicted_player_of_match_id
predicted_first_goal_minute
points
winner_rank
submitted_at
last_edited_at
created_at
updated_at
```

Unique index:

```txt
game_id + player_id
```

### `prediction_answers`

Stores each player's answer to each active prediction prompt.

Suggested fields:

```txt
id
game_id
player_id
prediction_prompt_id
answer_payload
points
is_correct
submitted_at
last_edited_at
created_at
updated_at
```

Unique index:

```txt
player_id + prediction_prompt_id
```

### Optional: `game_events`

Useful for audit/debugging during a live event.

Suggested fields:

```txt
id
game_id
admin_user_id
event_type
payload
created_at
```

### Leaderboard Separation

Leaderboards are calculated separately.

Trivia leaderboard source:

```txt
players
player_answers
questions
```

Prediction leaderboard source:

```txt
players
prediction_answers
prediction_prompts
predictions
prediction_settings
```

Do not combine trivia score and prediction points by default.

## State Design

The game state should be explicit and admin-driven.

Recommended statuses:

```txt
draft
registration_open
waiting
question_live
question_closed
answer_reveal
leaderboard
podium
paused
cancelled
ended
```

Fan Prediction statuses:

```txt
predictions_open
prediction_prompt_live
prediction_prompt_closed
predictions_locked
official_results_pending
prediction_results_ready
```

Only the admin should cause state transitions. Player and stage screens should treat the server as the source of truth.

At runtime, the game should expose an active prompt payload with:

```txt
prompt_mode: trivia | prediction
prompt_id
duration_seconds
started_at
closes_at
payload
```

This lets the same player/stage shell render either a trivia question or a prediction prompt.

## Real-Time Design

Use Laravel broadcasting with Laravel Reverb or Pusher.

Recommended broadcast events:

```txt
RegistrationOpened
RegistrationClosed
QuestionStarted
QuestionClosed
AnswerRevealed
TriviaLeaderboardUpdated
PredictionLeaderboardUpdated
PlayerResultUpdated
GamePaused
GameCancelled
GameEnded
PodiumShown
PredictionsOpened
PredictionPromptStarted
PredictionPromptClosed
PredictionsLocked
PredictionSubmitted
PredictionDashboardUpdated
PredictionResultsEntered
PredictionWinnersCalculated
```

Recommended channels:

```txt
game.{gameId}
stage.{gameId}
player.{playerId}
```

`game.{gameId}` can broadcast public game state changes.

`stage.{gameId}` can broadcast stage-specific display data.

`player.{playerId}` should be private and used for personal score, rank, and answer result.

## Scoring Service

Create a dedicated service instead of scattering scoring logic in controllers.

Suggested classes:

```txt
App\Services\ScoreService
App\Services\TriviaLeaderboardService
App\Services\TieBreakerService
App\Services\PredictionScoringService
App\Services\PredictionLeaderboardService
App\Services\PredictionDashboardService
```

### `ScoreService`

Responsibilities:

- Validate whether an answer is correct
- Score multiple-choice answers by selected option
- Score open-text answers by accepted-answer matching or manual review
- Calculate response time using server timestamps
- Calculate base points
- Calculate speed bonus
- Calculate streak bonus
- Update player totals
- Reset streaks for wrong or missed answers
- Store score breakdown

Open-text scoring rules:

- Normalize submitted answer by trimming extra spaces.
- Compare case-insensitively against accepted answers.
- If exact normalized match is found, score as correct.
- If no match is found and manual review is disabled, score as incorrect.
- If manual review is enabled, mark as `needs_review` and let admin approve or reject before final leaderboard is published.

Pseudo-code:

```txt
if answer is incorrect or missing:
    question_score = 0
    streak = 0

if answer is correct:
    base_score = question.is_double_points ? 1600 : 800
    max_speed_bonus = question.is_double_points ? 400 : 200
    speed_bonus = max_speed_bonus * (time_remaining / total_question_time)
    streak = streak + 1
    streak_bonus = calculate_streak_bonus(streak)
    question_score = base_score + speed_bonus + streak_bonus

total_player_score = previous_total_score + round(question_score)
```

### `TriviaLeaderboardService`

Responsibilities:

- Rank active, non-disqualified players
- Track rank movement after each scored question
- Return top 10 for stage screen
- Return personal rank and gap for player phone

Sort order:

```txt
total_score desc
correct_answers desc
average_correct_response_ms asc
double_point_correct_answers desc
```

### `PredictionLeaderboardService`

Responsibilities:

- Rank active, non-disqualified prediction participants
- Calculate prediction points totals
- Apply prediction tie-breakers
- Return top prediction winners for stage screen
- Return personal prediction result for player phone

Sort order depends on the selected prediction winner rule:

```txt
points desc
exact_score_correct desc
correct_outcome desc
closest_goal_difference asc
correct_first_goalscorer desc
correct_player_of_match desc
closest_first_goal_minute asc
submitted_at asc
```

### `TieBreakerService`

Responsibilities:

- Identify unresolved ties
- Apply tie-breaker order
- Mark whether sudden death is required

### `PredictionScoringService`

Responsibilities:

- Compare submitted predictions against official match results
- Calculate prediction points
- Rank winners
- Support exact-score winner rules
- Support closest-score winner rules
- Use submitted time as final tie-breaker

Recommended points mode:

```txt
exact final score = 5 points
correct outcome = 2 points
correct first goalscorer = 3 points
correct Player of the Match = 3 points
correct first goal minute = 5 points
closest first goal minute = 2 points
```

Recommended closest-score mode:

```txt
exact final score
correct outcome
smallest total goal difference
correct first goalscorer
correct Player of the Match
closest first goal minute
earliest submitted prediction
```

### `PredictionDashboardService`

Responsibilities:

- Count total predictions
- Aggregate predicted outcomes
- Aggregate common scorelines
- Aggregate first goalscorer picks
- Aggregate Player of the Match picks
- Aggregate first goal minute picks
- Ignore disabled prediction prompts in public dashboards unless admin explicitly includes them
- Return safe public display data for stage screen

## Frontend Design

### Blade Pages

Use Blade for:

- Admin shell/layout
- Admin forms
- Game setup
- Question setup
- Registration page
- Export/results page
- Prediction settings page
- Prediction roster management page
- Official result entry page

### Vue Components

Use Vue for:

- Player live screen
- Player answer buttons
- Countdown timer
- Player result panel
- Stage screen
- Stage leaderboard
- Stage podium
- Admin live-control panel if useful
- Prediction form
- Prediction prompt panel
- Prediction dashboard
- Prediction winner display
- Shared live prompt shell

Suggested components:

```txt
resources/js/components/PlayerGame.vue
resources/js/components/LivePromptShell.vue
resources/js/components/StageScreen.vue
resources/js/components/CountdownTimer.vue
resources/js/components/AnswerButtons.vue
resources/js/components/LeaderboardTable.vue
resources/js/components/PodiumDisplay.vue
resources/js/components/PredictionForm.vue
resources/js/components/PredictionPromptPanel.vue
resources/js/components/PredictionDashboard.vue
resources/js/components/PredictionWinners.vue
```

## Player Reconnection Design

After registration, store a player `session_token` in a secure cookie or session.

When a player returns to `/play/{game_code}`:

- Resolve player from session token.
- Confirm the player belongs to the game.
- Reload current game state.
- Reload player score, rank, and current question status.
- Reload existing prediction if the player is using Fan Predictions.
- Do not create a new player if the phone number already exists.

## Validation and Security

Important rules:

- Admin routes require authentication.
- Public registration validates phone number, nickname, and consent.
- Phone number is unique per game.
- Player answer submission checks active question status.
- Player answer submission checks question timer window.
- Player answer submission prevents duplicate answers.
- Multiple-choice answer submission checks that the selected option belongs to the active question and is enabled.
- Open-text answer submission checks that non-empty text was submitted.
- Scoring does not trust client timestamps.
- Public stage screen never shows mobile numbers or emails.
- Prediction submission checks whether predictions are open.
- Prediction answer submission checks that the prompt is currently live.
- Disabled prediction prompts cannot receive submissions.
- Prediction edits are blocked after prediction lock.
- Prediction prompt answers are one submission per player per prompt unless edits are enabled before close/lock.
- Official result entry requires admin authentication.
- Trivia and prediction leaderboard calculations must not read from each other's answer tables.

## Export Design

Use a CSV or Excel export.

Columns:

```txt
rank
nickname
phone
email
total_score
correct_answers
incorrect_answers
missed_answers
accuracy_percentage
average_correct_response_ms
double_point_correct_answers
disqualified
created_at
```

Prediction export columns:

```txt
rank
nickname
phone
email
predicted_outcome
predicted_team_a_score
predicted_team_b_score
predicted_first_goalscorer
predicted_player_of_match
predicted_first_goal_minute
points
winner_rank
submitted_at
last_edited_at
```

CSV is enough for MVP. Excel can be added later if needed.

## Reliability Notes

Live events are sensitive to small failures. Prioritize:

- Clear admin controls
- Refresh-safe player state
- Server-side scoring
- Idempotent answer submission
- Simple state machine
- Minimal dependencies
- Logs for admin actions and scoring events
- Editable prediction roster
- Manual official result entry
- Prediction lock that is enforced server-side

If real-time delivery fails, refresh should restore the correct current state.
