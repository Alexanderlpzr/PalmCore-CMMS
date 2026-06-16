<template>
    <div class="p-5 lg:p-8 max-w-5xl mx-auto">

        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-xl font-bold text-gray-900">Repuestos</h1>
                <p v-if="!loading" class="text-sm text-gray-500 mt-0.5">{{ parts.length }} repuestos</p>
            </div>
            <button
                @click="downloadPdf"
                :disabled="downloadingPdf"
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-gray-200 text-xs font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-50 transition-colors"
            >
                <AppIcon name="fileText" class="w-3.5 h-3.5" />
                {{ downloadingPdf ? 'Generando…' : 'PDF inventario' }}
            </button>
        </div>

        <!-- Search + category filter -->
        <div class="flex flex-col sm:flex-row gap-3 mb-5">
            <div class="relative flex-1">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                </svg>
                <input
                    v-model="search"
                    type="text"
                    placeholder="Buscar por código o nombre..."
                    class="w-full pl-9 pr-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent bg-white"
                />
            </div>
        </div>

        <!-- Category filter pills -->
        <div class="flex items-center gap-2 mb-5">
            <div class="flex gap-1.5 overflow-x-auto pb-1 flex-1">
                <button
                    v-for="cat in categoryFilters"
                    :key="cat.value"
                    @click="activeCategory = cat.value"
                    class="shrink-0 px-3.5 py-1.5 rounded-full text-xs font-semibold transition-colors"
                    :class="activeCategory === cat.value
                        ? 'bg-slate-900 text-white'
                        : 'bg-white border border-gray-200 text-gray-600 hover:border-gray-300'"
                >
                    {{ cat.label }}
                </button>
            </div>
            <SavedViews view="spareparts" :current="{ category: activeCategory, search }" @apply="applySavedView" />
            <button @click="resetPrefs" class="shrink-0 text-xs text-gray-400 hover:text-gray-600 transition-colors whitespace-nowrap" title="Restablecer preferencias de esta vista">
                Restablecer
            </button>
        </div>

        <!-- Skeleton -->
        <div v-if="loading" class="space-y-2">
            <div v-for="i in 6" :key="i" class="bg-white rounded-2xl border border-gray-100 p-4 flex items-center gap-4">
                <div class="skeleton w-10 h-10 rounded-xl shrink-0" />
                <div class="flex-1 space-y-2">
                    <div class="skeleton h-4 w-1/2 rounded" />
                    <div class="skeleton h-3 w-1/3 rounded" />
                </div>
                <div class="skeleton h-5 w-12 rounded-full" />
            </div>
        </div>

        <!-- Spare part list -->
        <div v-else-if="parts.length" class="space-y-2">
            <div
                v-for="part in parts"
                :key="part.id"
                class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 flex items-start gap-4"
            >
                <!-- Category icon -->
                <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 mt-0.5" :class="categoryBg[part.category_type] ?? 'bg-gray-100'">
                    <span class="text-base">{{ categoryEmoji[part.category_type] ?? '🔧' }}</span>
                </div>

                <!-- Content -->
                <div class="flex-1 min-w-0">
                    <div class="flex items-start gap-2 flex-wrap mb-1">
                        <span class="font-mono text-xs bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded">{{ part.code }}</span>
                        <span v-if="part.abc_classification" :class="abcBadge[part.abc_classification]" class="text-xs font-bold px-1.5 py-0.5 rounded-full">
                            {{ part.abc_classification }}
                        </span>
                        <Badge v-if="part.criticality" :tone="crit(part.criticality).tone" :label="crit(part.criticality).label" />
                    </div>
                    <p class="text-sm font-semibold text-gray-900 leading-snug">{{ part.name }}</p>
                    <p v-if="part.description" class="text-xs text-gray-500 mt-0.5 line-clamp-1">{{ part.description }}</p>
                    <p v-if="part.manufacturer" class="text-xs text-gray-500 mt-0.5">{{ part.manufacturer.name }}</p>
                </div>

                <!-- Cost -->
                <div v-if="part.unit_cost != null" class="text-right shrink-0">
                    <p class="text-sm font-bold text-gray-900">${{ part.unit_cost.toFixed(2) }}</p>
                    <p class="text-xs text-gray-500">/ {{ part.unit }}</p>
                </div>
                <div v-else class="text-right shrink-0">
                    <p class="text-xs text-gray-500">{{ part.unit }}</p>
                </div>

                <FavoriteStar type="spareparts" :id="part.id" class="mt-0.5" />
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
            icon="package"
            title="Sin repuestos"
            :subtitle="search ? 'No se encontraron resultados para tu búsqueda.' : 'No hay repuestos registrados.'"
        />

    </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'
import { useApi } from '../composables/useApi.js'
import { useViewPreferences } from '../composables/useViewPreferences.js'
import { describe, CRITICALITY } from '../../shared/design.js'
import Badge from '../components/Badge.vue'
import EmptyState from '../components/EmptyState.vue'
import AppIcon from '../components/AppIcon.vue'
import FavoriteStar from '../components/FavoriteStar.vue'
import SavedViews from '../components/SavedViews.vue'

const api = useApi()
const parts = ref([])
const downloadingPdf = ref(false)

const crit = (c) => describe(CRITICALITY, c)

async function downloadPdf() {
    if (downloadingPdf.value) { return }
    downloadingPdf.value = true
    try {
        await api.download('reports/inventory', `inventario-${new Date().toISOString().slice(0, 10)}.pdf`)
    } catch { /* ignored */ } finally {
        downloadingPdf.value = false
    }
}
const loading = ref(true)
const loadingMore = ref(false)
const nextCursor = ref(null)
const { category: activeCategory, search, reset: resetPrefs } = useViewPreferences('spareparts', { category: '', search: '' })

function applySavedView(state) {
    activeCategory.value = state.category ?? ''
    search.value = state.search ?? ''
}

const categoryFilters = [
    { label: 'Todos', value: '' },
    { label: 'Mecánico', value: 'mechanical' },
    { label: 'Eléctrico', value: 'electrical' },
    { label: 'Instrumentación', value: 'instrumentation' },
    { label: 'Lubricación', value: 'lubrication' },
    { label: 'Consumible', value: 'consumable' },
    { label: 'Seguridad', value: 'safety' },
    { label: 'Otro', value: 'other' },
]

const categoryBg = {
    mechanical: 'bg-blue-50', electrical: 'bg-yellow-50', instrumentation: 'bg-purple-50',
    lubrication: 'bg-green-50', consumable: 'bg-gray-100', safety: 'bg-red-50', other: 'bg-gray-100',
}

const categoryEmoji = {
    mechanical: '⚙️', electrical: '⚡', instrumentation: '📡',
    lubrication: '🛢️', consumable: '📦', safety: '🦺', other: '🔧',
}

const abcBadge = {
    A: 'bg-red-100 text-red-700',
    B: 'bg-amber-100 text-amber-700',
    C: 'bg-gray-100 text-gray-600',
}

// ── API ───────────────────────────────────────────────────────────────────────

function buildParams(cursor = null) {
    const params = new URLSearchParams({ per_page: '30' })
    if (activeCategory.value) { params.set('category_type', activeCategory.value) }
    params.set('is_active', 'true')
    const q = search.value.trim()
    if (q) { params.set('search', q) }
    if (cursor) { params.set('cursor', cursor) }
    return params.toString()
}

async function load() {
    loading.value = true
    nextCursor.value = null
    try {
        const res = await api.get(`inventory/spare-parts?${buildParams()}`)
        parts.value = res?.data ?? []
        nextCursor.value = res?.meta?.next_cursor ?? null
    } catch { /* silent */ } finally {
        loading.value = false
    }
}

async function loadMore() {
    if (!nextCursor.value || loadingMore.value) { return }
    loadingMore.value = true
    try {
        const res = await api.get(`inventory/spare-parts?${buildParams(nextCursor.value)}`)
        parts.value = [...parts.value, ...(res?.data ?? [])]
        nextCursor.value = res?.meta?.next_cursor ?? null
    } catch { /* silent */ } finally {
        loadingMore.value = false
    }
}

// Server-side search with debounce so each keystroke does not fire a request.
let searchTimer = null
watch(search, () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(load, 350)
})
watch(activeCategory, load)
onMounted(load)
</script>
