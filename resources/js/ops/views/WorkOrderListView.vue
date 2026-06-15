<template>
    <div class="p-5 lg:p-8 max-w-5xl mx-auto">

        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-xl font-bold text-gray-900">Órdenes de trabajo</h1>
                <p v-if="!loading" class="text-sm text-gray-400 mt-0.5">{{ total }} órdenes encontradas</p>
            </div>
            <button class="flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-4 py-2 rounded-xl transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                Nueva OT
            </button>
        </div>

        <!-- Status tabs -->
        <div class="flex gap-1.5 mb-5 overflow-x-auto pb-1">
            <button
                v-for="f in filters"
                :key="f.value"
                @click="activeFilter = f.value"
                class="shrink-0 px-3.5 py-1.5 rounded-full text-xs font-semibold transition-colors"
                :class="activeFilter === f.value
                    ? 'bg-slate-900 text-white'
                    : 'bg-white border border-gray-200 text-gray-600 hover:border-gray-300'"
            >
                {{ f.label }}
            </button>
        </div>

        <!-- Skeleton -->
        <div v-if="loading" class="space-y-3">
            <div v-for="i in 5" :key="i" class="bg-white rounded-2xl border border-gray-100 p-4 flex items-center gap-4">
                <div class="skeleton w-10 h-10 rounded-xl shrink-0" />
                <div class="flex-1 space-y-2">
                    <div class="skeleton h-4 w-2/3 rounded" />
                    <div class="skeleton h-3 w-1/3 rounded" />
                </div>
                <div class="skeleton h-5 w-14 rounded-full" />
            </div>
        </div>

        <!-- Work order list -->
        <div v-else-if="workOrders.length" class="space-y-2">
            <RouterLink
                v-for="wo in workOrders"
                :key="wo.id"
                :to="{ name: 'ops.ordenes.show', params: { id: wo.id } }"
                class="block bg-white rounded-2xl border border-gray-100 shadow-sm hover:shadow-md hover:border-gray-200 transition-all"
            >
                <div class="flex items-start gap-4 p-4">
                    <!-- Priority indicator -->
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 mt-0.5" :class="priorityBg[wo.priority] ?? 'bg-gray-100'">
                        <svg class="w-5 h-5" :class="priorityColor[wo.priority] ?? 'text-gray-500'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
                        </svg>
                    </div>

                    <!-- Content -->
                    <div class="flex-1 min-w-0">
                        <div class="flex items-start justify-between gap-2">
                            <p class="text-sm font-semibold text-gray-900 truncate">{{ wo.title }}</p>
                            <span class="text-[10px] font-bold shrink-0 px-2 py-0.5 rounded-full" :class="statusColors[wo.status] ?? 'bg-gray-100 text-gray-600'">
                                {{ statusLabels[wo.status] ?? wo.status }}
                            </span>
                        </div>
                        <p class="text-xs text-gray-400 mt-1 flex items-center gap-2">
                            <span class="font-mono">{{ wo.work_order_number }}</span>
                            <span v-if="wo.equipment?.code" class="flex items-center gap-1">
                                <span class="text-gray-200">·</span>
                                {{ wo.equipment.code }}
                            </span>
                            <span v-if="wo.created_at" class="flex items-center gap-1">
                                <span class="text-gray-200">·</span>
                                {{ relativeTime(wo.created_at) }}
                            </span>
                        </p>
                    </div>
                </div>
            </RouterLink>

            <!-- Pagination hint -->
            <p v-if="total > workOrders.length" class="text-center text-xs text-gray-400 py-4">
                Mostrando {{ workOrders.length }} de {{ total }} — implementar paginación en siguiente sprint
            </p>
        </div>

        <!-- Empty -->
        <div v-else class="flex flex-col items-center justify-center py-20 text-center">
            <div class="w-16 h-16 rounded-2xl bg-gray-100 flex items-center justify-center mb-4">
                <svg class="w-8 h-8 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
                </svg>
            </div>
            <p class="text-sm font-medium text-gray-700">Sin órdenes de trabajo</p>
            <p class="text-xs text-gray-400 mt-1">Crea la primera orden de trabajo</p>
        </div>

    </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { useApi } from '../composables/useApi.js'

const api = useApi()
const workOrders = ref([])
const loading = ref(true)
const total = ref(0)
const activeFilter = ref('open')

const filters = [
    { label: 'Abiertas', value: 'open' },
    { label: 'En progreso', value: 'in_progress' },
    { label: 'Completadas', value: 'completed' },
    { label: 'Todas', value: '' },
]

const priorityBg = { p1_critical: 'bg-red-100', p2_high: 'bg-orange-100', p3_medium: 'bg-yellow-100', p4_low: 'bg-gray-100' }
const priorityColor = { p1_critical: 'text-red-600', p2_high: 'text-orange-600', p3_medium: 'text-yellow-600', p4_low: 'text-gray-500' }

const statusColors = {
    open:        'bg-blue-100 text-blue-700',
    in_progress: 'bg-indigo-100 text-indigo-700',
    completed:   'bg-emerald-100 text-emerald-700',
    closed:      'bg-gray-100 text-gray-600',
    cancelled:   'bg-red-100 text-red-600',
}
const statusLabels = {
    open: 'Abierta', in_progress: 'En progreso', completed: 'Completada', closed: 'Cerrada', cancelled: 'Cancelada',
}

function relativeTime(dateStr) {
    const diff = Date.now() - new Date(dateStr).getTime()
    const h = Math.floor(diff / 36e5)
    if (h < 1) return 'hace menos de 1h'
    if (h < 24) return `hace ${h}h`
    const d = Math.floor(h / 24)
    return `hace ${d}d`
}

async function load() {
    loading.value = true
    try {
        const statusParam = activeFilter.value ? `&filter[status]=${activeFilter.value}` : ''
        const res = await api.get(`work-orders?per_page=50&include=equipment${statusParam}`)
        workOrders.value = res?.data ?? []
        total.value = res?.meta?.total ?? workOrders.value.length
    } catch { /* silent */ } finally {
        loading.value = false
    }
}

watch(activeFilter, load)
onMounted(load)
</script>
