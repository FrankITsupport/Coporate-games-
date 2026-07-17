<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    startsAt: { type: String, required: true },  // ISO 8601
    closesAt: { type: String, required: true },  // ISO 8601
    durationSeconds: { type: Number, required: true },
});

const emit = defineEmits(['expired']);

const remaining = ref(0);
let timer = null;

function tick() {
    const now = Date.now();
    const end = new Date(props.closesAt).getTime();
    remaining.value = Math.max(0, Math.ceil((end - now) / 1000));

    if (remaining.value === 0) {
        clearInterval(timer);
        emit('expired');
    }
}

onMounted(() => {
    tick();
    timer = setInterval(tick, 250);
});

onUnmounted(() => clearInterval(timer));

const pct = computed(() =>
    props.durationSeconds > 0
        ? (remaining.value / props.durationSeconds) * 100
        : 0
);

const barColor = computed(() => {
    if (pct.value > 50) return 'bg-brand-lime';
    if (pct.value > 25) return 'bg-brand-gold';
    return 'bg-red-400';
});
</script>

<template>
    <div class="w-full">
        <!-- Numeric countdown -->
        <div class="text-center text-3xl font-bold tabular-nums" :class="remaining <= 5 ? 'text-red-400' : 'text-white'">
            {{ remaining }}
        </div>
        <!-- Progress bar -->
        <div class="mt-1 h-2 w-full overflow-hidden rounded-full bg-white/10">
            <div
                class="h-full rounded-full transition-all duration-300"
                :class="barColor"
                :style="{ width: pct + '%' }"
            ></div>
        </div>
    </div>
</template>
