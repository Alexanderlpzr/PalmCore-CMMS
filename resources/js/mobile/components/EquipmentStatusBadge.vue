<template>
    <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium', desc.classes]">
        {{ desc.label }}
    </span>
</template>

<script setup>
import { computed } from 'vue'
import { describe, EQUIPMENT_STATUS } from '../../shared/design.js'

const props = defineProps({
    status: { type: String, default: '' },
})

const desc = computed(() => describe(EQUIPMENT_STATUS, props.status, 'dark'))
</script>
