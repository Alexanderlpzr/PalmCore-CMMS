<template>
    <div class="space-y-1.5">
        <div
            v-for="(item, index) in items"
            :key="index"
            class="flex items-center gap-3 h-9"
        >
            <!-- Label -->
            <span class="w-28 shrink-0 text-xs text-gray-600 text-right truncate">{{ item.label }}</span>

            <!-- Bar track -->
            <div class="flex-1 relative h-4 bg-gray-100 rounded-full overflow-hidden">
                <div
                    class="absolute inset-y-0 left-0 rounded-full transition-all duration-700 ease-out"
                    :class="item.color"
                    :style="{ width: animating ? barWidth(item.value) : '0%', minWidth: item.value === 0 ? '2px' : undefined }"
                />
            </div>

            <!-- Value -->
            <span class="w-16 shrink-0 text-xs font-semibold text-gray-800 tabular-nums">{{ formatValue(item.value) }}</span>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'

const props = defineProps({
    items: {
        type: Array,
        default: () => [],
        // [{ label: string, value: number, color: string }]
        // color is a Tailwind bg color class e.g. 'bg-emerald-500'
    },
})

const animating = ref(false)

const maxValue = computed(() => {
    if (!props.items.length) { return 1 }
    return Math.max(...props.items.map(i => i.value), 1)
})

function barWidth(value) {
    if (value === 0) { return '0%' }
    return `${(value / maxValue.value) * 100}%`
}

function formatValue(value) {
    if (value == null) { return '—' }
    return Number.isInteger(value) ? value.toLocaleString('es') : value.toLocaleString('es', { minimumFractionDigits: 1, maximumFractionDigits: 1 })
}

onMounted(() => {
    // Short delay so the CSS transition fires after paint
    setTimeout(() => {
        animating.value = true
    }, 50)
})
</script>
