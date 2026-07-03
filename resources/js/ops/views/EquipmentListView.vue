<template>
    <div class="p-5 lg:p-8 max-w-6xl mx-auto">

        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-xl font-bold text-gray-900">Equipos</h1>
                <p v-if="!loading" class="text-sm text-gray-500 mt-0.5">{{ equipment.length }} {{ equipment.length === 1 ? 'equipo' : 'equipos' }}</p>
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

        <!-- Fleet intelligence bar -->
        <div v-if="!loading && equipment.length" class="grid grid-cols-2 sm:grid-cols-5 gap-2.5 mb-5">
            <div class="bg-white rounded-xl border border-gray-100 px-3.5 py-2.5">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 leading-none mb-1">Cargados</p>
                <p class="text-lg font-bold text-gray-900 leading-none">{{ fleetStats.total }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 px-3.5 py-2.5">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-red-500 leading-none mb-1">Críticos</p>
                <p class="text-lg font-bold text-gray-900 leading-none">{{ fleetStats.critical }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 px-3.5 py-2.5">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-blue-500 leading-none mb-1">Con OTs activas</p>
                <p class="text-lg font-bold text-gray-900 leading-none">{{ fleetStats.withActiveWo }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 px-3.5 py-2.5">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-amber-500 leading-none mb-1">Prev. vencidos</p>
                <p class="text-lg font-bold text-gray-900 leading-none">{{ fleetStats.withOverduePrev }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 px-3.5 py-2.5">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-emerald-500 leading-none mb-1">Disp. promedio</p>
                <p class="text-lg font-bold text-gray-900 leading-none">{{ fleetStats.avgAvailability != null ? fleetStats.avgAvailability.toFixed(1) + '%' : '—' }}</p>
            </div>
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

        <!-- Sort + view mode -->
        <div class="flex items-center justify-between gap-2 mb-5">
            <div class="flex items-center gap-1.5">
                <span class="text-xs text-gray-400 font-medium shrink-0">Ordenar:</span>
                <select v-model="sortKey" class="border border-gray-200 rounded-xl text-xs text-gray-700 px-3 py-1.5 focus:ring-2 focus:ring-emerald-500 focus:border-transparent bg-white">
                    <option value="code">Código A–Z</option>
                    <option value="criticality_desc">Criticidad (mayor primero)</option>
                    <option value="risk">Mayor riesgo</option>
                    <option value="active_wos">Más OTs activas</option>
                    <option value="availability_asc">Menor disponibilidad</option>
                </select>
                <span v-if="sortKey !== 'code'" class="text-[11px] text-gray-400" title="Ordenado solo entre los equipos ya cargados">
                    (entre cargados)
                </span>
            </div>
            <div class="flex rounded-xl border border-gray-200 overflow-hidden shrink-0">
                <button
                    @click="viewMode = 'grid'"
                    class="px-2.5 py-1.5 transition-colors"
                    :class="viewMode === 'grid' ? 'bg-slate-900 text-white' : 'bg-white text-gray-400 hover:text-gray-600'"
                    aria-label="Vista de cuadrícula"
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                </button>
                <button
                    @click="viewMode = 'list'"
                    class="px-2.5 py-1.5 transition-colors border-l border-gray-200"
                    :class="viewMode === 'list' ? 'bg-slate-900 text-white' : 'bg-white text-gray-400 hover:text-gray-600'"
                    aria-label="Vista de lista compacta"
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><line x1="4" y1="6" x2="20" y2="6"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="18" x2="20" y2="18"/></svg>
                </button>
            </div>
        </div>

        <!-- Additional filter row -->
        <div class="flex flex-wrap items-center gap-2 mb-5">
            <!-- Planta select -->
            <select v-model="selectedPlant" @change="selectedArea = ''" class="border border-gray-200 rounded-xl text-xs text-gray-700 px-3 py-1.5 focus:ring-2 focus:ring-emerald-500 focus:border-transparent bg-white">
                <option value="">Todas las plantas</option>
                <option v-for="p in plants" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>

            <!-- Área select -->
            <select v-model="selectedArea" class="border border-gray-200 rounded-xl text-xs text-gray-700 px-3 py-1.5 focus:ring-2 focus:ring-emerald-500 focus:border-transparent bg-white" :disabled="!selectedPlant && filteredAreas.length === 0">
                <option value="">Todas las áreas</option>
                <option v-for="a in filteredAreas" :key="a.id" :value="a.id">{{ a.name }}</option>
            </select>

            <!-- Criticidad select -->
            <select v-model="selectedCriticality" class="border border-gray-200 rounded-xl text-xs text-gray-700 px-3 py-1.5 focus:ring-2 focus:ring-emerald-500 focus:border-transparent bg-white">
                <option value="">Todas las criticidades</option>
                <option value="critical">Crítico</option>
                <option value="high">Alto</option>
                <option value="medium">Medio</option>
                <option value="low">Bajo</option>
            </select>

            <!-- Smart filter checkboxes -->
            <label class="flex items-center gap-1.5 text-xs text-gray-600 cursor-pointer">
                <input type="checkbox" v-model="filterHasActiveWO" class="w-4 h-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500" />
                Con OTs activas
            </label>
            <label class="flex items-center gap-1.5 text-xs text-gray-600 cursor-pointer">
                <input type="checkbox" v-model="filterHasOverduePrev" class="w-4 h-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500" />
                Preventivos vencidos
            </label>

            <!-- Clear filters button -->
            <button v-if="hasActiveFilters" @click="clearFilters" class="text-xs text-red-500 hover:text-red-700 transition-colors">
                Limpiar filtros
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

            <div v-if="viewMode === 'grid'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div v-for="eq in sortedEquipment" :key="eq.id" class="relative">
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

            <div v-else class="space-y-2">
                <div v-for="eq in sortedEquipment" :key="eq.id" class="relative">
                    <input
                        type="checkbox"
                        :checked="sel.has(eq.id)"
                        @change="sel.toggle(eq.id)"
                        class="absolute top-1/2 -translate-y-1/2 left-2 z-10 w-4 h-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500 cursor-pointer bg-white shadow-sm"
                    />
                    <RouterLink
                        :to="{ name: 'ops.equipos.show', params: { id: eq.id } }"
                        class="block pl-7"
                    >
                        <EquipmentRow :equipment="eq" />
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
            :subtitle="emptySubtitle"
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

const plants = ref([])
const areas = ref([])
const selectedPlant = ref('')
const selectedArea = ref('')
const selectedCriticality = ref('')
const filterHasActiveWO = ref(false)
const filterHasOverduePrev = ref(false)
const sortKey = ref('code')
const viewMode = ref('grid')

const CRITICALITY_WEIGHT = { critical: 4, high: 3, medium: 2, low: 1 }

function riskScore(eq) {
    return (CRITICALITY_WEIGHT[eq.criticality] ?? 0)
        + (eq.active_work_orders_count ?? 0)
        + (eq.has_overdue_preventives ? 2 : 0)
}

const fleetStats = computed(() => {
    const list = equipment.value
    const availabilities = list
        .map((e) => e.kpi?.availability_percentage)
        .filter((v) => v != null)
        .map(Number)

    return {
        total: list.length,
        critical: list.filter((e) => e.criticality === 'critical').length,
        withActiveWo: list.filter((e) => (e.active_work_orders_count ?? 0) > 0).length,
        withOverduePrev: list.filter((e) => e.has_overdue_preventives).length,
        avgAvailability: availabilities.length
            ? availabilities.reduce((a, b) => a + b, 0) / availabilities.length
            : null,
    }
})

const sortedEquipment = computed(() => {
    const list = equipment.value
    switch (sortKey.value) {
        case 'criticality_desc':
            // Criticality is stored as a string enum (critical/high/medium/low), so
            // alphabetical server-side sorting cannot express severity order — rank here instead.
            return [...list].sort((a, b) => (CRITICALITY_WEIGHT[b.criticality] ?? 0) - (CRITICALITY_WEIGHT[a.criticality] ?? 0))
        case 'risk':
            return [...list].sort((a, b) => riskScore(b) - riskScore(a))
        case 'active_wos':
            return [...list].sort((a, b) => (b.active_work_orders_count ?? 0) - (a.active_work_orders_count ?? 0))
        case 'availability_asc':
            return [...list].sort((a, b) => {
                const av = a.kpi?.availability_percentage
                const bv = b.kpi?.availability_percentage
                if (av == null && bv == null) return 0
                if (av == null) return 1
                if (bv == null) return -1
                return Number(av) - Number(bv)
            })
        default:
            // 'code' is already ordered server-side
            return list
    }
})

const filteredAreas = computed(() =>
    selectedPlant.value ? areas.value.filter(a => a.plant_id === selectedPlant.value) : areas.value
)

const hasActiveFilters = computed(() =>
    selectedPlant.value || selectedArea.value || selectedCriticality.value ||
    filterHasActiveWO.value || filterHasOverduePrev.value
)

const emptySubtitle = computed(() => {
    if (search.value.trim()) { return `Sin resultados para "${search.value.trim()}".` }
    if (hasActiveFilters.value) { return 'Ningún equipo coincide con los filtros activos.' }
    return 'Aún no hay equipos registrados.'
})

function clearFilters() {
    selectedPlant.value = ''
    selectedArea.value = ''
    selectedCriticality.value = ''
    filterHasActiveWO.value = false
    filterHasOverduePrev.value = false
}

async function loadPlants() {
    try {
        const res = await api.get('plants?per_page=100')
        plants.value = res?.data ?? []
    } catch { /* silent */ }
}

async function loadAreas() {
    try {
        const res = await api.get('areas?per_page=200')
        areas.value = res?.data ?? []
    } catch { /* silent */ }
}

const filters = [
    { label: 'Todos', value: 'all' },
    { label: 'Activos', value: 'active' },
    { label: 'En mantenimiento', value: 'under_maintenance' },
    { label: 'Inactivos', value: 'inactive' },
]

const CRITICALITY_BORDER = {
    critical: 'border-l-4 border-l-red-500',
    high: 'border-l-4 border-l-orange-400',
    medium: 'border-l-4 border-l-yellow-300',
    low: 'border-l-4 border-l-emerald-300',
}

function availabilityTone(pct) {
    if (pct == null) return null
    if (pct >= 95) return { classes: 'bg-emerald-100 text-emerald-700', bar: 'bg-emerald-500' }
    if (pct >= 85) return { classes: 'bg-amber-100 text-amber-700', bar: 'bg-amber-500' }
    return { classes: 'bg-red-100 text-red-700', bar: 'bg-red-500' }
}

const EquipmentCard = defineComponent({
    props: { equipment: Object },
    setup(props) {
        const status = computed(() => describe(EQUIPMENT_STATUS, props.equipment.status))
        const crit = computed(() => props.equipment.criticality ? describe(CRITICALITY, props.equipment.criticality) : null)
        const prio = computed(() => props.equipment.priority ? describe(PRIORITY, props.equipment.priority) : null)
        const availability = computed(() => {
            const pct = props.equipment.kpi?.availability_percentage
            return pct != null ? Number(pct) : null
        })
        const availTone = computed(() => availabilityTone(availability.value))
        const risk = computed(() => riskScore(props.equipment))
        const isHighRisk = computed(() => props.equipment.criticality === 'critical' && (props.equipment.active_work_orders_count > 0 || props.equipment.has_overdue_preventives))
        const isHighLoad = computed(() => (props.equipment.active_work_orders_count ?? 0) >= 3)

        return () => h('div', {
            class: [
                'bg-white rounded-2xl border border-gray-100 shadow-sm hover:shadow-md hover:border-gray-200 transition cursor-pointer p-4 flex flex-col gap-3',
                CRITICALITY_BORDER[props.equipment.criticality] ?? '',
            ],
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

            // Availability mini-bar
            availability.value != null
                ? h('div', { class: 'flex items-center gap-2' }, [
                    h('div', { class: 'flex-1 h-1.5 rounded-full bg-gray-100 overflow-hidden' }, [
                        h('div', { class: `h-full rounded-full ${availTone.value.bar}`, style: `width: ${Math.min(availability.value, 100)}%` }),
                    ]),
                    h('span', { class: `text-xs font-semibold shrink-0 px-1.5 py-0.5 rounded-full ${availTone.value.classes}` }, `${availability.value.toFixed(1)}% disp.`),
                  ])
                : null,

            // Operational + risk signals
            (props.equipment.active_work_orders_count > 0 || props.equipment.has_overdue_preventives || isHighRisk.value || isHighLoad.value)
                ? h('div', { class: 'flex items-center gap-1.5 flex-wrap' }, [
                    isHighRisk.value
                        ? h('span', {
                            class: 'inline-flex items-center gap-1 text-xs font-semibold px-1.5 py-0.5 rounded-full bg-red-600 text-white',
                            title: `Puntaje de riesgo: ${risk.value}`,
                          }, 'Alto riesgo')
                        : null,
                    props.equipment.active_work_orders_count > 0
                        ? h('span', {
                            class: 'inline-flex items-center gap-1 text-xs font-semibold px-1.5 py-0.5 rounded-full bg-blue-100 text-blue-700',
                          }, [
                            h('span', { class: 'w-1.5 h-1.5 rounded-full bg-blue-500 shrink-0' }),
                            `${props.equipment.active_work_orders_count} OT${props.equipment.active_work_orders_count !== 1 ? 's' : ''} activa${props.equipment.active_work_orders_count !== 1 ? 's' : ''}`,
                          ])
                        : null,
                    isHighLoad.value
                        ? h('span', {
                            class: 'inline-flex items-center gap-1 text-xs font-semibold px-1.5 py-0.5 rounded-full bg-purple-100 text-purple-700',
                          }, 'Alta carga')
                        : null,
                    props.equipment.has_overdue_preventives
                        ? h('span', {
                            class: 'inline-flex items-center gap-1 text-xs font-semibold px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-700',
                          }, 'Prev. vencido')
                        : null,
                  ])
                : null,
        ])
    },
})

const EquipmentRow = defineComponent({
    props: { equipment: Object },
    setup(props) {
        const status = computed(() => describe(EQUIPMENT_STATUS, props.equipment.status))
        const crit = computed(() => props.equipment.criticality ? describe(CRITICALITY, props.equipment.criticality) : null)
        const availability = computed(() => {
            const pct = props.equipment.kpi?.availability_percentage
            return pct != null ? Number(pct) : null
        })
        const availTone = computed(() => availabilityTone(availability.value))

        return () => h('div', {
            class: [
                'bg-white rounded-xl border border-gray-100 shadow-sm hover:shadow-md hover:border-gray-200 transition cursor-pointer px-4 py-2.5 flex items-center gap-3',
                CRITICALITY_BORDER[props.equipment.criticality] ?? '',
            ],
        }, [
            h('span', { class: 'text-xs font-mono font-bold text-gray-500 tracking-widest shrink-0 w-20 truncate' }, props.equipment.code),
            h('p', { class: 'text-sm font-semibold text-gray-900 truncate flex-1 min-w-0' }, props.equipment.name),
            h('p', { class: 'text-xs text-gray-500 truncate shrink-0 w-40 hidden sm:block' },
                [props.equipment.plant?.name, props.equipment.area?.name].filter(Boolean).join(' — ') || '—'),
            crit.value ? h(Badge, { tone: crit.value.tone, label: crit.value.label }) : null,
            h(Badge, { tone: status.value.tone, label: status.value.label }),
            availability.value != null
                ? h('span', { class: `text-xs font-semibold shrink-0 px-1.5 py-0.5 rounded-full ${availTone.value.classes}` }, `${availability.value.toFixed(1)}%`)
                : null,
            props.equipment.active_work_orders_count > 0
                ? h('span', { class: 'inline-flex items-center gap-1 text-xs font-semibold px-1.5 py-0.5 rounded-full bg-blue-100 text-blue-700 shrink-0' },
                    `${props.equipment.active_work_orders_count} OT${props.equipment.active_work_orders_count !== 1 ? 's' : ''}`)
                : null,
            props.equipment.has_overdue_preventives
                ? h('span', { class: 'inline-flex items-center text-xs font-semibold px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-700 shrink-0' }, 'Prev. vencido')
                : null,
        ])
    },
})

function buildUrl(cursor = null) {
    const params = new URLSearchParams({ per_page: '30', include: 'plant,area,kpi' })
    if (activeFilter.value !== 'all') { params.set('status', activeFilter.value) }
    if (selectedPlant.value) { params.set('plant_id', selectedPlant.value) }
    if (selectedArea.value) { params.set('area_id', selectedArea.value) }
    if (selectedCriticality.value) { params.set('criticality', selectedCriticality.value) }
    if (filterHasActiveWO.value) { params.set('has_active_work_orders', '1') }
    if (filterHasOverduePrev.value) { params.set('has_overdue_preventives', '1') }
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
watch([selectedPlant, selectedArea, selectedCriticality, filterHasActiveWO, filterHasOverduePrev], load)

onMounted(() => {
    loadPlants()
    loadAreas()
    load()
})
</script>
