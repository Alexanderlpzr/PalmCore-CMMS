<template>
    <div class="flex flex-col items-center justify-center h-full min-h-96 p-8 text-center">
        <div class="w-16 h-16 rounded-2xl bg-emerald-50 flex items-center justify-center mb-5">
            <svg class="w-8 h-8 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
        <h2 class="text-lg font-bold text-gray-800">{{ title }}</h2>
        <p class="text-sm text-gray-500 mt-2 max-w-xs">
            No hay información disponible para mostrar en esta sección.
        </p>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()
const title = computed(() => route.meta?.title ?? 'Sección')
</script>
