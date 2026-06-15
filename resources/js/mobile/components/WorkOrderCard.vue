<template>
    <div class="bg-zinc-900 border border-zinc-800 rounded-2xl p-4 cursor-pointer hover:border-zinc-700 transition active:scale-[0.98]">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
                <p class="text-xs text-zinc-400 font-mono mb-1">{{ workOrder.work_order_number }}</p>
                <p class="font-semibold text-zinc-100 leading-snug line-clamp-2">{{ workOrder.title }}</p>
                <p v-if="workOrder.equipment" class="text-sm text-zinc-400 mt-1.5 truncate">
                    {{ workOrder.equipment.name }}
                    <span class="text-zinc-700"> · {{ workOrder.equipment.code }}</span>
                </p>
            </div>
            <svg class="w-4 h-4 text-zinc-700 shrink-0 mt-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
            </svg>
        </div>
        <div class="flex gap-2 mt-3 flex-wrap">
            <StatusBadge :status="workOrder.status" />
            <PriorityBadge :priority="workOrder.priority" />
        </div>
    </div>
</template>

<script setup>
import StatusBadge from './StatusBadge.vue'
import PriorityBadge from './PriorityBadge.vue'

defineProps({
    workOrder: { type: Object, required: true },
})
</script>
