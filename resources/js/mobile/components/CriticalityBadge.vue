<template>
    <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium', colorClass]">
        {{ label }}
    </span>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    criticality: { type: String, default: '' },
})

const config = {
    critical: { label: 'Crítico',  classes: 'bg-red-500/15 text-red-400' },
    high:     { label: 'Alto',     classes: 'bg-orange-500/15 text-orange-400' },
    medium:   { label: 'Medio',    classes: 'bg-yellow-500/15 text-yellow-400' },
    low:      { label: 'Bajo',     classes: 'bg-sky-500/15 text-sky-400' },
}

const colorClass = computed(() => config[props.criticality]?.classes ?? 'bg-zinc-700/40 text-zinc-400')
const label = computed(() => config[props.criticality]?.label ?? props.criticality)
</script>
