<template>
    <div class="p-5 lg:p-8 max-w-6xl mx-auto space-y-7">

        <!-- Header -->
        <div>
            <h1 class="text-xl font-bold text-gray-900">Bienvenido, {{ firstName }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ formattedDate }}</p>
        </div>

        <!-- Stat cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 lg:gap-4">
            <StatCard
                v-for="stat in stats"
                :key="stat.label"
                :stat="stat"
                :loading="loadingStats"
            />
        </div>

        <!-- Mis OTs + Actividad (2 columns on desktop) -->
        <div class="grid lg:grid-cols-5 gap-5">

            <!-- Mis órdenes de trabajo (left, wider) -->
            <div class="lg:col-span-3 bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-800">Mis órdenes de trabajo</h2>
                    <RouterLink :to="{ name: 'ops.ordenes' }" class="text-xs text-emerald-600 hover:text-emerald-700 font-medium">Ver todas</RouterLink>
                </div>

                <!-- Loading skeleton -->
                <div v-if="loadingWOs" class="divide-y divide-gray-50">
                    <div v-for="i in 3" :key="i" class="px-5 py-4 flex items-center gap-3">
                        <div class="skeleton w-8 h-8 rounded-lg" />
                        <div class="flex-1 space-y-2">
                            <div class="skeleton h-3 w-3/4 rounded" />
                            <div class="skeleton h-2.5 w-1/2 rounded" />
                        </div>
                    </div>
                </div>

                <!-- Empty state -->
                <div v-else-if="myWorkOrders.length === 0" class="flex flex-col items-center justify-center py-12 px-5 text-center">
                    <div class="w-12 h-12 rounded-2xl bg-gray-100 flex items-center justify-center mb-3">
                        <svg class="w-6 h-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <p class="text-sm font-medium text-gray-700">Sin órdenes asignadas</p>
                    <p class="text-xs text-gray-400 mt-1">No tienes OTs activas en este momento</p>
                </div>

                <!-- WO list -->
                <div v-else class="divide-y divide-gray-50">
                    <WorkOrderRow v-for="wo in myWorkOrders" :key="wo.id" :wo="wo" />
                </div>
            </div>

            <!-- Actividad reciente (right, narrower) -->
            <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-800">Actividad reciente</h2>
                </div>

                <div v-if="loadingActivity" class="px-5 py-4 space-y-4">
                    <div v-for="i in 4" :key="i" class="flex gap-3">
                        <div class="skeleton w-6 h-6 rounded-full mt-0.5 shrink-0" />
                        <div class="flex-1 space-y-1.5">
                            <div class="skeleton h-2.5 w-full rounded" />
                            <div class="skeleton h-2 w-2/3 rounded" />
                        </div>
                    </div>
                </div>

                <div v-else class="flex flex-col items-center justify-center py-12 px-5 text-center">
                    <div class="w-12 h-12 rounded-2xl bg-gray-100 flex items-center justify-center mb-3">
                        <svg class="w-6 h-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <p class="text-sm font-medium text-gray-700">Sin actividad reciente</p>
                    <p class="text-xs text-gray-400 mt-1">Aquí verás los últimos eventos del sistema</p>
                </div>
            </div>

        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, defineComponent, h } from 'vue'
import { RouterLink } from 'vue-router'
import { useAuthStore } from '../stores/auth.js'
import { useApi } from '../composables/useApi.js'

const auth = useAuthStore()
const api = useApi()

const loadingStats = ref(true)
const loadingWOs = ref(true)
const loadingActivity = ref(false)
const myWorkOrders = ref([])

const statsData = ref({ openWOs: 0, pendingMRs: 0, criticalAlerts: 0, offlineEquipment: 0 })

const firstName = computed(() => (auth.userName ?? '').split(' ')[0] || 'Usuario')

const formattedDate = computed(() => {
    return new Date().toLocaleDateString('es-CO', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })
})

const stats = computed(() => [
    {
        label: 'OTs abiertas',
        value: statsData.value.openWOs,
        color: 'text-blue-600',
        bg: 'bg-blue-50',
        icon: `<path stroke-linecap="round" stroke-linejoin="round" d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>`,
        to: 'ops.ordenes',
    },
    {
        label: 'Solicitudes pendientes',
        value: statsData.value.pendingMRs,
        color: 'text-amber-600',
        bg: 'bg-amber-50',
        icon: `<path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="2" ry="2"/>`,
        to: 'ops.solicitudes',
    },
    {
        label: 'Alertas críticas',
        value: statsData.value.criticalAlerts,
        color: 'text-red-600',
        bg: 'bg-red-50',
        icon: `<path stroke-linecap="round" stroke-linejoin="round" d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path stroke-linecap="round" stroke-linejoin="round" d="M13.73 21a2 2 0 0 1-3.46 0"/>`,
        to: 'ops.alertas',
    },
    {
        label: 'Equipos f. servicio',
        value: statsData.value.offlineEquipment,
        color: 'text-slate-600',
        bg: 'bg-slate-100',
        icon: `<path stroke-linecap="round" stroke-linejoin="round" d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>`,
        to: 'ops.equipos',
    },
])

// Inline sub-components
const StatCard = defineComponent({
    props: { stat: Object, loading: Boolean },
    setup(props) {
        return () => h(RouterLink, { to: { name: props.stat.to }, class: 'bg-white rounded-2xl border border-gray-100 shadow-sm p-4 flex flex-col gap-3 hover:shadow-md transition-shadow' }, () => [
            h('div', { class: `inline-flex items-center justify-center w-9 h-9 rounded-xl ${props.stat.bg}` }, [
                h('svg', { class: `w-4 h-4 ${props.stat.color}`, fill: 'none', viewBox: '0 0 24 24', stroke: 'currentColor', 'stroke-width': '2', 'stroke-linecap': 'round', 'stroke-linejoin': 'round', innerHTML: props.stat.icon }),
            ]),
            h('div', {}, [
                props.loading
                    ? h('div', { class: 'skeleton h-7 w-12 rounded mb-1' })
                    : h('p', { class: `text-2xl font-bold ${props.stat.color}` }, props.stat.value),
                h('p', { class: 'text-xs text-gray-500 font-medium leading-tight' }, props.stat.label),
            ]),
        ])
    },
})

const WorkOrderRow = defineComponent({
    props: { wo: Object },
    setup(props) {
        const priorityColors = { p1_critical: 'bg-red-100 text-red-700', p2_high: 'bg-orange-100 text-orange-700', p3_medium: 'bg-yellow-100 text-yellow-700', p4_low: 'bg-gray-100 text-gray-600' }
        const statusColors = { open: 'bg-blue-100 text-blue-700', in_progress: 'bg-indigo-100 text-indigo-700', completed: 'bg-emerald-100 text-emerald-700' }
        return () => h('div', { class: 'px-5 py-3.5 flex items-start gap-3 hover:bg-gray-50 transition-colors cursor-pointer' }, [
            h('div', { class: 'w-8 h-8 rounded-lg bg-indigo-100 flex items-center justify-center shrink-0 mt-0.5' }, [
                h('svg', { class: 'w-4 h-4 text-indigo-600', fill: 'none', viewBox: '0 0 24 24', stroke: 'currentColor', 'stroke-width': '2', innerHTML: `<path stroke-linecap="round" stroke-linejoin="round" d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>` }),
            ]),
            h('div', { class: 'flex-1 min-w-0' }, [
                h('p', { class: 'text-sm font-medium text-gray-900 truncate' }, props.wo.title),
                h('p', { class: 'text-xs text-gray-400 mt-0.5' }, `${props.wo.work_order_number} · ${props.wo.equipment?.code ?? '—'}`),
            ]),
            h('span', { class: `text-[10px] font-semibold px-2 py-0.5 rounded-full ${priorityColors[props.wo.priority] ?? 'bg-gray-100 text-gray-600'}` }, props.wo.priority?.toUpperCase().replace('_', ' ')),
        ])
    },
})

onMounted(async () => {
    try {
        const [woRes, mrRes] = await Promise.allSettled([
            api.get('work-orders?filter[status]=open,in_progress&per_page=100'),
            api.get('maintenance-requests?filter[status]=pending,under_review&per_page=100'),
        ])
        if (woRes.status === 'fulfilled') {
            statsData.value.openWOs = woRes.value?.meta?.total ?? woRes.value?.data?.length ?? 0
            myWorkOrders.value = (woRes.value?.data ?? []).slice(0, 5)
        }
        if (mrRes.status === 'fulfilled') {
            statsData.value.pendingMRs = mrRes.value?.meta?.total ?? mrRes.value?.data?.length ?? 0
        }
    } catch { /* silent */ } finally {
        loadingStats.value = false
        loadingWOs.value = false
    }
})
</script>
