<template>
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <div class="flex items-center justify-between gap-3 mb-4">
            <h2 class="text-xs font-bold uppercase tracking-widest text-gray-500">Progreso de la misión</h2>
            <div class="flex items-center gap-2 shrink-0">
                <Badge v-if="offSpineBadge" :tone="offSpineBadge.tone" :label="offSpineBadge.label" />
                <span class="text-lg font-bold text-gray-900 leading-none">{{ progress.percentage }}%</span>
            </div>
        </div>

        <!-- Progress bar -->
        <div class="h-1.5 rounded-full bg-gray-100 overflow-hidden mb-5">
            <div
                class="h-full rounded-full bg-emerald-500 transition-all"
                :style="{ width: `${progress.percentage}%` }"
            />
        </div>

        <!-- Stage tracker -->
        <div class="flex items-start">
            <template v-for="(stage, index) in progress.stages" :key="stage.status">
                <div class="flex flex-col items-center flex-1 min-w-0">
                    <div
                        class="w-3.5 h-3.5 rounded-full shrink-0 ring-4 ring-white"
                        :class="dotClasses(stage)"
                        :title="stage.at ? `${stage.label} — ${formatDateTime(stage.at)}` : stage.label"
                    />
                    <p
                        class="text-[11px] font-medium text-center mt-2 leading-tight px-0.5"
                        :class="stage.current ? 'text-emerald-700 font-bold' : stage.done ? 'text-gray-700' : 'text-gray-400'"
                    >
                        {{ stage.label }}
                    </p>
                    <p v-if="stage.at" class="text-[10px] text-gray-400 mt-0.5">{{ formatDateTime(stage.at, true) }}</p>
                </div>
                <div
                    v-if="index < progress.stages.length - 1"
                    class="h-px flex-1 mt-[7px] shrink-0"
                    :class="stage.done ? 'bg-emerald-300' : 'bg-gray-200'"
                    style="min-width: 8px;"
                />
            </template>
        </div>

        <p v-if="nextStageLabel" class="text-xs text-gray-500 mt-4 pt-4 border-t border-gray-50">
            <span class="font-semibold text-gray-700">Sigue:</span> {{ nextStageLabel }}
        </p>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import Badge from '../Badge.vue'

const props = defineProps({
    // mission.progress — { stages: [{status,label,done,current,at}], percentage, current_status, off_spine }
    progress: { type: Object, required: true },
})

const offSpineBadge = computed(() => {
    if (!props.progress.off_spine) return null
    const isCancelled = props.progress.off_spine.status === 'cancelled'
    return { label: props.progress.off_spine.label, tone: isCancelled ? 'danger' : 'warning' }
})

const nextStageLabel = computed(() => {
    const stages = props.progress.stages ?? []
    const currentIndex = stages.findIndex((s) => s.current)
    if (currentIndex === -1 || currentIndex === stages.length - 1) return null
    return stages[currentIndex + 1].label
})

function dotClasses(stage) {
    if (stage.current) return 'bg-emerald-500 ring-emerald-100'
    if (stage.done) return 'bg-emerald-400'
    return 'bg-gray-200'
}

function formatDateTime(iso, short = false) {
    if (!iso) return null
    return new Date(iso).toLocaleDateString('es', short
        ? { day: '2-digit', month: 'short' }
        : { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}
</script>
