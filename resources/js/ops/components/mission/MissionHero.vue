<template>
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <p class="text-xs font-mono font-bold text-gray-400 uppercase tracking-widest leading-none">{{ workOrder.work_order_number }}</p>

        <h1 class="text-xl lg:text-2xl font-bold text-gray-900 mt-1 leading-tight">{{ workOrder.title }}</h1>

        <div class="flex items-center gap-1.5 mt-2 flex-wrap">
            <Badge :tone="statusInfo.tone" :label="statusInfo.label" />
            <Badge :tone="priorityInfo.tone" :label="priorityInfo.label" />
            <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">
                {{ typeLabel[workOrder.work_order_type] ?? workOrder.work_order_type }}
            </span>
        </div>

        <p v-if="expectedOutcome" class="text-sm font-semibold text-indigo-700 mt-3 leading-snug">
            <span class="text-indigo-400 font-medium">Objetivo:</span> {{ expectedOutcome }}
        </p>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mt-4 pt-4 border-t border-gray-50">
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 leading-none mb-1">Equipo</p>
                <RouterLink
                    v-if="workOrder.equipment"
                    :to="{ name: 'ops.equipos.show', params: { id: workOrder.equipment.id } }"
                    class="text-sm font-bold text-gray-900 hover:text-indigo-600 transition-colors truncate block rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2"
                >
                    {{ workOrder.equipment.name }}
                </RouterLink>
                <p v-else class="text-sm text-gray-400">—</p>
            </div>

            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 leading-none mb-1">Responsable</p>
                <p class="text-sm font-bold text-gray-900 truncate">{{ workOrder.assigned_supervisor?.name ?? 'Sin asignar' }}</p>
            </div>

            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 leading-none mb-1">Técnicos</p>
                <p class="text-sm font-bold text-gray-900 truncate">
                    {{ technicianSummary }}
                </p>
            </div>

            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 leading-none mb-1">Tiempo estimado</p>
                <p class="text-sm font-bold text-gray-900">{{ workOrder.planned_labor_hours != null ? `${workOrder.planned_labor_hours} h` : 'Sin estimar' }}</p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { describe, WORK_ORDER_STATUS, PRIORITY } from '../../../shared/design.js'
import Badge from '../Badge.vue'

const props = defineProps({
    workOrder: { type: Object, required: true },
    // mission.expected_outcome — derived server-side (WorkOrderMissionPresenter), not stored.
    expectedOutcome: { type: String, default: null },
})

const typeLabel = {
    corrective: 'Correctivo', preventive: 'Preventivo', predictive: 'Predictivo',
    improvement: 'Mejora', emergency: 'Emergencia',
}

const statusInfo = computed(() => describe(WORK_ORDER_STATUS, props.workOrder.status))
const priorityInfo = computed(() => describe(PRIORITY, props.workOrder.priority))

const technicianSummary = computed(() => {
    const technicians = props.workOrder.technicians ?? []
    if (!technicians.length) return 'Sin asignar'
    const [first, ...rest] = technicians
    const firstName = first.user?.name ?? 'Sin nombre'
    return rest.length ? `${firstName} +${rest.length}` : firstName
})
</script>
