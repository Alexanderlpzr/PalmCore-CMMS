<template>
    <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium', colorClass]">
        {{ label }}
    </span>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    priority: { type: String, default: '' },
})

const config = {
    p1_critical: { label: 'Crítica',  classes: 'bg-red-500/15 text-red-400' },
    p2_high:     { label: 'Alta',     classes: 'bg-orange-500/15 text-orange-400' },
    p3_medium:   { label: 'Media',    classes: 'bg-yellow-500/15 text-yellow-400' },
    p4_low:      { label: 'Baja',     classes: 'bg-sky-500/15 text-sky-400' },
}

const colorClass = computed(() => config[props.priority]?.classes ?? 'bg-zinc-700/40 text-zinc-400')
const label = computed(() => config[props.priority]?.label ?? props.priority)
</script>
