# Event Game Platform Requirements

## Project Summary

Build a live event game platform for a corporate football viewing event. The platform should support two game experiences:

- Kahoot-style trivia
- Digital fan predictions

Both experiences have three access points:

- Player phone view
- Admin dashboard
- Stage screen view

The platform should support registration by QR code, real-time stage display, admin-controlled event flow, winner calculation, final podium/results display, and exportable results.

## Event Context

The event is a semi-final viewing event for the England vs Argentina match.

The platform should allow admins to configure match-specific data such as:

- Internal team labels
- Team flags for public display
- Kick-off time
- Prediction lock time
- Available players for first goalscorer
- Available players for Player of the Match

The app should be seeded with the currently available England and Argentina player rosters, including substitutes where available. The roster must still be admin-editable because final matchday squads can change, so admins should be able to add, remove, disable, or update players close to the event.

Client-facing screens should use flags instead of team names. Team names may be stored internally for admin clarity, roster management, exports, and scoring, but the player prediction form and stage screen should present the matchup using flags only.

## Recommended Stack

- Backend: Laravel
- Frontend: Blade-first with Vue components for live interactive screens
- Real-time: Laravel Reverb or Pusher
- Database: MySQL or PostgreSQL
- Styling: Tailwind CSS
- Build tooling: Vite

Blade should handle standard pages and admin forms. Vue should be used for the player live game screen, prediction form, stage screen, countdowns, answer selection, prediction dashboards, and real-time leaderboard updates.

## Game Types

### Trivia

Trivia is an MC/admin-triggered quiz where questions appear on player phones only when the MC/admin starts them. The system scores correct answers based on accuracy, response speed, and streak bonuses.

### Fan Predictions

Fan Predictions is an MC/admin-triggered prediction game. Guests scan a QR code on arrival, then prediction prompts appear on their phones when the MC/admin starts them, similar to trivia questions.

Prediction fields:

- Final match outcome
- Final score
- First goalscorer
- Player of the Match

Optional prediction fields:

- Half-time score
- First team to score
- Total goals range
- Winner after penalties, if relevant
- First goal minute
- First card
- Number of corners range
- Number of yellow cards range
- Will both teams score?

Predictions should be displayed live on the stage screen in an aggregated format to create conversation among guests. Winners should be calculated and announced after the match.

## User Roles

### Player

Players join individually using a QR code or short URL. A player can participate in trivia, fan predictions, or both depending on which game types are enabled for the event.

### Admin

The admin controls the event flow, creates questions, manages prediction settings, starts and closes questions, locks predictions, reveals answers, manages players, shows leaderboards/results, triggers sudden death if required, and exports final results.

### Stage Screen

The stage screen is the public event display shown to the audience. It should be clean, readable from a distance, and controlled by the admin game state. It should support both trivia screens and prediction result dashboards.

## Player Registration Requirements

Each participant should register with:

- First name or nickname
- Mobile number
- Optional email address
- Consent to participate
- Optional confirmation that they have an M-PESA GlobalPay Virtual Visa Card

Rules:

- Mobile number is the unique identifier per game.
- Duplicate mobile numbers should not create duplicate active players.
- Player phone numbers and personal details must not be shown publicly.
- A player should be able to reconnect without losing score.
- Inappropriate nicknames can be removed or edited by admin.
- Duplicate or invalid players can be disqualified by admin.

## Shared Game Flow Requirements

1. Admin opens registration.
2. Players join through QR code or short URL.
3. Player phones show a waiting state.
4. MC/admin starts a live prompt.
5. The prompt appears on the stage screen and player phones.
6. A countdown runs for the configured prompt duration.
7. Players submit one answer/prediction for the active prompt.
8. Once submitted, the response cannot be changed unless that prompt explicitly allows edits.
9. When time expires or admin closes the prompt, submissions stop.
10. The system stores the response server-side.
11. Trivia prompts are scored immediately after close.
12. Prediction prompts are scored after official results are entered.
13. Stage screen shows the relevant result, reveal, dashboard, leaderboard, or winner view.
14. Player phones return to waiting state until the next live prompt.

Live prompt types:

- Trivia question
- Fan prediction prompt

Each type uses the same MC/admin launch pattern but has separate scoring and leaderboard logic.

## Fan Prediction Flow Requirements

1. Admin configures the match, internal team labels, public flags, prediction fields, and player roster.
2. Admin chooses which prediction prompts are active for the event.
3. Guests scan a QR code or visit the short URL.
4. Guests register or confirm their existing registration.
5. MC/admin starts a prediction prompt.
6. The prediction form appears on player phones and the stage screen shows the active prompt.
7. Players submit their predictions within the configured time window.
8. Admin closes the active prediction prompt, or it closes automatically when time expires.
9. MC/admin can start another prediction prompt later, such as before kick-off, at half-time, or before a key segment.
10. Predictions lock automatically at the configured lock time or manually by admin.
11. Stage screen displays live aggregate prediction data before and during the match.
12. After the match, admin enters official results.
13. System calculates closest/correct predictions.
14. Stage screen displays winners.
15. Admin exports prediction results.

## Game States

Each game should have one current state:

- `draft`
- `registration_open`
- `waiting`
- `question_live`
- `question_closed`
- `answer_reveal`
- `leaderboard`
- `podium`
- `paused`
- `cancelled`
- `ended`

For Fan Predictions, the game should also support:

- `predictions_open`
- `prediction_prompt_live`
- `prediction_prompt_closed`
- `predictions_locked`
- `official_results_pending`
- `prediction_results_ready`

The admin controls state transitions. Player and stage screens react to state changes.

## Game Separation Requirements

Players register once for the event but the two game modes must remain separate in scoring, results, and leaderboards.

Shared across both game modes:

- Registration
- Player profile
- Admin dashboard shell
- Stage screen shell
- Live prompt waiting state

Separated by game mode:

- Trivia questions, answers, scoring, leaderboard, and podium
- Prediction prompts, answers, scoring, leaderboard, and winner display

The system should not combine trivia score and prediction points into one overall winner unless the client explicitly asks for an overall event champion.

## Question Requirements

Each trivia question is a live prompt. Each question should include:

- Game
- Round name
- Question text
- Question type
- Answer options, for multiple-choice questions
- Correct answer
- Accepted answers, for open-text questions
- Question duration in seconds
- Order number
- Difficulty label, optional
- Standard or double-points setting
- Sudden-death setting, optional

Supported question types:

- `multiple_choice`
- `open_text`

Trivia authoring requirements:

- Admin can add, edit, reorder, duplicate, and remove trivia questions.
- Questions must be stored in the database, not hardcoded.
- Admin can choose whether each question is multiple-choice or open-text.
- Multiple-choice questions can have up to four answer options by default.
- Admin can add fewer than four answer options if needed.
- Admin can disable a specific answer option without deleting it.
- Disabled answer options should not appear on player phones or stage screen.
- Admin can mark one answer option as correct for multiple-choice questions.
- Open-text questions should allow admin to define one or more accepted answers.
- Open-text answer matching should be case-insensitive and trim extra spaces.
- Open-text answers should support manual review/override by admin if needed.

Trivia prompt requirements:

- Admin can start a trivia question manually.
- Admin can close a trivia question manually.
- A trivia question can also close automatically after its countdown.
- Player phones should show a waiting state until MC/admin starts the question.
- Stage screen should show the active question only when MC/admin starts it.

Game structure, per the client-supplied question set (see `plans/trivia-questions.md` for the full
question bank and answer key):

- Round 1: General Knowledge (Fastest Finger), 8 questions
- Round 2: FIFA World Cup, 8 questions
- Round 3: Visa Questions, 8 questions
- Round 4: M-Pesa Questions, 8 questions
- Round 5: Campaign Questions, 5 questions
- Final tie-breaker, activated only if required

Total: 37 questions. This is materially larger than the original 15-question / 12-15 minute MVP
estimate, and that is fine by design: trivia is not time-bound. The admin can launch trivia
questions whenever they choose, spread across before, during breaks in, and after the match —
there is no requirement to run all 37 in one sitting or within a fixed window. Fan Predictions is
the only game mode with a hard time constraint, since predictions must be submitted and locked
before kick-off. None of the 37 trivia questions are pre-marked as double-points or sudden-death
by the client; those designations remain admin-configurable per question.

## Fan Prediction Requirements

Each prediction game should include:

- Game
- Match title
- Internal Team A name
- Internal Team B name
- Team A flag
- Team B flag
- Kick-off time
- Prediction lock time
- Available prediction fields
- Active/inactive status for each prediction field
- Player roster for selectable player-based predictions
- Official match result, entered after the match

Each player prediction should include:

- Player
- Predicted outcome
- Predicted final score
- Predicted first goalscorer, if enabled
- Predicted Player of the Match, if enabled
- Predicted first goal minute, if enabled
- Other enabled prediction answers
- Submitted time
- Last edited time, if edits are allowed
- Points or winner status after official results are entered

Prediction prompt requirements:

- Admin can create, edit, enable, disable, and reorder prediction prompts.
- Admin can start a prediction prompt manually, similar to starting a trivia question.
- Admin can close a prediction prompt manually.
- A prediction prompt can also close automatically after a configured duration.
- Disabled prompts should not appear on player phones, stage screen, scoring, or exports unless admin explicitly includes them.
- Player phones should show a waiting state until MC/admin starts a prediction prompt.

Roster requirements:

- The system should start with the currently available England and Argentina rosters.
- Admin can add players manually.
- Admin can remove or disable players.
- Admin can mark players as England or Argentina.
- Admin can mark players as starter, substitute, unknown, or unavailable.
- Admin can import, seed, or update roster data before the event.
- Admin can disable player names that should not appear as prediction options.
- The system should include "No goalscorer" as an option for first goalscorer.

Public display requirements:

- The prediction form should show flags for the match outcome and score prediction UI.
- The stage prediction dashboard should show flags instead of team names.
- Team names should not be used on the public/player-facing prediction screens unless the client later approves it.

## Answer Submission Requirements

- A player can submit only one answer per question.
- Submitted answers cannot be changed.
- Late answers receive 0 points.
- Incorrect answers receive 0 points.
- Missed answers receive 0 points.
- Scoring must use server-side timestamps, not phone timestamps.
- The system should store response time for each answer.

## Scoring Requirements

These scoring rules apply to Trivia.

### Standard Question

- Maximum base score: 1,000 points before streak bonus
- Correctness component: 800 points
- Speed component: up to 200 points
- Incorrect answer: 0 points
- No answer: 0 points

Formula for a correct answer:

```txt
score = 800 + speed_bonus + streak_bonus
speed_bonus = 200 * (time_remaining / total_question_time)
```

### Double-Points Question

- Maximum base score: 2,000 points before streak bonus
- Correctness component: 1,600 points
- Speed component: up to 400 points

Formula for a correct double-points answer:

```txt
score = 1600 + speed_bonus + streak_bonus
speed_bonus = 400 * (time_remaining / total_question_time)
```

### Streak Bonus

Consecutive correct answers should award small bonus points:

| Streak | Bonus |
| --- | ---: |
| 2 correct | 50 |
| 3 correct | 100 |
| 4 correct | 150 |
| 5 or more correct | 200 |

A wrong or missed answer resets the streak to zero.

### Tie-Breakers

Tie-breakers should be applied in this order:

1. Highest total score
2. Highest number of correct answers
3. Fastest average response time across correctly answered questions
4. Highest number of double-point questions answered correctly
5. Sudden-death question

For top prize ties, sudden death should be preferred over silently selecting a winner.

## Fan Prediction Winner Rules

The prediction game should support simple configurable scoring. Recommended MVP scoring:

| Prediction | Points |
| --- | ---: |
| Exact final score | 5 |
| Correct match outcome | 2 |
| Correct first goalscorer | 3 |
| Correct Player of the Match | 3 |
| Correct first goal minute | 5 |
| Closest first goal minute | 2 |

Outcome means England win, Argentina win, or draw if the prediction is based on normal/full-time result.

If the client wants "closest prediction" instead of points only, use this order:

1. Exact final score
2. Correct match outcome
3. Smallest total goal difference from actual score
4. Correct first goalscorer
5. Correct Player of the Match
6. Closest first goal minute
7. Earliest submitted prediction

Admin should be able to choose whether winners are selected by points or closest-score rules.

## Leaderboard Requirements

Trivia and Fan Predictions must have separate leaderboards.

The trivia leaderboard updates after every scored trivia question.

Stage screen should show:

- Current rank
- Player nickname
- Total score
- Movement since previous question
- Correct-answer streak
- Top 10 players only

Player phone should show:

- Current score
- Current rank
- Points earned for last question
- Whether their answer was correct
- Current answer streak
- Points gap between them and the player immediately above

Fan Prediction leaderboard should show:

- Prediction rank
- Player nickname
- Prediction points
- Correct/closest prediction count
- Tie-breaker position where useful

Prediction leaderboard should update after official results are entered and prediction winners are calculated.

## Fan Prediction Display Requirements

Stage screen should show aggregated prediction data, not private player information.

Before/during the match, stage screen can show:

- Number of predictions submitted
- Most predicted outcome
- Most common scorelines
- First goalscorer prediction breakdown
- Player of the Match prediction breakdown
- Recent prediction nicknames, without phone numbers or emails

After the match, stage screen should show:

- Prediction winners
- Winning prediction details
- Closest score predictions
- Optional prize fulfilment status for admin only

Player phone should show:

- Their submitted predictions
- Whether predictions are open or locked
- Edit option before lock, if enabled
- Their result after official results are entered

## Admin Dashboard Requirements

Admin should be able to:

- Login securely
- Create and edit a game
- Add, edit, reorder, and remove questions
- Add answer options and mark the correct answer
- Mark questions as standard, double-points, or sudden-death
- Open and close registration
- Start, pause, close, cancel, or reveal a question
- Show leaderboard
- Move to next question
- Show final podium
- Trigger sudden-death question when required
- View registered players
- Edit or remove inappropriate nicknames
- Disqualify players
- View answer submissions
- Export results
- Configure Fan Prediction game
- Create, enable, disable, and reorder prediction prompts
- Start and close prediction prompts like trivia questions
- Open, lock, and unlock predictions when appropriate
- Manage team/player roster
- Enter official match result
- Calculate prediction winners
- Show prediction dashboard on stage screen
- Export prediction results

## Export Requirements

Exportable results should include:

- Player nickname
- Mobile number
- Optional email
- Total score
- Final rank
- Correct answer count
- Incorrect answer count
- Missed answer count
- Accuracy percentage
- Average response time for correct answers
- Double-point correct count
- Disqualification status

Fan Prediction export should include:

- Player nickname
- Mobile number
- Optional email
- Predicted outcome
- Predicted final score
- Predicted first goalscorer
- Predicted Player of the Match
- Submitted time
- Last edited time
- Prediction points
- Winner status
- Prize fulfilment status, if tracked

## Non-Functional Requirements

- The system must remain stable during a live event.
- Player refresh/reconnect must not reset score.
- Score calculation must be server-side.
- Public screens must not expose mobile numbers, emails, or personal details.
- Stage screen must be readable from a distance.
- Admin actions should be clear and difficult to misclick during the event.
- The system should work well on common mobile browsers.
- Real-time failures should recover on refresh.

## MVP Scope

The first version should include:

- Admin login
- One game setup
- Question and option management
- Player registration
- QR join route
- Admin-controlled game state
- Live question delivery
- One-answer submission
- Server-side scoring
- Player personal result screen
- Stage question display
- Stage top-10 leaderboard
- Final podium
- CSV export
- Fan Prediction setup
- Player prediction form
- Prediction lock
- Stage prediction dashboard
- Official result entry
- Prediction winner calculation

## Version Two Candidates

These should be deferred unless the client explicitly requires them:

- SMS or OTP phone verification
- Multiple simultaneous games
- Advanced analytics dashboard
- Deep nickname moderation workflow
- Branding editor
- Complex animations
- Sponsor CMS
- Multi-admin permission levels
- Automated sports data feed
- Automatic match roster syncing
