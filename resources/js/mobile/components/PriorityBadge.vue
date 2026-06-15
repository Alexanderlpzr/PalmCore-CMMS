<template>
    <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium', desc.classes]">
        {{ desc.label }}
    </span>
</template>

<script setup>
import { computed } from 'vue'
import { describe, PRIORITY } from '../../shared/design.js'

const props = defineProps({
    priority: { type: String, default: '' },
})

const desc = computed(() => describe(PRIORITY, props.priority, 'dark'))
</script>
