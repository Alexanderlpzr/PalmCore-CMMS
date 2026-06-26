<template>
    <div class="min-h-full bg-gray-50">

        <!-- Loading skeleton -->
        <div v-if="loading" class="bg-white border-b border-gray-100 px-4 lg:px-8 py-5">
            <div class="max-w-4xl mx-auto">
                <div class="skeleton h-3 w-32 rounded mb-4" />
                <div class="skeleton h-7 w-1/2 rounded mb-2" />
                <div class="skeleton h-3 w-1/4 rounded" />
                <div class="grid grid-cols-3 gap-3 mt-6">
                    <div v-for="i in 3" :key="i" class="skeleton h-16 rounded-xl" />
                </div>
            </div>
        </div>

        <template v-else-if="area">

            <!-- ── Sticky header ──────────────────────────────────────────────── -->
            <div class="bg-white border-b border-gray-100 sticky top-0 z-20 shadow-sm">
                <div class="max-w-4xl mx-auto px-4 lg:px-8 pt-3 pb-0">

                    <!-- Breadcrumb + back -->
                    <div class="flex items-center gap-1.5 text-xs mb-3 flex-wrap">
                        <button @click="goBack" class="flex items-center gap-1 text-gray-500 hover:text-gray-700 transition-colors shrink-0">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
                            {{ backLabel }}
                        </button>
                        <template v-if="area.plant">
                            <svg class="w-3 h-3 text-gray-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                            <RouterLink
                                :to="{ name: 'ops.plantes.show', params: { id: area.plant.id }, query: { from: route.query.from, fromId: route.query.fromId } }"
                                class="text-indigo-400 hover:text-indigo-700 transition-colors shrink-0">
                                {{ area.plant.name }}
                            </RouterLink>
                        </template>
                        <svg class="w-3 h-3 text-gray-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                        <span class="text-gray-700 font-medium truncate">{{ area.name }}</span>
                    </div>

                    <!-- Identity row -->
                    <div class="flex items-start gap-3 mb-4">
                        <div class="w-10 h-10 rounded-xl bg-indigo-100 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-mono font-bold text-gray-500 uppercase tracking-widest leading-none">{{ area.code }}</p>
                            <h1 class="text-xl font-bold text-gray-900 mt-0.5 leading-tight">{{ area.name }}</h1>
                            <p v-if="area.plant" class="text-xs text-gray-500 mt-0.5">{{ area.plant.name }}</p>
                        </div>
                        <span v-if="!area.is_active" class="shrink-0 text-xs font-semibold px-2 py-0.5 rounded-full bg-gray-100 text-gray-500">Inactiva</span>
                    </div>

                    <!-- KPI strip -->
                    <div class="grid grid-cols-3 gap-2 mb-4">
                        <div class="rounded-xl p-2.5 bg-emerald-50">
                            <p class="text-xs font-bold uppercase tracking-wider text-emerald-600 leading-none mb-1">Disponibilidad</p>
                            <p class="text-lg font-bold text-gray-900 leading-none">
                                {{ area.kpi?.avg_availability != null ? area.kpi.avg_availability.toFixed(1) + '%' : '—' }}
                            </p>
                        </div>
                        <div class="rounded-xl p-2.5 bg-blue-50">
                            <p class="text-xs font-bold uppercase tracking-wider text-blue-600 leading-none mb-1">Equipos</p>
                            <p class="text-lg font-bold text-gray-900 leading-none">{{ area.equipment_count }}</p>
                        </div>
                        <div class="rounded-xl p-2.5 bg-red-50">
                            <p class="text-xs font-bold uppercase tracking-wider text-red-600 leading-none mb-1">Fallas</p>
                            <p class="text-lg font-bold text-gray-900 leading-none">{{ area.kpi?.total_failures ?? '—' }}</p>
                        </div>
                    </div>

                    <!-- Tab bar -->
                    <div class="flex gap-0 overflow-x-auto border-t border-gray-100">
                        <button v-for="tab in tabs" :key="tab.id" @click="activeTab = tab.id"
                            class="shrink-0 px-4 py-3 text-sm font-medium transition-colors border-b-2 -mb-px"
                            :class="activeTab === tab.id
                                ? 'border-emerald-500 text-emerald-700 font-semibold'
                                : 'border-transparent text-gray-500 hover:text-gray-800 hover:border-gray-300'">
                            {{ tab.label }}
                            <span v-if="tab.count" class="ml-1 text-xs font-bold bg-gray-100 text-gray-500 rounded-full px-1.5 py-0.5">{{ tab.count }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- ── Content ──────────────────────────────────────────────────────── -->
            <div class="max-w-4xl mx-auto px-4 lg:px-8 py-6 space-y-6">

                <!-- Información -->
                <div v-if="activeTab === 'info'">
                    <h2 class="text-xs font-bold uppercase tracking-widest text-gray-500 mb-3">Información</h2>
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
                        <div class="px-4 divide-y divide-gray-50">
                            <div class="flex items-start justify-between py-2.5 gap-4">
                                <span class="text-xs text-gray-500 shrink-0">Código</span>
                                <span class="text-xs font-semibold text-gray-800 text-right font-mono">{{ area.code }}</span>
                            </div>
                            <div class="flex items-start justify-between py-2.5 gap-4">
                                <span class="text-xs text-gray-500 shrink-0">Nombre</span>
                                <span class="text-xs font-semibold text-gray-800 text-right">{{ area.name }}</span>
                            </div>
                            <div v-if="area.description" class="flex items-start justify-between py-2.5 gap-4">
                                <span class="text-xs text-gray-500 shrink-0">Descripción</span>
                                <span class="text-xs font-semibold text-gray-800 text-right">{{ area.description }}</span>
                            </div>
                            <div v-if="area.plant" class="flex items-start justify-between py-2.5 gap-4">
                                <span class="text-xs text-gray-500 shrink-0">Planta</span>
                                <RouterLink
                                    :to="{ name: 'ops.plantes.show', params: { id: area.plant.id }, query: { from: 'ops.areas.show', fromId: area.id } }"
                                    class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 transition-colors text-right">
                                    {{ area.plant.name }}
                                </RouterLink>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Equipos -->
                <div v-if="activeTab === 'equipos'">
                    <h2 class="text-xs font-bold uppercase tracking-widest text-gray-500 mb-3">Equipos</h2>

                    <div v-if="area.equipment?.length" class="space-y-2">
                        <RouterLink v-for="eq in area.equipment" :key="eq.id"
                            :to="{ name: 'ops.equipos.show', params: { id: eq.id }, query: { from: 'ops.areas.show', fromId: area.id } }"
                            class="bg-white rounded-2xl border border-gray-100 shadow-sm hover:border-gray-200 hover:shadow-md transition-all p-4 flex items-center gap-3">
                            <!-- Criticality dot -->
                            <div class="w-2.5 h-2.5 rounded-full shrink-0" :class="critDot(eq.criticality)" />
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <p class="text-xs font-mono font-bold text-gray-500">{{ eq.code }}</p>
                                    <span class="text-xs font-semibold px-1.5 py-0.5 rounded-full" :class="statusBadge(eq.status)">
                                        {{ statusLabel(eq.status) }}
                                    </span>
                                </div>
                                <p class="text-sm font-semibold text-gray-800 mt-0.5 truncate">{{ eq.name }}</p>
                                <p v-if="eq.model" class="text-xs text-gray-500">{{ eq.model }}</p>
                            </div>
                            <div v-if="eq.kpi?.availability_percentage != null" class="text-right shrink-0">
                                <p class="text-xs font-bold" :class="eq.kpi.availability_percentage >= 95 ? 'text-emerald-600' : eq.kpi.availability_percentage >= 85 ? 'text-amber-500' : 'text-red-500'">
                                    {{ eq.kpi.availability_percentage.toFixed(0) }}%
                                </p>
                                <p class="text-xs text-gray-400">disp.</p>
                            </div>
                            <svg class="w-4 h-4 text-gray-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                        </RouterLink>
                    </div>

                    <div v-else class="bg-white rounded-2xl border border-gray-100 shadow-sm py-10 text-center text-xs text-gray-500">
                        Sin equipos registrados en esta área
                    </div>
                </div>

                <!-- Indicadores -->
                <div v-if="activeTab === 'indicadores'">
                    <h2 class="text-xs font-bold uppercase tracking-widest text-gray-500 mb-3">Indicadores</h2>

                    <div v-if="area.kpi" class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
                        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                            <p class="text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">Disponibilidad promedio</p>
                            <p class="text-3xl font-bold" :class="area.kpi.avg_availability >= 95 ? 'text-emerald-600' : area.kpi.avg_availability >= 85 ? 'text-amber-500' : 'text-red-500'">
                                {{ area.kpi.avg_availability != null ? area.kpi.avg_availability.toFixed(1) + '%' : '—' }}
                            </p>
                        </div>
                        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                            <p class="text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">MTBF promedio</p>
                            <p class="text-3xl font-bold text-gray-900">
                                {{ area.kpi.avg_mtbf != null ? area.kpi.avg_mtbf.toFixed(0) + ' h' : '—' }}
                            </p>
                        </div>
                        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                            <p class="text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">Total de fallas</p>
                            <p class="text-3xl font-bold text-red-600">{{ area.kpi.total_failures }}</p>
                        </div>
                    </div>

                    <!-- Per-equipment KPI mini-table -->
                    <div v-if="equipmentWithKpi.length">
                        <h3 class="text-xs font-bold uppercase tracking-widest text-gray-400 mb-2">Por equipo</h3>
                        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                            <div class="divide-y divide-gray-50">
                                <div v-for="eq in equipmentWithKpi" :key="eq.id"
                                    class="flex items-center gap-3 px-4 py-3">
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-semibold text-gray-800 truncate">{{ eq.name }}</p>
                                        <p class="text-xs font-mono text-gray-400">{{ eq.code }}</p>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <p class="text-xs font-bold" :class="eq.kpi.availability_percentage >= 95 ? 'text-emerald-600' : eq.kpi.availability_percentage >= 85 ? 'text-amber-500' : 'text-red-500'">
                                            {{ eq.kpi.availability_percentage != null ? eq.kpi.availability_percentage.toFixed(1) + '%' : '—' }}
                                        </p>
                                        <p class="text-xs text-gray-400">{{ eq.kpi.failure_count }} falla{{ eq.kpi.failure_count !== 1 ? 's' : '' }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else-if="!area.kpi" class="bg-white rounded-2xl border border-gray-100 shadow-sm py-10 text-center text-xs text-gray-500">
                        KPIs aún no calculados para esta área
                    </div>
                </div>

                <!-- Historial -->
                <div v-if="activeTab === 'historial'">
                    <h2 class="text-xs font-bold uppercase tracking-widest text-gray-500 mb-3">Historial</h2>
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-12 flex flex-col items-center gap-3 text-center">
                        <svg class="w-10 h-10 text-gray-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p class="text-sm font-semibold text-gray-400">Historial de actividad próximamente</p>
                        <p class="text-xs text-gray-400">Aquí aparecerán las últimas órdenes de trabajo y eventos del área</p>
                    </div>
                </div>

            </div>
        </template>

        <!-- Not found -->
        <div v-else class="p-8 text-center text-xs text-gray-500">Área no encontrada</div>

    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { useApi } from '../composables/useApi.js'

const route  = useRoute()
const router = useRouter()
const api    = useApi()

const loading  = ref(true)
const area     = ref(null)
const activeTab = ref('equipos')

// ── Tabs ──────────────────────────────────────────────────────────────────────

const tabs = computed(() => {
    if (!area.value) { return [] }
    return [
        { id: 'info',         label: 'Información' },
        { id: 'equipos',      label: 'Equipos', count: area.value.equipment_count || null },
        { id: 'indicadores',  label: 'Indicadores' },
        { id: 'historial',    label: 'Historial' },
    ]
})

const equipmentWithKpi = computed(() =>
    (area.value?.equipment ?? []).filter(eq => eq.kpi != null)
)

// ── Back navigation ───────────────────────────────────────────────────────────

const backLabel = computed(() => {
    const from = route.query.from
    if (from === 'ops.plantes.show') { return 'Planta' }
    if (from === 'ops.equipos.show') { return 'Equipo' }
    if (from === 'ops.equipos') { return 'Equipos' }
    if (from === 'ops.dashboard') { return 'Dashboard' }
    return 'Volver'
})

function goBack() {
    const from = route.query.from
    const fromId = route.query.fromId
    if (from && fromId && ['ops.plantes.show', 'ops.equipos.show'].includes(from)) {
        router.push({ name: from, params: { id: fromId } })
    } else if (from && ['ops.equipos', 'ops.dashboard'].includes(from)) {
        router.push({ name: from })
    } else {
        router.push({ name: 'ops.equipos' })
    }
}

// ── Status + criticality helpers ──────────────────────────────────────────────

function statusLabel(s) {
    return { active: 'Activo', inactive: 'Inactivo', under_maintenance: 'En mantenimiento', retired: 'Retirado' }[s] ?? s
}

function statusBadge(s) {
    return {
        active: 'bg-emerald-100 text-emerald-700',
        inactive: 'bg-gray-100 text-gray-500',
        under_maintenance: 'bg-amber-100 text-amber-700',
        retired: 'bg-red-100 text-red-500',
    }[s] ?? 'bg-gray-100 text-gray-600'
}

function critDot(c) {
    return { critical: 'bg-red-500', high: 'bg-orange-400', medium: 'bg-amber-400', low: 'bg-gray-300' }[c] ?? 'bg-gray-300'
}

// ── Data loading ──────────────────────────────────────────────────────────────

async function loadArea() {
    try {
        const res = await api.get(`areas/${route.params.id}/summary`)
        area.value = res ?? null
    } catch { /* silent */ } finally {
        loading.value = false
    }
}

onMounted(loadArea)
</script>
