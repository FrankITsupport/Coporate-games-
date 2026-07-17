<script setup>
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue';
import CountdownTimer from './CountdownTimer.vue';
import PredictionPromptPanel from './PredictionPromptPanel.vue';

const props = defineProps({
    initialState:  { type: Object, required: true },
    gameCode:      { type: String, required: true },
    predictUrlTpl: { type: String, required: true },
    csrf:          { type: String, required: true },
});

// ─── Reactive state ───────────────────────────────────────────────────────────

const predictionStatus = ref(props.initialState.prediction_status ?? null);
const locked           = ref(props.initialState.locked ?? false);
const allowEdits       = ref(props.initialState.allow_edits ?? false);
const config           = reactive(props.initialState.config ?? {});
const currentPrompt    = ref(props.initialState.current_prompt ?? null);
const existingAnswers  = reactive(props.initialState.existing_answers ?? {});
const player           = reactive(props.initialState.player ?? {});

const activePrompt     = ref(null);
const submittedPrompts = ref(new Set());
const submitting       = ref(false);
const submitError      = ref(null);
const submitSuccess    = ref(false);
const timerExpired     = ref(false);

// Seed activePrompt if a prompt is already present in the initial state
if (currentPrompt.value) {
    activePrompt.value = currentPrompt.value;
    if (existingAnswers[currentPrompt.value.prompt_id]) {
        submittedPrompts.value.add(currentPrompt.value.prompt_id);
    }
}

// ─── Computed ─────────────────────────────────────────────────────────────────

const isLive = computed(
    () => activePrompt.value !== null && predictionStatus.value === 'prediction_prompt_live'
);

const canSubmit = computed(() => {
    if (!isLive.value || submitting.value || timerExpired.value) return false;
    const pid = activePrompt.value.prompt_id;
    if (submittedPrompts.value.has(pid) && !(allowEdits.value && !locked.value)) return false;
    return true;
});

// ─── Submission ───────────────────────────────────────────────────────────────

async function onSubmit(answer) {
    if (!canSubmit.value || !activePrompt.value) return;

    submitting.value = true;
    submitError.value = null;
    submitSuccess.value = false;

    const url = props.predictUrlTpl.replace('__ID__', activePrompt.value.prompt_id);

    try {
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept':       'application/json',
                'X-CSRF-TOKEN': props.csrf,
            },
            body: JSON.stringify(answer),
        });

        const data = await res.json();

        if (!res.ok) {
            submitError.value = data.error ?? 'Could not submit your prediction. Please try again.';
        } else {
            submittedPrompts.value.add(activePrompt.value.prompt_id);
            submitSuccess.value = true;
            existingAnswers[activePrompt.value.prompt_id] = answer;
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

    const gameId = props.initialState.game_id;
    if (!gameId) {
        console.warn('[PredictionForm] game_id missing from initialState — real-time disabled.');
        return;
    }

    gameChannel = window.Echo.channel(`game.${gameId}`)
        .listen('.PredictionsOpened',      (e) => {
            predictionStatus.value = 'predictions_open';
            activePrompt.value = null;
        })
        .listen('.PredictionPromptStarted', (e) => {
            activePrompt.value = e;
            predictionStatus.value = 'prediction_prompt_live';
            submittedPrompts.value = new Set();
            submitSuccess.value = false;
            submitError.value = null;
            timerExpired.value = false;
            if (existingAnswers[e.prompt_id]) {
                submittedPrompts.value.add(e.prompt_id);
            }
        })
        .listen('.PredictionPromptClosed',  (e) => {
            predictionStatus.value = 'predictions_open';
            activePrompt.value = null;
        })
        .listen('.PredictionsLocked',       (e) => {
            predictionStatus.value = 'predictions_locked';
            locked.value = true;
            activePrompt.value = null;
        })
        .listen('.PredictionWinnersCalculated', (e) => {
            predictionStatus.value = 'prediction_results_ready';
            activePrompt.value = null;
        });

    playerChannel = window.Echo.channel(`player.${player.session_token}`)
        .listen('.PredictionSubmitted', (e) => {
            if (e.submitted) {
                submittedPrompts.value.add(e.prediction_prompt_id);
            }
        });
});

onUnmounted(() => {
    gameChannel?.stopListening('.PredictionsOpened');
    gameChannel?.stopListening('.PredictionPromptStarted');
    gameChannel?.stopListening('.PredictionPromptClosed');
    gameChannel?.stopListening('.PredictionsLocked');
    gameChannel?.stopListening('.PredictionWinnersCalculated');
    playerChannel?.stopListening('.PredictionSubmitted');
});

function onTimerExpired() {
    timerExpired.value = true;
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

function flagEmoji(flag) {
    if (!flag) return '🏳️';
    const code = flag.toUpperCase();
    if (code.length === 2) {
        return String.fromCodePoint(...code.split('').map(c => 0x1F1E6 + c.charCodeAt(0) - 65));
    }
    return flag;
}
</script>

<template>
    <div class="flex min-h-screen flex-col text-white">

        <!-- ── LIVE PROMPT ── -->
        <div v-if="isLive" class="flex flex-1 flex-col gap-4 p-4">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium uppercase tracking-widest text-gray-400">
                    Live Prediction
                </span>
                <span class="rounded-full bg-brand-visa px-3 py-1 text-xs font-bold uppercase tracking-widest">
                    {{ config.team_a_flag ? flagEmoji(config.team_a_flag) : '' }} {{ config.team_a_name ?? 'Team A' }} vs {{ config.team_b_name ?? 'Team B' }} {{ config.team_b_flag ? flagEmoji(config.team_b_flag) : '' }}
                </span>
            </div>

            <CountdownTimer
                :starts-at="activePrompt.started_at"
                :closes-at="activePrompt.closes_at"
                :duration-seconds="activePrompt.duration_seconds"
                @expired="onTimerExpired"
            />

            <PredictionPromptPanel
                :prompt="activePrompt"
                :existing="existingAnswers[activePrompt.prompt_id] ?? null"
                :allow-edits="allowEdits && !locked"
                @submit="onSubmit"
            />

            <p v-if="submitError" class="text-center text-sm text-red-400">{{ submitError }}</p>

            <!-- Score strip at the bottom -->
            <div class="mt-auto flex items-center justify-between text-xs text-gray-500">
                <span>{{ player.nickname }}</span>
                <span v-if="locked">🔒 Locked</span>
            </div>
        </div>

        <!-- ── RESULTS READY ── -->
        <div v-else-if="predictionStatus === 'prediction_results_ready'" class="flex flex-1 flex-col items-center justify-center gap-6 p-6 text-center">
            <div class="text-6xl">🎉</div>
            <h1 class="text-2xl font-bold">Predictions are closed</h1>
            <p class="text-gray-400">Winners are on the stage screen, {{ player.nickname }}!</p>
        </div>

        <!-- ── LOCKED ── -->
        <div v-else-if="predictionStatus === 'predictions_locked'" class="flex flex-1 flex-col items-center justify-center gap-6 p-6 text-center">
            <div class="text-6xl">🔒</div>
            <h1 class="text-2xl font-bold">Predictions are locked</h1>
            <p class="text-gray-400">Thanks for your entries, {{ player.nickname }}!</p>
        </div>

        <!-- ── WAITING / OPEN (no live prompt) ── -->
        <div v-else class="flex flex-1 flex-col items-center justify-center gap-6 p-6 text-center">
            <div class="text-6xl">⏳</div>
            <h1 class="text-xl font-semibold">Hey, {{ player.nickname }}!</h1>
            <p class="text-sm text-gray-400">
                Waiting for the next prediction prompt…
            </p>
            <p class="text-2xl">
                {{ config.team_a_flag ? flagEmoji(config.team_a_flag) : '🏳️' }} {{ config.team_a_name ?? 'Team A' }} vs {{ config.team_b_name ?? 'Team B' }} {{ config.team_b_flag ? flagEmoji(config.team_b_flag) : '🏳️' }}
            </p>

            <!-- Score strip at the bottom -->
            <div class="mt-auto flex items-center justify-between text-xs text-gray-500">
                <span>{{ player.nickname }}</span>
                <span v-if="locked">🔒 Locked</span>
            </div>
        </div>

    </div>
</template>
