<template>
    <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium', colorClass]">
        {{ label }}
    </span>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    status: { type: String, default: '' },
})

const config = {
    draft:       { label: 'Borrador',     classes: 'bg-zinc-700/40 text-zinc-400' },
    planned:     { label: 'Planificada',  classes: 'bg-blue-500/15 text-blue-400' },
    in_progress: { label: 'En proceso',   classes: 'bg-amber-500/15 text-amber-400' },
    completed:   { label: 'Completada',   classes: 'bg-green-500/15 text-green-400' },
    verified:    { label: 'Verificada',   classes: 'bg-teal-500/15 text-teal-400' },
    closed:      { label: 'Cerrada',      classes: 'bg-zinc-600/30 text-zinc-500' },
    cancelled:   { label: 'Cancelada',    classes: 'bg-red-500/15 text-red-400' },
}

const colorClass = computed(() => config[props.status]?.classes ?? 'bg-zinc-700/40 text-zinc-400')
const label = computed(() => config[props.status]?.label ?? props.status)
</script>
