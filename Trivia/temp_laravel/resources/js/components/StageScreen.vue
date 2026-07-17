<script setup>
/**
 * StageScreen.vue — Phase 10 stage screen for large display/projector.
 *
 * Reacts to Laravel Echo broadcasts on:
 *   - game.{gameId} — public, game state events
 *
 * States handled:
 *   waiting / registration_open / paused / cancelled / ended
 *   question_live  → shows question + answer options
 *   question_closed / answer_reveal  → shows correct answer
 *   leaderboard → shows top-10 leaderboard
 *   podium       → shows final top-three podium
 */
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue';
import CountdownTimer from './CountdownTimer.vue';

const props = defineProps({
    initialState:  { type: Object, required: true },
    gameCode:      { type: String, required: true },
});

// ─── Reactive state ───────────────────────────────────────────────────────────

const gameStatus    = ref(props.initialState.game_status ?? 'waiting');
const activePrompt  = ref(props.initialState.active_prompt ?? null);
const leaderboard   = ref(props.initialState.leaderboard ?? []);
const podiumPlayers = ref(props.initialState.podium ?? []);

// Reveal data (from AnswerRevealed broadcast)
const revealData = ref(null);   // { correct_option_id, accepted_answers }

// ── Prediction state ──────────────────────────────────────────────────────────

const prediction = reactive(props.initialState.prediction ?? {
    status: null,
    current_prompt: null,
    dashboard: null,
    winners: null,
    locked: false,
});

// ─── Computed helpers ─────────────────────────────────────────────────────────

const prompt = computed(() => activePrompt.value?.payload ?? null);

const isMultipleChoice = computed(() =>
    prompt.value?.question_type === 'multiple_choice'
);

// 'up' | 'down' | 'same' | null
function rankMovementFor(p) {
    if (!p.current_rank || !p.previous_rank) return null;
    if (p.current_rank < p.previous_rank) return 'up';
    if (p.current_rank > p.previous_rank) return 'down';
    return 'same';
}

function optionState(optionId) {
    if (!revealData.value) return 'idle';
    if (optionId === revealData.value.correct_option_id) return 'correct';
    return 'idle';
}

// ─── Real-time ───────────────────────────────────────────────────────────────

let gameChannel = null;

onMounted(() => {
    if (!window.Echo) return;

    const gameId = props.initialState.game_id;
    if (!gameId) {
        console.warn('[StageScreen] game_id missing from initialState — real-time disabled.');
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
        .listen('.RegistrationClosed', () => { gameStatus.value = 'waiting'; })
        .listen('.PredictionsOpened',       (e) => { prediction.status = 'predictions_open'; prediction.current_prompt = null; })
        .listen('.PredictionPromptStarted', (e) => {
            prediction.current_prompt = e;
            prediction.status = 'prediction_prompt_live';
            prediction.dashboard = null;
        })
        .listen('.PredictionPromptClosed',  (e) => {
            prediction.status = 'predictions_open';
            prediction.current_prompt = null;
        })
        .listen('.PredictionsLocked',       (e) => {
            prediction.status = 'predictions_locked';
            prediction.locked = true;
            prediction.current_prompt = null;
        })
        .listen('.PredictionDashboardUpdated', (e) => {
            prediction.dashboard = e;
            prediction.status = 'prediction_dashboard';
        })
        .listen('.PredictionWinnersCalculated', (e) => {
            prediction.winners = e.winners;
            prediction.dashboard = e.dashboard;
            prediction.status = 'prediction_results_ready';
        });
});

onUnmounted(() => {
    gameChannel?.stopListening('.QuestionStarted');
});

// ─── Event handlers ───────────────────────────────────────────────────────────

function onQuestionStarted(e) {
    activePrompt.value = e;
    gameStatus.value = 'question_live';
    revealData.value = null;
    podiumPlayers.value = [];
}

function onQuestionClosed(e) {
    gameStatus.value = 'question_closed';
}

function onAnswerRevealed(e) {
    revealData.value = e;
    gameStatus.value = 'answer_reveal';
}

function onLeaderboardUpdated(e) {
    leaderboard.value = e.leaderboard ?? [];
    gameStatus.value = 'leaderboard';
}

function onPodiumShown(e) {
    podiumPlayers.value = e.podium ?? [];
    gameStatus.value = 'podium';
}
</script>

<template>
    <div class="flex min-h-screen flex-col text-white">

        <!-- ── WAITING / REGISTRATION ── -->
        <div v-if="['waiting', 'registration_open'].includes(gameStatus)" class="flex flex-1 flex-col items-center justify-center gap-8 p-12 text-center">
            <div class="text-8xl">⏳</div>
            <h1 class="text-6xl font-bold">Waiting for the game to start</h1>
            <p class="text-2xl text-gray-400">
                <template v-if="gameStatus === 'registration_open'">Registration is open</template>
                <template v-else>Get ready!</template>
            </p>
        </div>

        <!-- ── PAUSED ── -->
        <div v-else-if="gameStatus === 'paused'" class="flex flex-1 flex-col items-center justify-center gap-8 p-12 text-center">
            <div class="text-8xl">⏸</div>
            <h2 class="text-6xl font-bold">Game Paused</h2>
            <p class="text-2xl text-gray-400">We'll be back shortly</p>
        </div>

        <!-- ── CANCELLED ── -->
        <div v-else-if="gameStatus === 'cancelled'" class="flex flex-1 flex-col items-center justify-center gap-8 p-12 text-center">
            <div class="text-8xl">🚫</div>
            <h2 class="text-6xl font-bold">Game Ended</h2>
            <p class="text-2xl text-gray-400">Thanks for playing!</p>
        </div>

        <!-- ── PODIUM ── -->
        <div v-else-if="gameStatus === 'podium'" class="flex flex-1 flex-col items-center justify-center gap-8 p-12 text-center">
            <div class="text-8xl">🏆</div>
            <h2 class="text-6xl font-bold">Final Results</h2>
            <div class="mt-8 flex items-end justify-center gap-8">
                <div v-for="(p, i) in podiumPlayers" :key="p.id" 
                     class="flex flex-col items-center"
                     :class="i === 0 ? 'order-2' : i === 1 ? 'order-1' : 'order-3'">
                    <div class="mb-4 text-6xl">{{ ['🥇','🥈','🥉'][i] }}</div>
                    <div class="rounded-2xl bg-white/10 px-8 py-6 text-center backdrop-blur">
                        <div class="text-3xl font-bold">{{ p.nickname }}</div>
                        <div class="mt-2 text-2xl text-gray-300">{{ p.total_score }} pts</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── LEADERBOARD ── -->
        <div v-else-if="gameStatus === 'leaderboard'" class="flex flex-1 flex-col items-center justify-center p-12">
            <h2 class="mb-8 text-5xl font-bold">Leaderboard</h2>
            <div class="w-full max-w-4xl space-y-4">
                <div
                    v-for="(p, i) in leaderboard"
                    :key="p.id"
                    class="flex items-center gap-6 rounded-2xl bg-white/5 px-8 py-6 backdrop-blur"
                >
                    <span class="flex w-16 items-center gap-1 text-4xl font-bold">
                        {{ p.current_rank }}
                        <span v-if="rankMovementFor(p) === 'up'" class="text-2xl text-green-400">▲</span>
                        <span v-else-if="rankMovementFor(p) === 'down'" class="text-2xl text-red-400">▼</span>
                    </span>
                    <span class="flex-1 text-3xl font-semibold">{{ p.nickname }}</span>
                    <span class="text-3xl font-mono">{{ p.total_score }}</span>
                    <span v-if="p.current_streak > 0" class="text-2xl">🔥 {{ p.current_streak }}</span>
                </div>
            </div>
        </div>

        <!-- ── LIVE QUESTION ── -->
        <div v-else-if="gameStatus === 'question_live' && activePrompt" class="flex flex-1 flex-col gap-8 p-12">

            <!-- Header: round + double points badge -->
            <div class="flex items-center justify-between">
                <span class="text-2xl font-medium uppercase tracking-widest text-gray-400">
                    {{ prompt?.round_name }}
                </span>
                <span v-if="prompt?.is_double_points" class="rounded-full bg-brand-gold px-4 py-2 text-xl font-bold text-black">
                    2× POINTS
                </span>
            </div>

            <!-- Timer -->
            <CountdownTimer
                :starts-at="activePrompt.started_at"
                :closes-at="activePrompt.closes_at"
                :duration-seconds="activePrompt.duration_seconds"
            />

            <!-- Question text -->
            <p class="text-5xl font-bold leading-tight">{{ prompt?.question_text }}</p>

            <!-- ── Multiple choice ── -->
            <div v-if="isMultipleChoice" class="mt-4 grid grid-cols-2 gap-6">
                <div
                    v-for="opt in (prompt?.options ?? [])"
                    :key="opt.id"
                    class="flex flex-col items-center justify-center rounded-3xl p-8 text-2xl font-medium"
                    :class="optionState(opt.id) === 'idle' ? 'bg-white/10' : ''"
                >
                    <span class="mb-2 text-xl font-bold uppercase text-white/60">{{ opt.label }}</span>
                    {{ opt.option_text }}
                </div>
            </div>

            <!-- ── Open text ── -->
            <div v-else class="mt-4 rounded-3xl bg-white/10 p-8 text-center">
                <p class="text-2xl text-gray-400">Type your answer on your phone</p>
            </div>

        </div>

        <!-- ── QUESTION CLOSED ── -->
        <div v-else-if="gameStatus === 'question_closed'" class="flex flex-1 flex-col items-center justify-center gap-8 p-12 text-center">
            <div class="text-8xl">🔒</div>
            <h2 class="text-6xl font-bold">Answers Locked</h2>
            <p class="text-2xl text-gray-400">Revealing the answer shortly…</p>
        </div>

        <!-- ── ANSWER REVEAL ── -->
        <div v-else-if="gameStatus === 'answer_reveal'" class="flex flex-1 flex-col gap-8 p-12">

            <div class="text-center">
                <div class="text-8xl">✅</div>
                <h2 class="text-5xl font-bold">Answer Revealed</h2>
            </div>

            <!-- Multiple choice: highlight the correct option -->
            <div v-if="isMultipleChoice && prompt?.options" class="w-full grid grid-cols-2 gap-6">
                <div
                    v-for="opt in (prompt?.options ?? [])"
                    :key="opt.id"
                    class="flex flex-col items-center justify-center rounded-3xl p-8 text-2xl font-medium"
                    :class="[
                        revealData && opt.id === revealData.correct_option_id ? 'bg-brand-green text-white' : 'bg-white/5 text-gray-400',
                    ]"
                >
                    <span class="mb-2 text-xl font-bold uppercase text-white/50">{{ opt.label }}</span>
                    {{ opt.option_text }}
                </div>
            </div>

            <!-- Open text: show accepted answers -->
            <div v-else-if="!isMultipleChoice && revealData?.accepted_answers?.length" class="text-center">
                <p class="mb-4 text-xl uppercase tracking-widest text-gray-500">Accepted answers</p>
                <p v-for="ans in revealData.accepted_answers" :key="ans" class="text-3xl font-medium text-green-400">{{ ans }}</p>
            </div>

        </div>

        <!-- ── PREDICTION: LIVE PROMPT ── -->
        <div v-else-if="prediction.status === 'prediction_prompt_live' && prediction.current_prompt" class="flex flex-1 flex-col gap-8 p-12">
            <div class="flex items-center justify-between">
                <span class="rounded-full bg-brand-visa px-6 py-3 text-xl font-bold uppercase tracking-widest">
                    🔴 LIVE PREDICTION
                </span>
                <span class="text-2xl font-medium text-gray-300">
                    {{ prediction.current_prompt.payload.prompt_label }}
                </span>
            </div>

            <CountdownTimer
                v-if="prediction.current_prompt.duration_seconds"
                :starts-at="prediction.current_prompt.started_at"
                :closes-at="prediction.current_prompt.closes_at"
                :duration-seconds="prediction.current_prompt.duration_seconds"
            />

            <div class="mt-4 flex flex-col items-center justify-center gap-8">
                <template v-if="prediction.current_prompt.payload.prompt_type === 'flag_choice'">
                    <div class="flex items-center gap-8 text-4xl font-bold">
                        <span class="flex items-center gap-3">
                            <span class="text-5xl">{{ prediction.current_prompt.payload.team_a_flag || '🏳️' }}</span>
                            <span>Team A</span>
                        </span>
                        <span class="text-gray-400 text-2xl">VS</span>
                        <span class="flex items-center gap-3">
                            <span class="text-5xl">{{ prediction.current_prompt.payload.team_b_flag || '🏳️' }}</span>
                            <span>Team B</span>
                        </span>
                    </div>
                    <p class="text-3xl text-gray-300">Predict the winner on your phone</p>
                </template>

                <template v-else-if="prediction.current_prompt.payload.prompt_type === 'score_input'">
                    <p class="text-4xl font-bold">
                        {{ prediction.current_prompt.payload.team_a_flag || '🏳️' }} {{ prediction.current_prompt.payload.team_a_name ?? 'Team A' }} – {{ prediction.current_prompt.payload.team_b_name ?? 'Team B' }} {{ prediction.current_prompt.payload.team_b_flag || '🏳️' }}
                    </p>
                    <p class="text-2xl text-gray-400">Enter the final score on your phone</p>
                </template>

                <template v-else-if="prediction.current_prompt.payload.prompt_type === 'player_select'">
                    <p class="text-3xl font-bold">{{ prediction.current_prompt.payload.prompt_label }}</p>
                    <div class="flex max-h-96 flex-wrap justify-center gap-3 overflow-y-auto">
                        <span
                            v-for="roster in (prediction.current_prompt.payload.roster_options ?? [])"
                            :key="roster.id"
                            class="rounded-2xl bg-white/10 px-6 py-4 text-xl font-semibold"
                        >
                            {{ roster.display_name }} {{ roster.shirt_number ? '#' + roster.shirt_number : '' }} {{ roster.team_name ? '· ' + roster.team_name : '' }}
                        </span>
                    </div>
                </template>

                <template v-else-if="prediction.current_prompt.payload.prompt_type === 'number_input'">
                    <p class="text-3xl font-bold">{{ prediction.current_prompt.payload.prompt_label }}</p>
                    <p class="text-2xl text-gray-400">Enter your guess on your phone</p>
                </template>
            </div>
        </div>

        <!-- ── PREDICTION: DASHBOARD / LOCKED ── -->
        <div v-else-if="prediction.status === 'prediction_dashboard' || prediction.status === 'predictions_locked'" class="flex flex-1 flex-col gap-8 p-12">
            <h2 class="text-5xl font-bold">Prediction Dashboard</h2>

            <div v-if="prediction.dashboard" class="grid grid-cols-2 gap-8">
                <!-- Submission count -->
                <div class="col-span-2 rounded-2xl bg-white/5 p-6 text-center">
                    <p class="text-xl uppercase tracking-widest text-gray-400">Submissions</p>
                    <p class="mt-2 text-5xl font-bold">{{ prediction.dashboard.submission_count ?? 0 }}</p>
                </div>

                <!-- Outcomes -->
                <div v-if="prediction.dashboard.outcomes" class="col-span-2 rounded-2xl bg-white/5 p-6">
                    <p class="mb-4 text-xl uppercase tracking-widest text-gray-400">Predicted Outcome</p>
                    <div class="flex flex-col gap-3">
                        <div v-for="(count, key) in prediction.dashboard.outcomes" :key="key" class="flex items-center justify-between rounded-xl bg-white/5 px-6 py-4 text-xl">
                            <span>
                                <span v-if="key === 'team_a_win'">{{ prediction.current_prompt?.payload.team_a_flag || '🏳️' }} Team A Win</span>
                                <span v-else-if="key === 'team_b_win'">{{ prediction.current_prompt?.payload.team_b_flag || '🏳️' }} Team B Win</span>
                                <span v-else-if="key === 'draw'">🤝 Draw</span>
                                <span v-else>{{ key }}</span>
                            </span>
                            <span class="font-mono font-bold">{{ count }}</span>
                        </div>
                    </div>
                </div>

                <!-- Scorelines -->
                <div v-if="prediction.dashboard.scorelines" class="col-span-2 rounded-2xl bg-white/5 p-6">
                    <p class="mb-4 text-xl uppercase tracking-widest text-gray-400">Predicted Scorelines</p>
                    <div class="grid grid-cols-4 gap-3">
                        <div v-for="(count, line) in prediction.dashboard.scorelines" :key="line" class="rounded-xl bg-white/5 px-4 py-3 text-center">
                            <p class="text-lg font-mono font-bold">{{ line }}</p>
                            <p class="text-sm text-gray-400">{{ count }}</p>
                        </div>
                    </div>
                </div>

                <!-- First goalscorer -->
                <div v-if="prediction.dashboard.first_goalscorer" class="col-span-2 rounded-2xl bg-white/5 p-6">
                    <p class="mb-4 text-xl uppercase tracking-widest text-gray-400">First Goalscorer</p>
                    <div class="flex flex-col gap-3">
                        <div v-for="(count, name) in prediction.dashboard.first_goalscorer" :key="name" class="flex items-center justify-between rounded-xl bg-white/5 px-6 py-4 text-xl">
                            <span>{{ name }}</span>
                            <span class="font-mono font-bold">{{ count }}</span>
                        </div>
                    </div>
                </div>

                <!-- Player of match -->
                <div v-if="prediction.dashboard.player_of_match" class="col-span-2 rounded-2xl bg-white/5 p-6">
                    <p class="mb-4 text-xl uppercase tracking-widest text-gray-400">Player of the Match</p>
                    <div class="flex flex-col gap-3">
                        <div v-for="(count, name) in prediction.dashboard.player_of_match" :key="name" class="flex items-center justify-between rounded-xl bg-white/5 px-6 py-4 text-xl">
                            <span>{{ name }}</span>
                            <span class="font-mono font-bold">{{ count }}</span>
                        </div>
                    </div>
                </div>

                <!-- First goal minute -->
                <div v-if="prediction.dashboard.first_goal_minute" class="col-span-2 rounded-2xl bg-white/5 p-6">
                    <p class="mb-4 text-xl uppercase tracking-widest text-gray-400">First Goal Minute</p>
                    <div class="grid grid-cols-6 gap-3">
                        <div v-for="(count, minute) in prediction.dashboard.first_goal_minute" :key="minute" class="rounded-xl bg-white/5 px-4 py-3 text-center">
                            <p class="text-lg font-mono font-bold">{{ minute }}'</p>
                            <p class="text-sm text-gray-400">{{ count }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <p v-else class="text-center text-2xl text-gray-400">Waiting for dashboard data…</p>
        </div>

        <!-- ── PREDICTION: WINNERS ── -->
        <div v-else-if="prediction.status === 'prediction_results_ready'" class="flex flex-1 flex-col gap-8 p-12">
            <h2 class="text-5xl font-bold">🎉 Prediction Winners</h2>

            <div v-if="prediction.winners && prediction.winners.length" class="flex flex-col gap-8">
                <!-- Podium of top 3 -->
                <div class="flex items-end justify-center gap-8">
                    <div v-for="(w, i) in prediction.winners.slice(0, 3)" :key="w.player_id"
                         class="flex flex-col items-center"
                         :class="i === 0 ? 'order-2' : i === 1 ? 'order-1' : 'order-3'">
                        <div class="mb-4 text-6xl">{{ ['🥇','🥈','🥉'][i] }}</div>
                        <div class="rounded-2xl bg-white/10 px-8 py-6 text-center backdrop-blur">
                            <div class="text-2xl font-bold">{{ w.nickname }}</div>
                            <div class="mt-2 text-xl text-gray-300">{{ w.points }} pts</div>
                        </div>
                    </div>
                </div>

                <!-- Full list -->
                <div class="w-full max-w-4xl space-y-4">
                    <div v-for="(w, i) in prediction.winners" :key="w.player_id" class="flex items-center gap-6 rounded-2xl bg-white/5 px-8 py-6 backdrop-blur">
                        <span class="text-3xl font-bold w-12">{{ i + 1 }}</span>
                        <span class="flex-1 text-2xl font-semibold">{{ w.nickname }}</span>
                        <span class="text-2xl font-mono">{{ w.points }} pts</span>
                    </div>
                </div>
            </div>

            <div v-else class="text-center text-2xl text-gray-400">No winners calculated yet.</div>
        </div>
        <div v-else class="flex flex-1 flex-col items-center justify-center gap-8 p-12 text-center">
            <p class="text-2xl text-gray-500">Waiting for the game to start…</p>
        </div>

    </div>
</template>
