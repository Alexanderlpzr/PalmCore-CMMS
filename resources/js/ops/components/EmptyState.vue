<template>
    <div class="flex flex-col items-center justify-center py-16 px-5 text-center">
        <div class="w-14 h-14 rounded-2xl bg-gray-100 flex items-center justify-center mb-4">
            <AppIcon :name="icon" :stroke-width="1.5" class="w-7 h-7 text-gray-400" />
        </div>
        <p class="text-sm font-medium text-gray-700">{{ title }}</p>
        <p v-if="subtitle" class="text-xs text-gray-500 mt-1 max-w-xs">{{ subtitle }}</p>
        <div v-if="$slots.action" class="mt-5">
            <slot name="action" />
        </div>
    </div>
</template>

<script setup>
import AppIcon from './AppIcon.vue'

defineProps({
    icon: { type: String, default: 'checkCircle' },
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
})
</script>
