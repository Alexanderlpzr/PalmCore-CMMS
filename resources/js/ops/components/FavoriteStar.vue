<template>
    <button
        @click.stop.prevent="fav.toggle(id)"
        :title="active ? 'Quitar de favoritos' : 'Agregar a favoritos'"
        :aria-pressed="active"
        class="shrink-0 p-1 rounded-lg transition-colors"
        :class="active ? 'text-amber-500 hover:text-amber-600' : 'text-gray-300 hover:text-amber-400'"
    >
        <svg :class="sizeClass" :fill="active ? 'currentColor' : 'none'" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6">
            <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.5c.2-.48.84-.48 1.04 0l2.08 5.01 5.4.43c.52.04.73.69.34 1.03l-4.12 3.53 1.26 5.27c.12.5-.43.91-.87.63L12 16.69l-4.62 2.71c-.44.27-.99-.13-.87-.63l1.26-5.27-4.12-3.53c-.39-.34-.18-.99.34-1.03l5.4-.43L11.48 3.5z"/>
        </svg>
    </button>
</template>

<script setup>
import { computed } from 'vue'
import { useFavorites } from '../composables/useFavorites.js'

const props = defineProps({
    type: { type: String, required: true },
    id: { type: String, required: true },
    size: { type: String, default: 'w-5 h-5' },
})

const fav = useFavorites(props.type)
const active = computed(() => fav.isFavorite(props.id))
const sizeClass = computed(() => props.size)
</script>
