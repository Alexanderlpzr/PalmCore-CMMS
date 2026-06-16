<template>
    <div class="p-5 lg:p-8 max-w-6xl mx-auto">

        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-xl font-bold text-gray-900">Equipos</h1>
                <p v-if="!loading" class="text-sm text-gray-500 mt-0.5">{{ equipment.length }} equipos</p>
            </div>
            <button
                @click="createInFilament"
                class="flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-4 py-2 rounded-xl transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                Nuevo equipo
            </button>
        </div>

        <!-- Search -->
        <div class="relative mb-5">
            <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
            <input
                v-model="search"
                type="text"
                placeholder="Buscar por código, nombre o número de serie..."
                class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent bg-white"
            />
        </div>

        <!-- Status filter tabs -->
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
            <SavedViews view="equipment" :current="{ filter: activeFilter, search }" @apply="applySavedView" />
            <button @click="resetPrefs" class="shrink-0 text-xs text-gray-400 hover:text-gray-600 transition-colors whitespace-nowrap" title="Restablecer preferencias de esta vista">
                Restablecer
            </button>
        </div>

        <!-- Grid skeleton -->
        <div v-if="loading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div v-for="i in 6" :key="i" class="bg-white rounded-2xl border border-gray-100 p-4 space-y-3">
                <div class="skeleton h-4 w-1/3 rounded" />
                <div class="skeleton h-5 w-2/3 rounded" />
                <div class="flex gap-2">
                    <div class="skeleton h-5 w-16 rounded-full" />
                    <div class="skeleton h-5 w-12 rounded-full" />
                </div>
                <div class="skeleton h-3 w-1/2 rounded" />
            </div>
        </div>

        <!-- Equipment cards grid -->
        <template v-else-if="equipment.length">
            <!-- Select all -->
            <label class="flex items-center gap-2 mb-3 px-1 text-xs text-gray-500 cursor-pointer w-fit">
                <input type="checkbox" :checked="allSelected" @change="toggleAll" class="w-4 h-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500" />
                Seleccionar todo
            </label>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div v-for="eq in equipment" :key="eq.id" class="relative">
                    <input
                        type="checkbox"
                        :checked="sel.has(eq.id)"
                        @change="sel.toggle(eq.id)"
                        class="absolute -top-2 -left-2 z-10 w-5 h-5 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500 cursor-pointer bg-white shadow-sm"
                    />
                    <RouterLink
                        :to="{ name: 'ops.equipos.show', params: { id: eq.id } }"
                        class="block"
                    >
                        <EquipmentCard :equipment="eq" />
                    </RouterLink>
                </div>
            </div>

            <!-- Load more -->
            <button
                v-if="nextCursor"
                @click="loadMore"
                :disabled="loadingMore"
                class="w-full mt-4 py-3 text-sm text-gray-500 hover:text-gray-700 font-medium transition-colors"
            >
                {{ loadingMore ? 'Cargando…' : 'Cargar más' }}
            </button>
        </template>

        <!-- Empty state -->
        <EmptyState
            v-else
            icon="cube"
            title="Sin equipos encontrados"
            subtitle="Intenta ajustar los filtros o el término de búsqueda."
        />

        <div v-if="sel.count.value" class="h-20" />
        <BulkActionBar :count="sel.count.value" :actions="bulkActions" @apply="applyBulk" @clear="sel.clear" />
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch, defineComponent, h } from 'vue'
import { RouterLink } from 'vue-router'
import { useApi } from '../composables/useApi.js'
import { useAuthStore } from '../stores/auth.js'
import { useToast } from '../composables/useToast.js'
import { useBulkSelection } from '../composables/useBulkSelection.js'
import { useViewPreferences } from '../composables/useViewPreferences.js'
import { describe, EQUIPMENT_STATUS, CRITICALITY, PRIORITY } from '../../shared/design.js'
import AppIcon from '../components/AppIcon.vue'
import Badge from '../components/Badge.vue'
import EmptyState from '../components/EmptyState.vue'
import BulkActionBar from '../components/BulkActionBar.vue'
import FavoriteStar from '../components/FavoriteStar.vue'
import SavedViews from '../components/SavedViews.vue'

const api = useApi()
const auth = useAuthStore()
const toast = useToast()
const sel = useBulkSelection()

function createInFilament() {
    window.location.href = `/admin/${auth.tenantSlug}/equipment/create`
}

const bulkActions = [
    { key: 'set_status', label: 'Estado', options: [
        { value: 'active', label: 'Activo' },
        { value: 'inactive', label: 'Inactivo' },
        { value: 'under_maintenance', label: 'En Mantenimiento' },
        { value: 'retired', label: 'Retirado' },
    ] },
    { key: 'set_criticality', label: 'Criticidad', options: [
        { value: 'critical', label: 'Crítico' },
        { value: 'high', label: 'Alto' },
        { value: 'medium', label: 'Medio' },
        { value: 'low', label: 'Bajo' },
    ] },
]

const equipment = ref([])
const loading = ref(true)
const loadingMore = ref(false)
const nextCursor = ref(null)
const { filter: activeFilter, search, reset: resetPrefs } = useViewPreferences('equipment', { filter: 'all', search: '' })

const filters = [
    { label: 'Todos', value: 'all' },
    { label: 'Activos', value: 'active' },
    { label: 'En mantenimiento', value: 'under_maintenance' },
    { label: 'Inactivos', value: 'inactive' },
]

const EquipmentCard = defineComponent({
    props: { equipment: Object },
    setup(props) {
        const status = computed(() => describe(EQUIPMENT_STATUS, props.equipment.status))
        const crit = computed(() => props.equipment.criticality ? describe(CRITICALITY, props.equipment.criticality) : null)
        const prio = computed(() => props.equipment.priority ? describe(PRIORITY, props.equipment.priority) : null)

        return () => h('div', {
            class: 'bg-white rounded-2xl border border-gray-100 shadow-sm hover:shadow-md hover:border-gray-200 transition-all cursor-pointer p-4 flex flex-col gap-3',
        }, [
            // Header row: code + status badge + favorite star
            h('div', { class: 'flex items-start justify-between gap-2' }, [
                h('span', { class: 'text-xs font-mono font-bold text-gray-500 tracking-widest' }, props.equipment.code),
                h('div', { class: 'flex items-center gap-1 shrink-0' }, [
                    h(Badge, { tone: status.value.tone, label: status.value.label }),
                    h(FavoriteStar, { type: 'equipment', id: props.equipment.id, size: 'w-4 h-4' }),
                ]),
            ]),

            // Name
            h('p', { class: 'text-sm font-semibold text-gray-900 leading-snug' }, props.equipment.name),

            // Criticality + priority
            h('div', { class: 'flex gap-1.5 flex-wrap' }, [
                crit.value ? h(Badge, { tone: crit.value.tone, label: crit.value.label }) : null,
                prio.value ? h(Badge, { tone: prio.value.tone, label: prio.value.label }) : null,
            ]),

            // Location
            h('p', { class: 'text-xs text-gray-500 flex items-center gap-1' }, [
                h(AppIcon, { name: 'mapPin', class: 'w-3 h-3 shrink-0' }),
                [props.equipment.plant?.name, props.equipment.area?.name].filter(Boolean).join(' — ') || '—',
            ]),
        ])
    },
})

function buildUrl(cursor = null) {
    const params = new URLSearchParams({ per_page: '30', include: 'plant,area' })
    if (activeFilter.value !== 'all') { params.set('status', activeFilter.value) }
    const q = search.value.trim()
    if (q) { params.set('search', q) }
    if (cursor) { params.set('cursor', cursor) }
    return `equipment?${params.toString()}`
}

async function load() {
    loading.value = true
    nextCursor.value = null
    try {
        const res = await api.get(buildUrl())
        equipment.value = res?.data ?? []
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
        equipment.value = [...equipment.value, ...(res?.data ?? [])]
        nextCursor.value = res?.meta?.next_cursor ?? null
    } catch { /* silent */ } finally {
        loadingMore.value = false
    }
}

function applySavedView(state) {
    activeFilter.value = state.filter ?? 'all'
    search.value = state.search ?? ''
}

const allSelected = computed(() => equipment.value.length > 0 && equipment.value.every((e) => sel.has(e.id)))
function toggleAll() {
    sel.setMany(equipment.value.map((e) => e.id), ! allSelected.value)
}

async function applyBulk({ action, value }) {
    const ids = sel.ids()
    try {
        const res = await api.patch('equipment/bulk', { ids, action, value })
        const ok = res?.succeeded ?? 0
        const failed = res?.failed?.length ?? 0
        failed
            ? toast.warning(`${ok} equipos actualizados. ${failed} no pudieron modificarse.`)
            : toast.success(`${ok} equipos actualizados.`)
        sel.clear()
        await load()
    } catch {
        toast.error('No se pudo aplicar la acción.')
    }
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
