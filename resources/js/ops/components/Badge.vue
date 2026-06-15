<template>
    <span :class="['inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold', classes]">
        <slot>{{ label }}</slot>
    </span>
</template>

<script setup>
import { computed } from 'vue'
import { toneClasses } from '../../shared/design.js'

const props = defineProps({
    tone: { type: String, default: 'neutral' },
    label: { type: String, default: '' },
    theme: { type: String, default: 'light' },
})

const classes = computed(() => toneClasses(props.tone, props.theme))
</script>
