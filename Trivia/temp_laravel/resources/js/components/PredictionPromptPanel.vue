<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    prompt:     { type: Object, required: true },
    existing:   { type: Object, default: null },
    allowEdits: { type: Boolean, default: false },
});

const emit = defineEmits(['submit']);

const answer = ref(null);
const panelSubmitting = ref(false);
const panelError = ref(null);
const panelSuccess = ref(false);

const inputsLocked = computed(() => {
    if (!props.existing && !panelSuccess.value) return false;
    if (props.allowEdits) return false;
    return true;
});

const isAnswered = computed(() => {
    if (!answer.value) return false;
    if (props.prompt.payload.prompt_type === 'flag_choice') return answer.value.outcome !== undefined;
    if (props.prompt.payload.prompt_type === 'score_input') return answer.value.team_a_score !== undefined && answer.value.team_b_score !== undefined;
    if (props.prompt.payload.prompt_type === 'player_select') return answer.value.player_id !== undefined;
    if (props.prompt.payload.prompt_type === 'number_input') return answer.value.minute !== undefined;
    return false;
});

const canPanelSubmit = computed(() => {
    if (inputsLocked.value || panelSubmitting.value) return false;
    return isAnswered.value;
});

if (props.existing) {
    if (props.existing.outcome !== undefined) {
        answer.value = { outcome: props.existing.outcome };
    } else if (props.existing.team_a_score !== undefined && props.existing.team_b_score !== undefined) {
        answer.value = { team_a_score: props.existing.team_a_score, team_b_score: props.existing.team_b_score };
    } else if (props.existing.player_id !== undefined) {
        answer.value = { player_id: props.existing.player_id };
    } else if (props.existing.minute !== undefined) {
        answer.value = { minute: props.existing.minute };
    }
    panelSuccess.value = true;
}

function setFlagChoice(outcome) {
    if (inputsLocked.value) return;
    answer.value = { outcome };
}

function setScoreInput(side, value) {
    if (inputsLocked.value) return;
    if (!answer.value) answer.value = {};
    if (side === 'a') answer.value.team_a_score = value;
    if (side === 'b') answer.value.team_b_score = value;
}

function setPlayerSelect(playerId) {
    if (inputsLocked.value) return;
    answer.value = { player_id: playerId };
}

function setMinuteInput(value) {
    if (inputsLocked.value) return;
    answer.value = { minute: value };
}

async function doSubmit() {
    if (!canPanelSubmit.value) return;

    panelSubmitting.value = true;
    panelError.value = null;

    try {
        emit('submit', answer.value);
        panelSuccess.value = true;
    } catch {
        panelError.value = 'Could not submit. Please try again.';
    } finally {
        panelSubmitting.value = false;
    }
}

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
    <div class="flex flex-1 flex-col gap-4 rounded-2xl bg-white/5 p-5 backdrop-blur">

        <h2 class="text-xl font-bold text-center">{{ prompt.payload.prompt_label }}</h2>

        <!-- ── FLAG CHOICE ── -->
        <div v-if="prompt.payload.prompt_type === 'flag_choice'" class="grid grid-cols-3 gap-3">
            <button
                type="button"
                :disabled="inputsLocked"
                :class="[
                    'flex flex-col items-center justify-center rounded-xl p-4 text-sm font-medium transition',
                    answer?.outcome === 'team_a_win' ? 'bg-brand-green text-white ring-2 ring-brand-lime' : 'bg-white/10 hover:bg-white/20 active:scale-95',
                ]"
                @click="setFlagChoice('team_a_win')"
            >
                <span class="text-3xl">{{ flagEmoji(prompt.payload.team_a_flag) }}</span>
                <span class="mt-1 text-xs font-bold uppercase text-white/60">Win</span>
            </button>
            <button
                type="button"
                :disabled="inputsLocked"
                :class="[
                    'flex flex-col items-center justify-center rounded-xl p-4 text-sm font-medium transition',
                    answer?.outcome === 'draw' ? 'bg-brand-gold text-black ring-2 ring-brand-gold' : 'bg-white/10 hover:bg-white/20 active:scale-95',
                ]"
                @click="setFlagChoice('draw')"
            >
                <span class="text-3xl">🤝</span>
                <span class="mt-1 text-xs font-bold uppercase text-white/60">Draw</span>
            </button>
            <button
                type="button"
                :disabled="inputsLocked"
                :class="[
                    'flex flex-col items-center justify-center rounded-xl p-4 text-sm font-medium transition',
                    answer?.outcome === 'team_b_win' ? 'bg-brand-green text-white ring-2 ring-brand-lime' : 'bg-white/10 hover:bg-white/20 active:scale-95',
                ]"
                @click="setFlagChoice('team_b_win')"
            >
                <span class="text-3xl">{{ flagEmoji(prompt.payload.team_b_flag) }}</span>
                <span class="mt-1 text-xs font-bold uppercase text-white/60">Win</span>
            </button>
        </div>

        <!-- ── SCORE INPUT ── -->
        <div v-else-if="prompt.payload.prompt_type === 'score_input'" class="flex items-center justify-center gap-6">
            <div class="flex flex-col items-center gap-2">
                <span class="text-3xl">{{ flagEmoji(prompt.payload.team_a_flag) }}</span>
                <input
                    type="number"
                    min="0"
                    :disabled="inputsLocked"
                    :value="answer?.team_a_score ?? ''"
                    class="w-20 rounded-xl bg-white/10 px-3 py-2 text-center text-2xl font-bold text-white outline-none focus:ring-2 focus:ring-brand-lime disabled:opacity-50"
                    @input="setScoreInput('a', parseInt($event.target.value) || 0)"
                />
                <span class="text-xs uppercase text-gray-400">{{ prompt.payload.team_a_name ?? 'Team A' }}</span>
            </div>
            <span class="text-2xl font-bold text-gray-400">–</span>
            <div class="flex flex-col items-center gap-2">
                <span class="text-3xl">{{ flagEmoji(prompt.payload.team_b_flag) }}</span>
                <input
                    type="number"
                    min="0"
                    :disabled="inputsLocked"
                    :value="answer?.team_b_score ?? ''"
                    class="w-20 rounded-xl bg-white/10 px-3 py-2 text-center text-2xl font-bold text-white outline-none focus:ring-2 focus:ring-brand-lime disabled:opacity-50"
                    @input="setScoreInput('b', parseInt($event.target.value) || 0)"
                />
                <span class="text-xs uppercase text-gray-400">{{ prompt.payload.team_b_name ?? 'Team B' }}</span>
            </div>
        </div>

        <!-- ── PLAYER SELECT ── -->
        <div v-else-if="prompt.payload.prompt_type === 'player_select'" class="flex max-h-64 flex-col gap-2 overflow-y-auto">
            <button
                v-for="roster in (prompt.payload.roster_options ?? [])"
                :key="roster.id"
                type="button"
                :disabled="inputsLocked"
                :class="[
                    'flex items-center justify-between rounded-xl px-4 py-3 text-left text-sm transition',
                    answer?.player_id === roster.id
                        ? 'bg-brand-green text-white ring-2 ring-brand-lime'
                        : 'bg-white/10 hover:bg-white/20 active:scale-95',
                ]"
                @click="setPlayerSelect(roster.id)"
            >
                <span class="font-semibold">{{ roster.display_name }}</span>
                <span class="text-xs text-white/60">
                    {{ roster.shirt_number ? '#' + roster.shirt_number : '' }} {{ roster.team_name ? '· ' + roster.team_name : '' }}
                </span>
            </button>
        </div>

        <!-- ── NUMBER INPUT ── -->
        <div v-else-if="prompt.payload.prompt_type === 'number_input'" class="flex flex-col items-center gap-3">
            <label class="text-sm text-gray-400">Minute</label>
            <input
                type="number"
                min="0"
                max="200"
                :disabled="inputsLocked"
                :value="answer?.minute ?? ''"
                class="w-32 rounded-xl bg-white/10 px-4 py-3 text-center text-2xl font-bold text-white outline-none focus:ring-2 focus:ring-brand-lime disabled:opacity-50"
                @input="setMinuteInput(parseInt($event.target.value) || 0)"
            />
        </div>

        <!-- Submit button -->
        <button
            v-if="!panelSuccess"
            type="button"
            :disabled="!canPanelSubmit"
            class="mt-auto w-full rounded-xl py-4 text-center text-sm font-bold uppercase tracking-widest transition disabled:cursor-not-allowed disabled:opacity-40"
            :class="canPanelSubmit ? 'bg-brand-visa hover:bg-blue-800 active:scale-95' : 'bg-white/10'"
            @click="doSubmit"
        >
            <span v-if="panelSubmitting">Submitting…</span>
            <span v-else>Lock in prediction</span>
        </button>

        <!-- Already submitted state -->
        <div v-else class="mt-auto rounded-xl bg-green-500/20 p-4 text-center">
            <p class="text-sm font-bold text-green-400">✓ Submitted</p>
            <p v-if="allowEdits" class="mt-1 text-xs text-gray-400">You can change it until locked</p>
        </div>

        <p v-if="panelError" class="text-center text-sm text-red-400">{{ panelError }}</p>
    </div>
</template>
