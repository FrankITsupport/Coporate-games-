<script setup>
/**
 * PlayerGame.vue — Phase 7 / 8 player live screen.
 *
 * Reacts to Laravel Echo broadcasts on:
 *   - game.{gameId}   — public, game state events
 *   - player.{token}  — public (token-keyed), personal score updates
 *
 * States handled:
 *   waiting / registration_open / paused / cancelled / ended
 *   question_live  → answer selection / text input → submitted
 *   question_closed / answer_reveal  → reveals correct answer
 *   leaderboard → personal rank panel
 *   podium       → final podium view
 */
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue';
import CountdownTimer from './CountdownTimer.vue';

const props = defineProps({
    initialState:  { type: Object, required: true },
    gameCode:      { type: String, required: true },
    answerUrlTpl:  { type: String, required: true },
    csrf:          { type: String, required: true },
});

// ─── Reactive state ───────────────────────────────────────────────────────────

const gameStatus    = ref(props.initialState.game_status ?? 'waiting');
const activePrompt  = ref(props.initialState.active_prompt ?? null);
const player        = reactive(props.initialState.player ?? {});
const pointsGap     = ref(props.initialState.points_gap ?? null);

// Answer state
const selectedOptionId  = ref(null);
const openTextAnswer    = ref('');
const submitted         = ref(false);
const submitting        = ref(false);
const submitError       = ref(null);
const lastResult        = ref(null);           // { is_correct, total_points, … }
const timerExpired      = ref(false);

// Seeded with any existing answer (page refresh mid-question)
if (props.initialState.existing_answer) {
    submitted.value = true;
    if (props.initialState.existing_answer.question_option_id) {
        selectedOptionId.value = props.initialState.existing_answer.question_option_id;
    }
    if (props.initialState.existing_answer.answer_text) {
        openTextAnswer.value = props.initialState.existing_answer.answer_text;
    }
}

// Reveal data (from AnswerRevealed broadcast)
const revealData = ref(null);   // { correct_option_id, accepted_answers }

// Leaderboard / podium
const podiumPlayers = ref([]);

// ─── Computed helpers ─────────────────────────────────────────────────────────

const prompt = computed(() => activePrompt.value?.payload ?? null);

const isMultipleChoice = computed(() =>
    prompt.value?.question_type === 'multiple_choice'
);

// 'up' | 'down' | 'same' | null (null = no prior rank to compare against, e.g. first question)
const rankMovement = computed(() => {
    const current = player.current_rank;
    const previous = player.previous_rank;
    if (!current || !previous) return null;
    if (current < previous) return 'up';
    if (current > previous) return 'down';
    return 'same';
});

const canSubmit = computed(() =>
    !submitted.value &&
    !submitting.value &&
    !timerExpired.value &&
    gameStatus.value === 'question_live' &&
    (isMultipleChoice.value ? selectedOptionId.value !== null : openTextAnswer.value.trim() !== '')
);

function optionState(optionId) {
    if (!submitted.value && revealData.value === null) {
        return selectedOptionId.value === optionId ? 'selected' : 'idle';
    }
    if (revealData.value) {
        if (optionId === revealData.value.correct_option_id) return 'correct';
        if (optionId === selectedOptionId.value) return 'wrong';
    }
    if (submitted.value && optionId === selectedOptionId.value) return 'submitted';
    return 'idle';
}

// ─── Answer submission ────────────────────────────────────────────────────────

async function submitAnswer() {
    if (!canSubmit.value) return;

    submitting.value = true;
    submitError.value = null;

    const url = props.answerUrlTpl.replace('__ID__', activePrompt.value.prompt_id);
    const body = isMultipleChoice.value
        ? { option_id: selectedOptionId.value }
        : { answer_text: openTextAnswer.value.trim() };

    try {
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept':       'application/json',
                'X-CSRF-TOKEN': props.csrf,
            },
            body: JSON.stringify(body),
        });

        const data = await res.json();

        if (!res.ok) {
            submitError.value = data.error ?? 'Could not submit your answer. Please try again.';
        } else {
            submitted.value = true;
            // A needs_review open-text answer has no result yet — leave lastResult
            // null so the template falls into the "waiting for the result" state
            // instead of showing a premature Correct/Wrong.
            lastResult.value = data.pending_review ? null : data;
            // Optimistically update player stats from the response
            if (data.player) {
                player.total_score    = data.player.total_score;
                player.current_rank   = data.player.current_rank;
                player.current_streak = data.player.current_streak;
            }
        }
    } catch {
        submitError.value = 'Network error. Please try again.';
    } finally {
        submitting.value = false;
    }
}

// ─── Real-time ────────────────────────────────────────────────────────────────

let gameChannel   = null;
let playerChannel = null;

onMounted(() => {
    if (!window.Echo) return;

    // Extract the numeric game ID from the initial state (not available directly,
    // so we subscribe to game.{code} won't work — we need the ID from initialState)
    // The game ID isn't in the player object, but we can get it from the active prompt
    // or from a dedicated field. Let's add it via the initial state.
    const gameId = props.initialState.game_id;
    if (!gameId) {
        console.warn('[PlayerGame] game_id missing from initialState — real-time disabled.');
        return;
    }

    gameChannel = window.Echo.channel(`game.${gameId}`)
        .listen('.QuestionStarted', onQuestionStarted)
        .listen('.QuestionClosed',  onQuestionClosed)
        .listen('.AnswerRevealed',  onAnswerRevealed)
        .listen('.TriviaLeaderboardUpdated', onLeaderboardUpdated)
        .listen('.PodiumShown',     onPodiumShown)
        .listen('.GamePaused',      (e) => { gameStatus.value = 'paused'; })
        .listen('.GameResumed',     (e) => { gameStatus.value = e.status; })
        .listen('.GameCancelled',   (e) => { gameStatus.value = 'cancelled'; })
        .listen('.RegistrationOpened', () => { gameStatus.value = 'registration_open'; })
        .listen('.RegistrationClosed', () => { gameStatus.value = 'waiting'; });

    playerChannel = window.Echo.channel(`player.${player.session_token}`)
        .listen('.PlayerResultUpdated', onPlayerResultUpdated);
});

onUnmounted(() => {
    gameChannel?.stopListening('.QuestionStarted');
    playerChannel?.stopListening('.PlayerResultUpdated');
});

// ─── Event handlers ───────────────────────────────────────────────────────────

function onQuestionStarted(e) {
    activePrompt.value      = e;
    gameStatus.value        = 'question_live';
    submitted.value         = false;
    submitting.value        = false;
    submitError.value       = null;
    selectedOptionId.value  = null;
    openTextAnswer.value    = '';
    lastResult.value        = null;
    revealData.value        = null;
    timerExpired.value      = false;
    podiumPlayers.value     = [];
}

function onQuestionClosed(e) {
    gameStatus.value = 'question_closed';
}

function onAnswerRevealed(e) {
    revealData.value = e;
    gameStatus.value = 'answer_reveal';
}

function onLeaderboardUpdated(e) {
    gameStatus.value = 'leaderboard';
}

function onPodiumShown(e) {
    podiumPlayers.value = e.podium ?? [];
    gameStatus.value = 'podium';
}

function onPlayerResultUpdated(e) {
    player.total_score    = e.total_score;
    player.current_rank   = e.current_rank;
    player.previous_rank  = e.previous_rank;
    player.current_streak = e.current_streak;
    pointsGap.value       = e.points_gap;

    // A pending manual-review answer was just approved/rejected — if it's for
    // the question we're currently showing, surface the result now.
    if (e.last_answer && activePrompt.value && e.last_answer.question_id === activePrompt.value.prompt_id) {
        lastResult.value = e.last_answer;
    }
}

function onTimerExpired() {
    timerExpired.value = true;
}
</script>

<template>
    <div class="flex min-h-screen flex-col">

        <!-- ── WAITING / REGISTRATION ── -->
        <div v-if="['waiting', 'registration_open', 'leaderboard'].includes(gameStatus)" class="flex flex-1 flex-col items-center justify-center gap-6 p-6 text-center">
            <div class="text-5xl">⏳</div>
            <h1 class="text-xl font-semibold">Hey, {{ player.nickname }}!</h1>
            <p class="text-sm text-gray-400">
                <template v-if="gameStatus === 'registration_open'">Registration is open — waiting for the game to start…</template>
                <template v-else-if="gameStatus === 'leaderboard'">Leaderboard is showing on screen…</template>
                <template v-else>Waiting for the next question…</template>
            </p>
            <!-- Score mini-panel -->
            <div class="mt-4 flex gap-6 text-sm text-gray-400">
                <div class="text-center">
                    <div class="text-2xl font-bold text-white">{{ player.total_score ?? 0 }}</div>
                    <div>Score</div>
                </div>
                <div class="text-center">
                    <div class="flex items-center justify-center gap-1 text-2xl font-bold text-white">
                        {{ player.current_rank ?? '—' }}
                        <span v-if="rankMovement === 'up'" class="text-base text-green-400">▲</span>
                        <span v-else-if="rankMovement === 'down'" class="text-base text-red-400">▼</span>
                    </div>
                    <div>Rank</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-white">{{ player.current_streak ?? 0 }}</div>
                    <div>Streak 🔥</div>
                </div>
                <div v-if="pointsGap !== null" class="text-center">
                    <div class="text-2xl font-bold text-white">{{ pointsGap }}</div>
                    <div>Gap to above</div>
                </div>
            </div>
        </div>

        <!-- ── PAUSED ── -->
        <div v-else-if="gameStatus === 'paused'" class="flex flex-1 flex-col items-center justify-center gap-4 p-6 text-center">
            <div class="text-5xl">⏸</div>
            <h2 class="text-lg font-semibold">Game paused</h2>
            <p class="text-sm text-gray-400">Hang tight — we'll be back shortly.</p>
        </div>

        <!-- ── CANCELLED ── -->
        <div v-else-if="gameStatus === 'cancelled'" class="flex flex-1 flex-col items-center justify-center gap-4 p-6 text-center">
            <div class="text-5xl">🚫</div>
            <h2 class="text-lg font-semibold">Game ended</h2>
            <p class="text-sm text-gray-400">Thanks for playing!</p>
        </div>

        <!-- ── PODIUM ── -->
        <div v-else-if="gameStatus === 'podium'" class="flex flex-1 flex-col items-center justify-center gap-6 p-6 text-center">
            <div class="text-5xl">🏆</div>
            <h2 class="text-2xl font-bold">Final Results</h2>
            <div v-if="podiumPlayers.length" class="mt-4 w-full space-y-3">
                <div
                    v-for="(p, i) in podiumPlayers"
                    :key="p.id"
                    class="flex items-center gap-4 rounded-xl bg-white/5 px-4 py-3"
                >
                    <span class="text-2xl">{{ ['🥇','🥈','🥉'][i] }}</span>
                    <span class="flex-1 text-left font-semibold">{{ p.nickname }}</span>
                    <span class="font-mono text-sm text-gray-300">{{ p.total_score }}</span>
                </div>
            </div>
            <!-- Player's own final score -->
            <p class="mt-4 text-sm text-gray-400">
                Your score: <span class="font-bold text-white">{{ player.total_score ?? 0 }}</span>
                <template v-if="player.current_rank"> · Rank <span class="font-bold text-white">{{ player.current_rank }}</span></template>
            </p>
        </div>

        <!-- ── LIVE QUESTION ── -->
        <div v-else-if="gameStatus === 'question_live' && activePrompt" class="flex flex-1 flex-col gap-4 p-4">

            <!-- Header: round + double points badge -->
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium uppercase tracking-widest text-gray-400">
                    {{ prompt?.round_name }}
                </span>
                <span v-if="prompt?.is_double_points" class="rounded-full bg-brand-gold px-2 py-0.5 text-xs font-bold text-black">
                    2×
                </span>
            </div>

            <!-- Timer -->
            <CountdownTimer
                :starts-at="activePrompt.started_at"
                :closes-at="activePrompt.closes_at"
                :duration-seconds="activePrompt.duration_seconds"
                @expired="onTimerExpired"
            />

            <!-- Question text -->
            <p class="text-lg font-semibold leading-snug">{{ prompt?.question_text }}</p>

            <!-- ── Multiple choice ── -->
            <div v-if="isMultipleChoice" class="mt-2 grid grid-cols-2 gap-3">
                <button
                    v-for="opt in (prompt?.options ?? [])"
                    :key="opt.id"
                    type="button"
                    :disabled="submitted || timerExpired || submitting"
                    :class="[
                        'relative flex flex-col items-center justify-center rounded-xl p-4 text-sm font-medium transition',
                        optionState(opt.id) === 'idle'      && 'bg-white/10 hover:bg-white/20 active:scale-95',
                        optionState(opt.id) === 'selected'  && 'bg-brand-green text-white ring-2 ring-brand-lime',
                        optionState(opt.id) === 'submitted' && 'bg-brand-green text-white',
                        optionState(opt.id) === 'correct'   && 'bg-green-500 text-white',
                        optionState(opt.id) === 'wrong'     && 'bg-red-500/60 text-white line-through',
                    ]"
                    @click="selectedOptionId = opt.id"
                >
                    <span class="mb-1 text-xs font-bold uppercase text-white/60">{{ opt.label }}</span>
                    {{ opt.option_text }}
                </button>
            </div>

            <!-- ── Open text ── -->
            <div v-else class="mt-2 flex flex-col gap-3">
                <input
                    v-model="openTextAnswer"
                    type="text"
                    placeholder="Type your answer…"
                    maxlength="500"
                    :disabled="submitted || timerExpired || submitting"
                    class="w-full rounded-xl bg-white/10 px-4 py-3 text-white placeholder-gray-500 outline-none focus:ring-2 focus:ring-brand-lime disabled:opacity-50"
                    @keyup.enter="submitAnswer"
                />
            </div>

            <!-- Submit button -->
            <button
                v-if="!submitted"
                type="button"
                :disabled="!canSubmit"
                class="mt-auto w-full rounded-xl py-4 text-center text-sm font-bold uppercase tracking-widest transition disabled:cursor-not-allowed disabled:opacity-40"
                :class="canSubmit ? 'bg-brand-green hover:bg-brand-lime active:scale-95' : 'bg-white/10'"
                @click="submitAnswer"
            >
                <span v-if="submitting">Submitting…</span>
                <span v-else-if="timerExpired">Time's up!</span>
                <span v-else>Lock in answer</span>
            </button>

            <!-- Submitted feedback -->
            <div v-if="submitted && lastResult" class="mt-2 rounded-xl p-4 text-center"
                :class="lastResult.is_correct ? 'bg-green-500/20' : 'bg-red-500/20'"
            >
                <p class="text-xl font-bold" :class="lastResult.is_correct ? 'text-green-400' : 'text-red-400'">
                    {{ lastResult.is_correct ? '✓ Correct!' : '✗ Wrong' }}
                </p>
                <p v-if="lastResult.total_points" class="mt-1 text-sm text-gray-300">
                    +{{ lastResult.total_points }} pts
                    <template v-if="lastResult.streak_bonus > 0"> (🔥 streak +{{ lastResult.streak_bonus }})</template>
                </p>
            </div>

            <!-- Submitted, no result yet (open-text needs review) -->
            <div v-else-if="submitted" class="mt-2 rounded-xl bg-brand-lime/20 p-4 text-center">
                <p class="text-sm text-brand-lime">Answer submitted. Waiting for the result…</p>
            </div>

            <!-- Error -->
            <p v-if="submitError" class="text-center text-sm text-red-400">{{ submitError }}</p>

            <!-- Score strip at the bottom -->
            <div class="mt-auto flex items-center justify-between text-xs text-gray-500">
                <span>Score: <span class="font-semibold text-white">{{ player.total_score ?? 0 }}</span></span>
                <span>Rank: <span class="font-semibold text-white">{{ player.current_rank ?? '—' }}</span></span>
                <span>🔥 {{ player.current_streak ?? 0 }}</span>
            </div>
        </div>

        <!-- ── QUESTION CLOSED (scored, waiting for reveal) ── -->
        <div v-else-if="gameStatus === 'question_closed'" class="flex flex-1 flex-col items-center justify-center gap-4 p-6 text-center">
            <div class="text-4xl">🔒</div>
            <h2 class="text-lg font-semibold">Question closed</h2>
            <p class="text-sm text-gray-400">Revealing the answer shortly…</p>
            <div v-if="submitted && lastResult" class="mt-4 rounded-xl px-6 py-4"
                :class="lastResult.is_correct ? 'bg-green-500/20' : 'bg-red-500/20'"
            >
                <p class="font-bold" :class="lastResult.is_correct ? 'text-green-400' : 'text-red-400'">
                    {{ lastResult.is_correct ? '✓ Correct' : '✗ Wrong' }}
                </p>
                <p v-if="lastResult.total_points" class="mt-1 text-sm text-gray-300">+{{ lastResult.total_points }} pts</p>
            </div>
        </div>

        <!-- ── ANSWER REVEAL ── -->
        <div v-else-if="gameStatus === 'answer_reveal'" class="flex flex-1 flex-col items-center justify-center gap-4 p-6 text-center">
            <div class="text-4xl">✅</div>
            <h2 class="text-lg font-semibold">Answer Revealed</h2>

            <!-- Multiple choice: highlight the correct option -->
            <div v-if="isMultipleChoice && prompt?.options" class="mt-2 w-full grid grid-cols-2 gap-3">
                <div
                    v-for="opt in (prompt?.options ?? [])"
                    :key="opt.id"
                    class="flex flex-col items-center justify-center rounded-xl p-4 text-sm font-medium"
                    :class="[
                        revealData && opt.id === revealData.correct_option_id ? 'bg-green-500 text-white' :
                        submitted && opt.id === selectedOptionId ? 'bg-red-500/60 text-white' : 'bg-white/5 text-gray-400',
                    ]"
                >
                    <span class="mb-1 text-xs font-bold uppercase text-white/50">{{ opt.label }}</span>
                    {{ opt.option_text }}
                </div>
            </div>

            <!-- Open text: show accepted answers -->
            <div v-else-if="!isMultipleChoice && revealData?.accepted_answers?.length" class="mt-2 text-sm text-gray-300">
                <p class="mb-1 text-xs uppercase tracking-widest text-gray-500">Accepted answers</p>
                <p v-for="ans in revealData.accepted_answers" :key="ans" class="font-medium text-green-400">{{ ans }}</p>
            </div>

            <!-- Personal result -->
            <div v-if="submitted && lastResult" class="mt-4 rounded-xl px-6 py-4"
                :class="lastResult.is_correct ? 'bg-green-500/20' : 'bg-red-500/20'"
            >
                <p class="font-bold" :class="lastResult.is_correct ? 'text-green-400' : 'text-red-400'">
                    {{ lastResult.is_correct ? '✓ Correct' : '✗ Wrong' }}
                </p>
                <p v-if="lastResult.total_points" class="mt-1 text-sm text-gray-300">+{{ lastResult.total_points }} pts</p>
            </div>

            <!-- Score strip -->
            <div class="mt-4 flex gap-6 text-sm text-gray-400">
                <div class="text-center"><div class="text-xl font-bold text-white">{{ player.total_score ?? 0 }}</div><div>Score</div></div>
                <div class="text-center">
                    <div class="flex items-center justify-center gap-1 text-xl font-bold text-white">
                        {{ player.current_rank ?? '—' }}
                        <span v-if="rankMovement === 'up'" class="text-sm text-green-400">▲</span>
                        <span v-else-if="rankMovement === 'down'" class="text-sm text-red-400">▼</span>
                    </div>
                    <div>Rank</div>
                </div>
                <div class="text-center"><div class="text-xl font-bold text-white">{{ player.current_streak ?? 0 }}</div><div>🔥</div></div>
            </div>
        </div>

        <!-- ── FALLBACK ── -->
        <div v-else class="flex flex-1 flex-col items-center justify-center gap-4 p-6 text-center">
            <p class="text-sm text-gray-500">Waiting for the game to start…</p>
        </div>

    </div>
</template>
