<template>
    <div class="p-5 lg:p-8 max-w-6xl mx-auto space-y-8">

        <!-- Header -->
        <div>
            <h1 class="text-xl font-bold text-gray-900">Dashboard Gerencial</h1>
            <p class="text-sm text-gray-500 mt-0.5">Resumen ejecutivo del período actual</p>
        </div>

        <!-- SECTION 1: KPI Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-3 gap-3 lg:gap-4">

            <!-- Skeleton -->
            <template v-if="loading.summary">
                <div
                    v-for="i in 6"
                    :key="i"
                    class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 space-y-2"
                >
                    <div class="skeleton h-3 w-1/2 rounded" />
                    <div class="skeleton h-7 w-2/3 rounded" />
                    <div class="skeleton h-3 w-1/4 rounded" />
                </div>
            </template>

            <template v-else>
                <!-- Disponibilidad -->
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
                    <p class="text-xs text-gray-500 mb-1">Disponibilidad</p>
                    <p class="text-2xl font-bold" :class="availColor(summary.availability)">
                        {{ summary.availability != null ? summary.availability.toFixed(1) : '—' }}
                        <span class="text-base font-medium">%</span>
                    </p>
                    <p class="text-xs mt-1" :class="availBadgeClass(summary.availability)">
                        {{ availLabel(summary.availability) }}
                    </p>
                </div>

                <!-- MTBF -->
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
                    <p class="text-xs text-gray-500 mb-1">MTBF</p>
                    <p class="text-2xl font-bold text-gray-900">
                        {{ summary.mtbf_hours != null ? summary.mtbf_hours.toFixed(0) : '—' }}
                        <span class="text-base font-normal text-gray-500">h</span>
                    </p>
                    <p class="text-xs text-gray-400 mt-1">Tiempo medio entre fallas</p>
                </div>

                <!-- MTTR -->
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
                    <p class="text-xs text-gray-500 mb-1">MTTR</p>
                    <p class="text-2xl font-bold text-gray-900">
                        {{ summary.mttr_hours != null ? summary.mttr_hours.toFixed(1) : '—' }}
                        <span class="text-base font-normal text-gray-500">h</span>
                    </p>
                    <p class="text-xs text-gray-400 mt-1">Tiempo medio de reparación</p>
                </div>

                <!-- OT Abiertas -->
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
                    <p class="text-xs text-gray-500 mb-1">OT Abiertas</p>
                    <p class="text-2xl font-bold text-gray-900">{{ summary.open_work_orders ?? '—' }}</p>
                    <p class="text-xs text-gray-400 mt-1">Órdenes de trabajo activas</p>
                </div>

                <!-- Preventivos Vencidos -->
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
                    <p class="text-xs text-gray-500 mb-1">Preventivos Vencidos</p>
                    <div class="flex items-baseline gap-2">
                        <p
                            class="text-2xl font-bold"
                            :class="summary.overdue_preventives > 0 ? 'text-red-600' : 'text-gray-900'"
                        >
                            {{ summary.overdue_preventives ?? '—' }}
                        </p>
                        <span
                            v-if="summary.overdue_preventives > 0"
                            class="text-xs font-semibold px-1.5 py-0.5 rounded-full bg-red-100 text-red-700"
                        >
                            Vencido
                        </span>
                    </div>
                    <p class="text-xs text-gray-400 mt-1">Planes preventivos atrasados</p>
                </div>

                <!-- Costo Mensual -->
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
                    <p class="text-xs text-gray-500 mb-1">Costo Mensual</p>
                    <p class="text-2xl font-bold text-gray-900">{{ formatCost(summary.monthly_cost) }}</p>
                    <p class="text-xs text-gray-400 mt-1">Mantenimiento total del período</p>
                </div>
            </template>
        </div>

        <!-- SECTION 2: Salud por Área -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-800">Salud por Área</h2>
            </div>

            <!-- Skeleton -->
            <div v-if="loading.areas" class="divide-y divide-gray-50">
                <div v-for="i in 4" :key="i" class="px-5 py-3.5 flex items-center gap-4">
                    <div class="skeleton h-3 w-24 rounded" />
                    <div class="skeleton h-3 w-16 rounded" />
                    <div class="skeleton h-3 w-12 rounded" />
                    <div class="skeleton h-3 w-12 rounded" />
                    <div class="skeleton h-3 w-20 rounded ml-auto" />
                </div>
            </div>

            <!-- Table -->
            <div v-else-if="areas.length" class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100">
                            <th class="px-5 py-2.5 text-left text-xs font-medium text-gray-500">Área</th>
                            <th class="px-4 py-2.5 text-center text-xs font-medium text-gray-500">Disponibilidad</th>
                            <th class="px-4 py-2.5 text-center text-xs font-medium text-gray-500">Fallas</th>
                            <th class="px-4 py-2.5 text-center text-xs font-medium text-gray-500">MTTR (h)</th>
                            <th class="px-5 py-2.5 text-right text-xs font-medium text-gray-500">Costo Mensual</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr
                            v-for="area in areas"
                            :key="area.code"
                            class="hover:bg-gray-50 transition-colors"
                        >
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="w-2 h-2 rounded-full shrink-0"
                                        :class="availDotClass(area.availability)"
                                    />
                                    <div>
                                        <p class="text-sm font-medium text-gray-800">{{ area.name }}</p>
                                        <p class="text-xs text-gray-400 font-mono">{{ area.code }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span
                                    v-if="area.availability"
                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold"
                                    :class="availBadgePill(area.availability)"
                                >
                                    {{ area.availability.toFixed(1) }}%
                                </span>
                                <span v-else class="text-xs text-gray-400">Sin datos</span>
                            </td>
                            <td
                                class="px-4 py-3 text-center text-sm"
                                :class="area.failure_count > 0 ? 'text-red-600 font-semibold' : 'text-gray-700'"
                            >
                                {{ area.failure_count ?? 0 }}
                            </td>
                            <td class="px-4 py-3 text-center text-sm text-gray-700">
                                {{ area.mttr_hours != null ? area.mttr_hours.toFixed(1) : '—' }}
                            </td>
                            <td class="px-5 py-3 text-right text-sm text-gray-700 font-medium">
                                {{ formatCost(area.monthly_cost) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <EmptyState
                v-else
                icon="chartBar"
                title="Sin datos de áreas"
                subtitle="No se encontraron áreas con información del período actual."
            />
        </div>

        <!-- SECTION 3: Top 10 Equipos Críticos -->
        <div>
            <h2 class="text-sm font-semibold text-gray-800 mb-3">Equipos Críticos</h2>

            <!-- Skeleton -->
            <div v-if="loading.topEquipment" class="grid grid-cols-2 lg:grid-cols-3 gap-3">
                <div v-for="i in 6" :key="i" class="bg-white rounded-2xl border border-gray-100 p-4 space-y-3">
                    <div class="skeleton h-3 w-16 rounded" />
                    <div class="skeleton h-4 w-3/4 rounded" />
                    <div class="grid grid-cols-3 gap-2">
                        <div v-for="j in 3" :key="j" class="skeleton h-8 rounded-lg" />
                    </div>
                </div>
            </div>

            <!-- Equipment cards -->
            <div v-else-if="topEquipment.length" class="grid grid-cols-2 lg:grid-cols-3 gap-3">
                <div
                    v-for="equip in topEquipment"
                    :key="equip.id"
                    class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4"
                >
                    <p class="text-xs font-mono text-gray-400 mb-0.5">{{ equip.code }}</p>
                    <p class="text-sm font-bold text-gray-900 leading-snug mb-1">{{ equip.name }}</p>
                    <p class="text-xs text-gray-400 mb-3">{{ equip.area_name }}</p>
                    <div class="grid grid-cols-3 gap-1 text-center">
                        <div class="bg-red-50 rounded-lg px-1 py-2">
                            <p class="text-xs text-red-400 mb-0.5">Fallas</p>
                            <p class="text-sm font-bold text-red-600">{{ equip.failure_count ?? 0 }}</p>
                        </div>
                        <div class="bg-amber-50 rounded-lg px-1 py-2">
                            <p class="text-xs text-amber-400 mb-0.5">Downtime</p>
                            <p class="text-sm font-bold text-amber-600">{{ equip.downtime_hours != null ? equip.downtime_hours.toFixed(0) + 'h' : '—' }}</p>
                        </div>
                        <div class="bg-slate-50 rounded-lg px-1 py-2">
                            <p class="text-xs text-slate-400 mb-0.5">Costo</p>
                            <p class="text-sm font-bold text-slate-600">{{ formatCostCompact(equip.monthly_cost) }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <EmptyState
                v-else
                icon="cube"
                title="Sin equipos críticos"
                subtitle="No se registraron fallas en el período actual."
            />
        </div>

        <!-- SECTION 4: Costos por Tipo -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
            <div class="flex items-baseline justify-between mb-4">
                <h2 class="text-sm font-semibold text-gray-800">Costos por Tipo</h2>
                <p v-if="costs.period_start" class="text-xs text-gray-400">{{ formatPeriodLabel(costs.period_start, costs.period_end) }}</p>
            </div>

            <div v-if="loading.costs" class="space-y-3">
                <div v-for="i in 4" :key="i" class="flex items-center gap-3 h-9">
                    <div class="skeleton h-3 w-24 rounded" />
                    <div class="flex-1 skeleton h-4 rounded-full" />
                    <div class="skeleton h-3 w-16 rounded" />
                </div>
            </div>

            <HorizontalBarChart
                v-else
                :items="costChartItems"
            />

            <div v-if="!loading.costs && costs.total != null" class="mt-4 pt-4 border-t border-gray-100 flex justify-between items-center">
                <span class="text-xs text-gray-500 font-medium">Total</span>
                <span class="text-sm font-bold text-gray-900">{{ formatCost(costs.total) }}</span>
            </div>
        </div>

        <!-- SECTION 5: Tendencias 12 Meses -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
            <h2 class="text-sm font-semibold text-gray-800 mb-1">Tendencias 12 Meses</h2>
            <p class="text-xs text-gray-400 mb-5">Cada serie normalizada a su propio rango (0–100%). Pase el cursor sobre los puntos para ver valores reales.</p>

            <div v-if="loading.trends" class="h-44 skeleton rounded-xl" />

            <MultiLineChart
                v-else-if="trendSeries.length"
                :series="trendSeries"
                :months="trendMonths"
                :chart-height-px="180"
            />

            <EmptyState
                v-else
                icon="chartBar"
                title="Sin datos de tendencias"
                subtitle="Los datos de tendencias se generan automáticamente con el tiempo."
            />
        </div>

    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useApi } from '../composables/useApi.js'
import EmptyState from '../components/EmptyState.vue'
import HorizontalBarChart from '../components/charts/HorizontalBarChart.vue'
import MultiLineChart from '../components/charts/MultiLineChart.vue'

const api = useApi()

// ── State ─────────────────────────────────────────────────────────────────────

const loading = ref({ summary: true, areas: true, topEquipment: true, costs: true, trends: true })

const summary = ref({
    availability: null,
    mtbf_hours: null,
    mttr_hours: null,
    open_work_orders: null,
    overdue_preventives: null,
    monthly_cost: null,
})

const areas = ref([])
const topEquipment = ref([])
const costs = ref({ corrective: 0, preventive: 0, predictive: 0, other: 0, total: 0, period_start: null, period_end: null })
const trends = ref([])

// ── Computed ──────────────────────────────────────────────────────────────────

const costChartItems = computed(() => [
    { label: 'Correctivo', value: costs.value.corrective ?? 0, color: 'bg-red-400' },
    { label: 'Preventivo', value: costs.value.preventive ?? 0, color: 'bg-emerald-500' },
    { label: 'Predictivo', value: costs.value.predictive ?? 0, color: 'bg-indigo-500' },
    { label: 'Otro', value: costs.value.other ?? 0, color: 'bg-slate-400' },
])

const trendMonths = computed(() =>
    trends.value.map(t => t.month ? formatMonthLabel(t.month) : '')
)

const trendSeries = computed(() => {
    if (!trends.value.length) { return [] }
    return [
        {
            label: 'Disponibilidad',
            color: 'emerald',
            data: trends.value.map(t => ({ x: t.month, y: t.availability ?? 0 })),
        },
        {
            label: 'MTBF (h)',
            color: 'indigo',
            data: trends.value.map(t => ({ x: t.month, y: t.mtbf_hours ?? 0 })),
        },
        {
            label: 'MTTR (h)',
            color: 'amber',
            data: trends.value.map(t => ({ x: t.month, y: t.mttr_hours ?? 0 })),
        },
        {
            label: 'Costo',
            color: 'slate',
            data: trends.value.map(t => ({ x: t.month, y: t.cost ?? 0 })),
        },
    ]
})

// ── Helpers ───────────────────────────────────────────────────────────────────

function availColor(pct) {
    if (pct == null) { return 'text-gray-900' }
    if (pct >= 90) { return 'text-emerald-600' }
    if (pct >= 75) { return 'text-amber-600' }
    return 'text-red-600'
}

function availLabel(pct) {
    if (pct == null) { return 'Sin datos' }
    if (pct >= 90) { return 'Disponibilidad excelente' }
    if (pct >= 75) { return 'Disponibilidad aceptable' }
    return 'Disponibilidad crítica'
}

function availBadgeClass(pct) {
    if (pct == null) { return 'text-gray-400' }
    if (pct >= 90) { return 'text-emerald-600' }
    if (pct >= 75) { return 'text-amber-600' }
    return 'text-red-600'
}

function availDotClass(pct) {
    if (!pct) { return 'bg-gray-300' }
    if (pct >= 90) { return 'bg-emerald-500' }
    if (pct >= 75) { return 'bg-amber-500' }
    return 'bg-red-500'
}

function availBadgePill(pct) {
    if (!pct) { return 'bg-gray-100 text-gray-500' }
    if (pct >= 90) { return 'bg-emerald-50 text-emerald-700' }
    if (pct >= 75) { return 'bg-amber-50 text-amber-700' }
    return 'bg-red-50 text-red-700'
}

function formatCost(value) {
    if (value == null || value === 0) { return '—' }
    return value.toLocaleString('es-AR', { style: 'currency', currency: 'ARS', maximumFractionDigits: 0 })
}

function formatCostCompact(value) {
    if (value == null || value === 0) { return '—' }
    if (value >= 1_000_000) { return '$' + (value / 1_000_000).toFixed(1) + 'M' }
    if (value >= 1_000) { return '$' + (value / 1_000).toFixed(0) + 'k' }
    return '$' + value.toLocaleString('es')
}

function formatPeriodLabel(start, end) {
    if (!start) { return '' }
    const fmt = { month: 'long', year: 'numeric' }
    const s = new Date(start + 'T12:00:00').toLocaleDateString('es', fmt)
    if (!end) { return s }
    const e = new Date(end + 'T12:00:00').toLocaleDateString('es', fmt)
    return s + ' — ' + e
}

function formatMonthLabel(monthStr) {
    if (!monthStr) { return '' }
    const [year, month] = monthStr.split('-')
    const date = new Date(Number(year), Number(month) - 1, 1)
    return date.toLocaleDateString('es', { month: 'short', year: '2-digit' })
}

// ── API ───────────────────────────────────────────────────────────────────────

async function fetchAll() {
    const [summaryRes, areasRes, topEquipRes, costsRes, trendsRes] = await Promise.allSettled([
        api.get('executive/summary'),
        api.get('executive/areas'),
        api.get('executive/top-equipment'),
        api.get('executive/costs'),
        api.get('executive/trends'),
    ])

    if (summaryRes.status === 'fulfilled' && summaryRes.value) {
        summary.value = { ...summary.value, ...summaryRes.value }
    }
    loading.value.summary = false

    if (areasRes.status === 'fulfilled' && areasRes.value?.data) {
        areas.value = areasRes.value.data
    }
    loading.value.areas = false

    if (topEquipRes.status === 'fulfilled' && topEquipRes.value?.data) {
        topEquipment.value = topEquipRes.value.data
    }
    loading.value.topEquipment = false

    if (costsRes.status === 'fulfilled' && costsRes.value) {
        costs.value = { ...costs.value, ...costsRes.value }
    }
    loading.value.costs = false

    if (trendsRes.status === 'fulfilled' && trendsRes.value?.data) {
        trends.value = trendsRes.value.data
    }
    loading.value.trends = false
}

onMounted(fetchAll)
</script>
