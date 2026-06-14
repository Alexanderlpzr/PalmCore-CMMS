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
    active:            { label: 'Activo',           classes: 'bg-green-500/15 text-green-400' },
    inactive:          { label: 'Inactivo',          classes: 'bg-zinc-600/30 text-zinc-500' },
    under_maintenance: { label: 'En mantenimiento',  classes: 'bg-amber-500/15 text-amber-400' },
    retired:           { label: 'Retirado',          classes: 'bg-red-500/15 text-red-400' },
    standby:           { label: 'En espera',         classes: 'bg-blue-500/15 text-blue-400' },
}

const colorClass = computed(() => config[props.status]?.classes ?? 'bg-zinc-700/40 text-zinc-400')
const label = computed(() => config[props.status]?.label ?? props.status)
</script>
