<template>
    <div class="p-5 lg:p-8 max-w-5xl mx-auto">

        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-xl font-bold text-gray-900">Órdenes de trabajo</h1>
                <p v-if="!loading" class="text-sm text-gray-500 mt-0.5">{{ workOrders.length }} órdenes</p>
            </div>
            <button
                @click="createInFilament"
                class="flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-4 py-2 rounded-xl transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                Nueva OT
            </button>
        </div>

        <!-- Search -->
        <div class="relative mb-4">
            <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
            <input
                v-model="search"
                type="text"
                placeholder="Buscar por número, título o descripción..."
                class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent bg-white"
            />
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
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 mt-0.5" :class="toneIcon(priority(wo.priority).tone)">
                        <AppIcon name="wrench" class="w-5 h-5" />
                    </div>

                    <!-- Content -->
                    <div class="flex-1 min-w-0">
                        <div class="flex items-start justify-between gap-2">
                            <p class="text-sm font-semibold text-gray-900 truncate">{{ wo.title }}</p>
                            <Badge :tone="status(wo.status).tone" :label="status(wo.status).label" class="shrink-0" />
                        </div>
                        <p class="text-xs text-gray-500 mt-1 flex items-center gap-2">
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

            <!-- Load more -->
            <button
                v-if="nextCursor"
                @click="loadMore"
                :disabled="loadingMore"
                class="w-full py-3 text-sm text-gray-500 hover:text-gray-700 font-medium transition-colors"
            >
                {{ loadingMore ? 'Cargando…' : 'Cargar más' }}
            </button>
        </div>

        <!-- Empty -->
        <EmptyState
            v-else
            icon="wrench"
            title="Sin órdenes de trabajo"
            subtitle="No hay órdenes de trabajo con los filtros seleccionados."
        />

    </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { useApi } from '../composables/useApi.js'
import { useAuthStore } from '../stores/auth.js'
import { describe, toneIcon, WORK_ORDER_STATUS, PRIORITY } from '../../shared/design.js'
import AppIcon from '../components/AppIcon.vue'
import Badge from '../components/Badge.vue'
import EmptyState from '../components/EmptyState.vue'

const api = useApi()
const auth = useAuthStore()
const workOrders = ref([])
const loading = ref(true)
const loadingMore = ref(false)
const nextCursor = ref(null)
const search = ref('')
const activeFilter = ref('planned,in_progress,on_hold')

const filters = [
    { label: 'Activas', value: 'planned,in_progress,on_hold' },
    { label: 'En ejecución', value: 'in_progress' },
    { label: 'Completadas', value: 'completed,verified,closed' },
    { label: 'Todas', value: '' },
]

const status = (s) => describe(WORK_ORDER_STATUS, s)
const priority = (p) => describe(PRIORITY, p)

function relativeTime(dateStr) {
    const diff = Date.now() - new Date(dateStr).getTime()
    const h = Math.floor(diff / 36e5)
    if (h < 1) return 'hace menos de 1h'
    if (h < 24) return `hace ${h}h`
    const d = Math.floor(h / 24)
    return `hace ${d}d`
}

function buildUrl(cursor = null) {
    const params = new URLSearchParams({ per_page: '25' })
    if (activeFilter.value) { params.set('status', activeFilter.value) }
    const q = search.value.trim()
    if (q) { params.set('search', q) }
    if (cursor) { params.set('cursor', cursor) }
    return `work-orders?${params.toString()}`
}

async function load() {
    loading.value = true
    nextCursor.value = null
    try {
        const res = await api.get(buildUrl())
        workOrders.value = res?.data ?? []
        nextCursor.value = res?.meta?.next_cursor ?? null
    } catch { /* silent */ } finally {
        loading.value = false
    }
}

async function loadMore() {
    if (!nextCursor.value || loadingMore.value) { return }
    loadingMore.value = true
    try {
        const res = await api.get(buildUrl(nextCursor.value))
        workOrders.value = [...workOrders.value, ...(res?.data ?? [])]
        nextCursor.value = res?.meta?.next_cursor ?? null
    } catch { /* silent */ } finally {
        loadingMore.value = false
    }
}

function createInFilament() {
    window.location.href = `/admin/${auth.tenantSlug}/work-orders/create`
}

// Server-side search with debounce so each keystroke does not fire a request.
let searchTimer = null
watch(search, () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(load, 350)
})
watch(activeFilter, load)
onMounted(load)
</script>
