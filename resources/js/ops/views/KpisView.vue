<template>
    <div class="p-5 lg:p-8 max-w-5xl mx-auto">

        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-xl font-bold text-gray-900">KPIs de Confiabilidad</h1>
            <p v-if="!loading" class="text-sm text-gray-500 mt-0.5">{{ kpis.length }} equipos con datos del último período</p>
        </div>

        <!-- Skeleton -->
        <div v-if="loading" class="space-y-4">
            <!-- Summary skeleton -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
                <div v-for="i in 4" :key="i" class="bg-white rounded-2xl border border-gray-100 p-4 space-y-2">
                    <div class="skeleton h-3 w-1/2 rounded" />
                    <div class="skeleton h-7 w-2/3 rounded" />
                </div>
            </div>
            <!-- Card skeleton -->
            <div v-for="i in 5" :key="i" class="bg-white rounded-2xl border border-gray-100 p-4 space-y-3">
                <div class="skeleton h-5 w-1/3 rounded" />
                <div class="grid grid-cols-4 gap-3">
                    <div v-for="j in 4" :key="j" class="skeleton h-10 rounded-xl" />
                </div>
            </div>
        </div>

        <!-- Content -->
        <template v-else-if="kpis.length">

            <!-- Fleet summary -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
                <div class="bg-white rounded-2xl border border-gray-100 p-4">
                    <p class="text-xs text-gray-500 mb-1">Disponibilidad flota</p>
                    <p class="text-2xl font-bold" :class="availColor(fleetAvailability)">{{ fleetAvailability.toFixed(1) }}%</p>
                </div>
                <div class="bg-white rounded-2xl border border-gray-100 p-4">
                    <p class="text-xs text-gray-500 mb-1">MTBF promedio</p>
                    <p class="text-2xl font-bold text-gray-900">{{ fleetMtbf.toFixed(0) }} <span class="text-sm font-normal text-gray-500">h</span></p>
                </div>
                <div class="bg-white rounded-2xl border border-gray-100 p-4">
                    <p class="text-xs text-gray-500 mb-1">Total fallas</p>
                    <p class="text-2xl font-bold text-gray-900">{{ fleetFailures }}</p>
                </div>
                <div class="bg-white rounded-2xl border border-gray-100 p-4">
                    <p class="text-xs text-gray-500 mb-1">Equipos analizados</p>
                    <p class="text-2xl font-bold text-gray-900">{{ kpis.length }}</p>
                </div>
            </div>

            <!-- Period filter -->
            <div class="flex gap-1.5 mb-5 overflow-x-auto pb-1">
                <button
                    v-for="p in periodOptions"
                    :key="p.value"
                    @click="activePeriod = p.value"
                    class="shrink-0 px-3.5 py-1.5 rounded-full text-xs font-semibold transition-colors"
                    :class="activePeriod === p.value
                        ? 'bg-slate-900 text-white'
                        : 'bg-white border border-gray-200 text-gray-600 hover:border-gray-300'"
                >
                    {{ p.label }}
                </button>
            </div>

            <!-- Sort control -->
            <div class="flex items-center gap-3 mb-4">
                <span class="text-xs text-gray-500">Ordenar por:</span>
                <select
                    v-model="sortBy"
                    class="text-xs border border-gray-200 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                >
                    <option value="availability">Disponibilidad</option>
                    <option value="failures">Fallas</option>
                    <option value="mtbf">MTBF</option>
                    <option value="name">Nombre</option>
                </select>
            </div>

            <!-- KPI cards -->
            <div class="space-y-3">
                <div
                    v-for="kpi in sortedKpis"
                    :key="kpi.id"
                    class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden"
                >
                    <!-- Card header -->
                    <div class="px-4 pt-4 pb-3 flex items-center gap-3">
                        <!-- Availability donut placeholder -->
                        <div class="relative w-12 h-12 shrink-0">
                            <svg class="w-12 h-12 -rotate-90" viewBox="0 0 44 44">
                                <circle cx="22" cy="22" r="18" fill="none" stroke="#f3f4f6" stroke-width="5" />
                                <circle
                                    cx="22" cy="22" r="18" fill="none"
                                    :stroke="availStroke(kpi.availability_percentage)"
                                    stroke-width="5"
                                    stroke-linecap="round"
                                    :stroke-dasharray="`${(kpi.availability_percentage ?? 0) * 1.1310} 113.10`"
                                />
                            </svg>
                            <span class="absolute inset-0 flex items-center justify-center text-xs font-bold" :class="availColor(kpi.availability_percentage ?? 0)">
                                {{ kpi.availability_percentage != null ? kpi.availability_percentage.toFixed(0) : '—' }}%
                            </span>
                        </div>

                        <div class="flex-1 min-w-0">
                            <RouterLink
                                :to="{ name: 'ops.equipos.show', params: { id: kpi.equipment.id } }"
                                class="text-sm font-bold text-gray-900 hover:text-indigo-600 transition-colors"
                            >
                                {{ kpi.equipment.name }}
                            </RouterLink>
                            <p class="text-xs text-gray-500 font-mono">{{ kpi.equipment.code }}</p>
                        </div>

                        <div class="text-right shrink-0">
                            <p class="text-xs text-gray-500">Período</p>
                            <p class="text-xs font-medium text-gray-700">{{ formatPeriod(kpi.period_start, kpi.period_end) }}</p>
                        </div>
                    </div>

                    <!-- Metrics row -->
                    <div class="grid grid-cols-4 gap-0 border-t border-gray-50">
                        <div class="px-3 py-2.5 border-r border-gray-50 text-center">
                            <p class="text-xs text-gray-500 mb-0.5">Disponib.</p>
                            <p class="text-sm font-bold" :class="availColor(kpi.availability_percentage ?? 0)">
                                {{ kpi.availability_percentage != null ? kpi.availability_percentage.toFixed(1) + '%' : '—' }}
                            </p>
                        </div>
                        <div class="px-3 py-2.5 border-r border-gray-50 text-center">
                            <p class="text-xs text-gray-500 mb-0.5">MTBF</p>
                            <p class="text-sm font-bold text-gray-900">
                                {{ kpi.mtbf_hours != null ? kpi.mtbf_hours.toFixed(0) + ' h' : '—' }}
                            </p>
                        </div>
                        <div class="px-3 py-2.5 border-r border-gray-50 text-center">
                            <p class="text-xs text-gray-500 mb-0.5">MTTR</p>
                            <p class="text-sm font-bold text-gray-900">
                                {{ kpi.mttr_hours != null ? kpi.mttr_hours.toFixed(1) + ' h' : '—' }}
                            </p>
                        </div>
                        <div class="px-3 py-2.5 text-center">
                            <p class="text-xs text-gray-500 mb-0.5">Fallas</p>
                            <p class="text-sm font-bold" :class="kpi.failure_count > 0 ? 'text-red-600' : 'text-gray-900'">
                                {{ kpi.failure_count ?? 0 }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Load more -->
            <button
                v-if="nextCursor"
                @click="loadMore"
                :disabled="loadingMore"
                class="w-full mt-4 py-3 text-sm text-gray-500 hover:text-gray-700 font-medium transition-colors"
            >
                {{ loadingMore ? 'Cargando…' : 'Cargar más equipos' }}
            </button>

        </template>

        <!-- Empty -->
        <EmptyState
            v-else
            icon="chartBar"
            title="Sin datos de KPI"
            subtitle="Los KPIs se calculan automáticamente con el registro de mantenimientos."
        />

    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { useApi } from '../composables/useApi.js'
import EmptyState from '../components/EmptyState.vue'

const api = useApi()
const kpis = ref([])
const loading = ref(true)
const loadingMore = ref(false)
const nextCursor = ref(null)
const activePeriod = ref('')
const sortBy = ref('availability')

const periodOptions = [
    { label: 'Todo', value: '' },
    { label: '1 mes', value: '1' },
    { label: '3 meses', value: '3' },
    { label: '6 meses', value: '6' },
    { label: '12 meses', value: '12' },
]

// ── Fleet aggregates ──────────────────────────────────────────────────────────

const fleetAvailability = computed(() => {
    const valid = kpis.value.filter(k => k.availability_percentage != null)
    if (!valid.length) { return 0 }
    return valid.reduce((sum, k) => sum + k.availability_percentage, 0) / valid.length
})

const fleetMtbf = computed(() => {
    const valid = kpis.value.filter(k => k.mtbf_hours != null)
    if (!valid.length) { return 0 }
    return valid.reduce((sum, k) => sum + k.mtbf_hours, 0) / valid.length
})

const fleetFailures = computed(() => kpis.value.reduce((sum, k) => sum + (k.failure_count ?? 0), 0))

// ── Sorted KPIs ───────────────────────────────────────────────────────────────

const sortedKpis = computed(() => {
    return [...kpis.value].sort((a, b) => {
        if (sortBy.value === 'availability') {
            return (b.availability_percentage ?? -1) - (a.availability_percentage ?? -1)
        }
        if (sortBy.value === 'failures') {
            return (b.failure_count ?? 0) - (a.failure_count ?? 0)
        }
        if (sortBy.value === 'mtbf') {
            return (b.mtbf_hours ?? -1) - (a.mtbf_hours ?? -1)
        }
        return (a.equipment?.name ?? '').localeCompare(b.equipment?.name ?? '', 'es')
    })
})

// ── Helpers ───────────────────────────────────────────────────────────────────

function availColor(pct) {
    if (pct >= 90) { return 'text-emerald-600' }
    if (pct >= 70) { return 'text-amber-600' }
    return 'text-red-600'
}

function availStroke(pct) {
    if (pct >= 90) { return '#059669' }
    if (pct >= 70) { return '#d97706' }
    return '#dc2626'
}

function formatPeriod(start, end) {
    if (!start || !end) { return '—' }
    const fmt = { month: 'short', year: 'numeric' }
    return `${new Date(start).toLocaleDateString('es', fmt)} — ${new Date(end).toLocaleDateString('es', fmt)}`
}

// ── API ───────────────────────────────────────────────────────────────────────

function buildParams(cursor = null) {
    const params = new URLSearchParams({ per_page: '50' })
    if (activePeriod.value) { params.set('period_months', activePeriod.value) }
    if (cursor) { params.set('cursor', cursor) }
    return params.toString()
}

async function load() {
    loading.value = true
    nextCursor.value = null
    try {
        const res = await api.get(`reliability/kpis?${buildParams()}`)
        kpis.value = res?.data ?? []
        nextCursor.value = res?.meta?.next_cursor ?? null
    } catch { /* silent */ } finally {
        loading.value = false
    }
}

async function loadMore() {
    if (!nextCursor.value || loadingMore.value) { return }
    loadingMore.value = true
    try {
        const res = await api.get(`reliability/kpis?${buildParams(nextCursor.value)}`)
        kpis.value = [...kpis.value, ...(res?.data ?? [])]
        nextCursor.value = res?.meta?.next_cursor ?? null
    } catch { /* silent */ } finally {
        loadingMore.value = false
    }
}

watch(activePeriod, load)
onMounted(load)
</script>
