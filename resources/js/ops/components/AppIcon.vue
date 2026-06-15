<template>
    <svg
        fill="none"
        viewBox="0 0 24 24"
        stroke="currentColor"
        :stroke-width="strokeWidth"
        stroke-linecap="round"
        stroke-linejoin="round"
        v-html="path"
    />
</template>

<script setup>
import { computed } from 'vue'
import { icons } from '../../shared/icons.js'

const props = defineProps({
    name: { type: String, required: true },
    strokeWidth: { type: [Number, String], default: 2 },
})

const path = computed(() => icons[props.name] ?? '')
</script>
