<template>
    <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium', desc.classes]">
        {{ desc.label }}
    </span>
</template>

<script setup>
import { computed } from 'vue'
import { describe, WORK_ORDER_STATUS } from '../../shared/design.js'

const props = defineProps({
    status: { type: String, default: '' },
})

const desc = computed(() => describe(WORK_ORDER_STATUS, props.status, 'dark'))
</script>
