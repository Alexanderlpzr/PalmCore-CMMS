<template>
    <div class="p-5 lg:p-8 max-w-7xl mx-auto space-y-8">

        <!-- Header — distinct slate/indigo executive treatment -->
        <div class="rounded-2xl bg-gradient-to-r from-slate-900 to-indigo-900 text-white p-6 shadow-sm">
            <p class="text-xs uppercase tracking-widest text-indigo-300 font-semibold">Super Admin</p>
            <h1 class="text-2xl font-bold mt-1">Dashboard de Plataforma</h1>
            <p class="text-sm text-slate-300 mt-1">Métricas globales agregadas de todas las empresas.</p>
        </div>

        <p v-if="error" class="rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3">
            {{ error }}
        </p>

        <!-- SECTION 1: Global KPI cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 lg:gap-4">
            <template v-if="loading.summary">
                <div v-for="i in 8" :key="i" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 space-y-2">
                    <div class="skeleton h-3 w-1/2 rounded" />
                    <div class="skeleton h-7 w-2/3 rounded" />
                </div>
            </template>

            <template v-else>
                <StatCard label="Empresas" :value="summary.tenants.total" :sub="`${summary.tenants.active} activas`" />
                <StatCard label="Usuarios" :value="summary.users.total" :sub="`${summary.users.active} activos`" />
                <StatCard label="Equipos" :value="summary.equipment.total" sub="Total registrados" />
                <StatCard label="OT Abiertas" :value="summary.open_work_orders" sub="En toda la plataforma" />
                <StatCard label="Preventivos" :value="summary.preventive_plans" sub="Planes activos" />
                <StatCard
                    label="Alertas Críticas"
                    :value="summary.critical_alerts"
                    :value-class="summary.critical_alerts > 0 ? 'text-red-600' : 'text-gray-900'"
                    sub="Sin resolver"
                />
                <StatCard
                    label="Disponibilidad Prom."
                    :value="`${summary.avg_availability.toFixed(1)}%`"
                    sub="Promedio global"
                />
                <StatCard label="Costos Globales" :value="money(summary.global_cost_month)" sub="Mes actual" />
            </template>
        </div>

        <!-- SECTION 2: Rankings -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <RankCard title="Top empresas por equipos" :rows="analytics.top_by_equipment" unit="equipos" />
            <RankCard title="Empresas con más OT" :rows="analytics.top_by_work_orders" unit="OT" />
            <RankCard title="Empresas con más alertas" :rows="analytics.top_by_alerts" unit="alertas" />
        </div>

        <!-- SECTION 3: Storage + Subscriptions -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

            <!-- Storage -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                <div class="flex items-baseline justify-between mb-3">
                    <h2 class="text-sm font-semibold text-gray-900">Uso de almacenamiento</h2>
                    <span class="text-sm font-bold text-indigo-700">{{ formatBytes(analytics.storage.total_bytes) }}</span>
                </div>
                <div v-if="loading.analytics" class="space-y-2">
                    <div v-for="i in 4" :key="i" class="skeleton h-4 w-full rounded" />
                </div>
                <ul v-else-if="analytics.storage.by_tenant.length" class="divide-y divide-gray-50">
                    <li v-for="row in analytics.storage.by_tenant" :key="row.tenant_id" class="flex items-center justify-between py-2">
                        <span class="text-sm text-gray-700 truncate">{{ row.name }}</span>
                        <span class="text-sm font-medium text-gray-900 shrink-0 ml-3">{{ formatBytes(row.bytes) }}</span>
                    </li>
                </ul>
                <p v-else class="text-sm text-gray-400 py-4 text-center">Sin datos de almacenamiento.</p>
            </div>

            <!-- Subscriptions -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                <div class="flex items-baseline justify-between mb-3">
                    <h2 class="text-sm font-semibold text-gray-900">Suscripciones</h2>
                    <span class="text-sm font-bold text-emerald-700">{{ analytics.subscriptions.active }} activas</span>
                </div>
                <div v-if="loading.analytics" class="space-y-2">
                    <div v-for="i in 4" :key="i" class="skeleton h-4 w-full rounded" />
                </div>
                <ul v-else class="divide-y divide-gray-50">
                    <li v-for="row in analytics.subscriptions.by_plan" :key="row.plan" class="flex items-center justify-between py-2">
                        <span class="text-sm text-gray-700 capitalize">{{ planLabel(row.plan) }}</span>
                        <span class="text-sm font-medium text-gray-900">{{ row.count }}</span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- SECTION 4: Expiring soon -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
            <h2 class="text-sm font-semibold text-gray-900 mb-3">Empresas próximas a vencer (30 días)</h2>
            <div v-if="loading.analytics" class="space-y-2">
                <div v-for="i in 3" :key="i" class="skeleton h-5 w-full rounded" />
            </div>
            <ul v-else-if="analytics.expiring_soon.length" class="divide-y divide-gray-50">
                <li v-for="row in analytics.expiring_soon" :key="row.tenant_id" class="flex items-center justify-between py-2.5">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-900 truncate">{{ row.name }}</p>
                        <p class="text-xs text-gray-400 capitalize">{{ planLabel(row.plan) }} · vence {{ row.expires_at }}</p>
                    </div>
                    <span
                        class="shrink-0 ml-3 text-xs font-semibold rounded-full px-2.5 py-1"
                        :class="row.days_left <= 7 ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700'"
                    >
                        {{ row.days_left }} día(s)
                    </span>
                </li>
            </ul>
            <p v-else class="text-sm text-gray-400 py-4 text-center">Ninguna empresa vence en los próximos 30 días.</p>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, h } from 'vue'
import { useApi } from '../composables/useApi.js'

const api = useApi()

const loading = ref({ summary: true, analytics: true })
const error = ref(null)

const summary = ref({
    tenants: { total: 0, active: 0 },
    users: { total: 0, active: 0 },
    equipment: { total: 0 },
    open_work_orders: 0,
    preventive_plans: 0,
    critical_alerts: 0,
    avg_availability: 0,
    global_cost_month: 0,
})

const analytics = ref({
    top_by_equipment: [],
    top_by_work_orders: [],
    top_by_alerts: [],
    storage: { total_bytes: 0, by_tenant: [] },
    subscriptions: { active: 0, by_plan: [] },
    expiring_soon: [],
})

function money(value) {
    return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(value ?? 0)
}

function formatBytes(bytes) {
    const b = Number(bytes ?? 0)
    if (b === 0) return '0 B'
    const units = ['B', 'KB', 'MB', 'GB', 'TB']
    const i = Math.floor(Math.log(b) / Math.log(1024))
    return `${(b / Math.pow(1024, i)).toFixed(i === 0 ? 0 : 1)} ${units[i]}`
}

const PLAN_LABELS = { trial: 'Prueba', starter: 'Inicial', professional: 'Profesional', enterprise: 'Empresarial' }
function planLabel(plan) {
    return PLAN_LABELS[plan] ?? plan
}

// Lightweight presentational components defined locally to avoid reusing the
// operations dashboard widgets (per ADMIN-3).
const StatCard = (props) => h('div', { class: 'bg-white rounded-2xl border border-gray-100 shadow-sm p-4' }, [
    h('p', { class: 'text-xs text-gray-500 mb-1' }, props.label),
    h('p', { class: `text-2xl font-bold ${props.valueClass ?? 'text-gray-900'}` }, String(props.value)),
    props.sub ? h('p', { class: 'text-xs text-gray-400 mt-1' }, props.sub) : null,
])
StatCard.props = ['label', 'value', 'sub', 'valueClass']

const RankCard = (props) => h('div', { class: 'bg-white rounded-2xl border border-gray-100 shadow-sm p-5' }, [
    h('h2', { class: 'text-sm font-semibold text-gray-900 mb-3' }, props.title),
    props.rows && props.rows.length
        ? h('ul', { class: 'divide-y divide-gray-50' }, props.rows.map((row, idx) => h('li', {
            key: row.tenant_id,
            class: 'flex items-center justify-between py-2',
        }, [
            h('span', { class: 'text-sm text-gray-700 truncate' }, `${idx + 1}. ${row.name}`),
            h('span', { class: 'text-sm font-medium text-gray-900 shrink-0 ml-3' }, `${row.count} ${props.unit}`),
        ])))
        : h('p', { class: 'text-sm text-gray-400 py-4 text-center' }, 'Sin datos.'),
])
RankCard.props = ['title', 'rows', 'unit']

async function load() {
    try {
        const [summaryData, analyticsData] = await Promise.all([
            api.get('platform/summary'),
            api.get('platform/analytics'),
        ])
        summary.value = summaryData
        loading.value.summary = false
        analytics.value = analyticsData
        loading.value.analytics = false
    } catch (e) {
        error.value = e.message ?? 'No se pudieron cargar las métricas de plataforma.'
        loading.value.summary = false
        loading.value.analytics = false
    }
}

onMounted(load)
</script>
