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

        <!-- Equipment context banner -->
        <div v-if="equipmentId" class="bg-indigo-50 border border-indigo-100 rounded-2xl px-4 py-3 flex items-center justify-between gap-4 mb-4">
            <p class="text-xs font-semibold text-indigo-700">
                Mostrando solo órdenes de trabajo de este equipo
            </p>
            <RouterLink :to="{ name: 'ops.equipos.show', params: { id: equipmentId } }"
                class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 transition-colors shrink-0">
                ← Volver al equipo
            </RouterLink>
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
        <div class="flex items-center gap-2 mb-5">
            <div class="flex gap-1.5 overflow-x-auto pb-1 flex-1">
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
            <SavedViews view="workorders" :current="{ filter: activeFilter, search }" @apply="applySavedView" />
            <button @click="resetPrefs" class="shrink-0 text-xs text-gray-400 hover:text-gray-600 transition-colors whitespace-nowrap" title="Restablecer preferencias de esta vista">
                Restablecer
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
            <!-- Select all -->
            <label class="flex items-center gap-2 mb-2 px-1 text-xs text-gray-500 cursor-pointer w-fit">
                <input type="checkbox" :checked="allSelected" @change="toggleAll" class="w-4 h-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500" />
                Seleccionar todo
            </label>

            <div v-for="wo in workOrders" :key="wo.id" class="flex items-center gap-2.5">
                <input
                    type="checkbox"
                    :checked="sel.has(wo.id)"
                    @change="sel.toggle(wo.id)"
                    class="w-4 h-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500 shrink-0 cursor-pointer"
                />
                <RouterLink
                    :to="{ name: 'ops.ordenes.show', params: { id: wo.id } }"
                    class="flex-1 min-w-0 block bg-white rounded-2xl border border-gray-100 shadow-sm hover:shadow-md hover:border-gray-200 transition-all"
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
                <FavoriteStar type="workorders" :id="wo.id" />
            </div>

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

        <!-- Spacer so the floating bar never hides the last row -->
        <div v-if="sel.count.value" class="h-20" />

        <BulkActionBar :count="sel.count.value" :actions="bulkActions" @apply="applyBulk" @clear="sel.clear" />
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { useApi } from '../composables/useApi.js'
import { useAuthStore } from '../stores/auth.js'
import { useToast } from '../composables/useToast.js'
import { useBulkSelection } from '../composables/useBulkSelection.js'
import { useViewPreferences } from '../composables/useViewPreferences.js'
import { describe, toneIcon, WORK_ORDER_STATUS, PRIORITY } from '../../shared/design.js'
import AppIcon from '../components/AppIcon.vue'
import Badge from '../components/Badge.vue'
import EmptyState from '../components/EmptyState.vue'
import BulkActionBar from '../components/BulkActionBar.vue'
import FavoriteStar from '../components/FavoriteStar.vue'
import SavedViews from '../components/SavedViews.vue'

const api = useApi()
const auth = useAuthStore()
const toast = useToast()
const route = useRoute()
const sel = useBulkSelection()
const workOrders = ref([])
const loading = ref(true)
const loadingMore = ref(false)
const nextCursor = ref(null)
const { filter: activeFilter, search, reset: resetPrefs } = useViewPreferences('workorders', { filter: 'planned,in_progress,on_hold', search: '' })

// Equipment context filter — set from route query when arriving from equipment ficha
const equipmentId = ref(route.query.equipment_id ?? null)

const bulkActions = [
    { key: 'close', label: 'Cerrar' },
    { key: 'cancel', label: 'Cancelar', danger: true },
    { key: 'set_priority', label: 'Prioridad', options: [
        { value: 'p1_critical', label: 'Crítica' },
        { value: 'p2_high', label: 'Alta' },
        { value: 'p3_medium', label: 'Media' },
        { value: 'p4_low', label: 'Baja' },
    ] },
]

function applySavedView(state) {
    activeFilter.value = state.filter ?? 'planned,in_progress,on_hold'
    search.value = state.search ?? ''
}

const allSelected = computed(() => workOrders.value.length > 0 && workOrders.value.every((w) => sel.has(w.id)))
function toggleAll() {
    sel.setMany(workOrders.value.map((w) => w.id), ! allSelected.value)
}

async function applyBulk({ action, value }) {
    const ids = sel.ids()
    try {
        const res = await api.patch('work-orders/bulk', { ids, action, ...(value != null ? { value } : {}) })
        const ok = res?.succeeded ?? 0
        const failed = res?.failed?.length ?? 0
        failed
            ? toast.warning(`${ok} órdenes actualizadas. ${failed} no pudieron modificarse.`)
            : toast.success(`${ok} órdenes actualizadas.`)
        sel.clear()
        await load()
    } catch {
        toast.error('No se pudo aplicar la acción.')
    }
}

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
    if (equipmentId.value) { params.set('equipment_id', equipmentId.value) }
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
