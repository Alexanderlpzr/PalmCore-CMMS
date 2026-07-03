<template>
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm divide-y divide-gray-50">
        <div class="px-5 py-3">
            <h2 class="text-xs font-bold uppercase tracking-widest text-gray-500">Contexto de la misión</h2>
        </div>

        <!-- Equipo -->
        <div v-if="workOrder.equipment" class="px-5 py-4 flex items-center justify-between gap-4">
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-0.5">Equipo</p>
                <p class="text-sm font-bold text-gray-900 truncate">{{ workOrder.equipment.name }}</p>
                <div class="flex items-center gap-2 mt-0.5 flex-wrap">
                    <span class="text-xs font-mono text-gray-400">{{ workOrder.equipment.code }}</span>
                    <Badge v-if="criticalityInfo" :tone="criticalityInfo.tone" :label="criticalityInfo.label" />
                </div>
            </div>
            <RouterLink
                :to="{ name: 'ops.equipos.show', params: { id: workOrder.equipment.id } }"
                class="shrink-0 flex items-center gap-1 text-xs font-semibold text-indigo-600 hover:text-indigo-800 transition-colors rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2"
            >
                Ver equipo
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
            </RouterLink>
        </div>

        <!-- Origen -->
        <div v-if="origin" class="px-5 py-4">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-1">{{ origin.label }}</p>
            <p class="text-sm text-gray-700 leading-relaxed">{{ origin.description }}</p>
        </div>

        <!-- Motivo (descripción) -->
        <div v-if="workOrder.description" class="px-5 py-4">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-1">Motivo</p>
            <p class="text-sm text-gray-700 whitespace-pre-line leading-relaxed">{{ workOrder.description }}</p>
        </div>

        <!-- Instrucciones críticas -->
        <div v-if="workOrder.instructions" class="px-5 py-4 bg-amber-50/60">
            <p class="text-xs font-semibold uppercase tracking-wider text-amber-700 mb-1">Instrucciones críticas</p>
            <p class="text-sm text-amber-900 whitespace-pre-line leading-relaxed">{{ workOrder.instructions }}</p>
        </div>

        <!-- Última intervención relacionada -->
        <div v-if="previousIntervention" class="px-5 py-4">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-1">Última intervención en este equipo</p>
            <RouterLink
                :to="{ name: 'ops.ordenes.show', params: { id: previousIntervention.id } }"
                class="text-sm font-semibold text-gray-800 hover:text-indigo-600 transition-colors rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2"
            >
                {{ previousIntervention.title }}
            </RouterLink>
            <p class="text-xs text-gray-400 mt-0.5">
                {{ previousIntervention.type }} · {{ formatRelative(previousIntervention.closed_at ?? previousIntervention.created_at) }}
            </p>
        </div>

        <p v-if="!hasAnyContext" class="px-5 py-6 text-center text-xs text-gray-500">
            Sin contexto adicional registrado para esta orden.
        </p>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { describe, CRITICALITY } from '../../../shared/design.js'
import Badge from '../Badge.vue'

const props = defineProps({
    workOrder: { type: Object, required: true },
    // mission.origin — { type, label, description } | null
    origin: { type: Object, default: null },
    // mission.previous_intervention — { id, work_order_number, title, type, closed_at, created_at } | null
    previousIntervention: { type: Object, default: null },
})

const criticalityInfo = computed(() => {
    const criticality = props.workOrder.equipment?.criticality
    return criticality ? describe(CRITICALITY, criticality) : null
})

const hasAnyContext = computed(() => Boolean(
    props.workOrder.equipment || props.origin || props.workOrder.description
    || props.workOrder.instructions || props.previousIntervention,
))

function formatRelative(iso) {
    if (!iso) return 'fecha desconocida'
    const days = Math.round((Date.now() - new Date(iso).getTime()) / 86400000)
    if (days <= 0) return 'hoy'
    if (days === 1) return 'hace 1 día'
    if (days < 30) return `hace ${days} días`
    const months = Math.round(days / 30)
    return months === 1 ? 'hace 1 mes' : `hace ${months} meses`
}
</script>
