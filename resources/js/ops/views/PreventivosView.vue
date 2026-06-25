<template>
    <div class="p-5 lg:p-8 max-w-5xl mx-auto">

        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-xl font-bold text-gray-900">Mantenimiento programado</h1>
                <p v-if="!loading" class="text-sm text-gray-500 mt-0.5">{{ total }} planes</p>
            </div>
        </div>

        <!-- Trigger filter pills -->
        <div class="flex items-center gap-2 mb-4">
            <div class="flex gap-1.5 overflow-x-auto pb-1 flex-1">
                <button
                    v-for="f in triggerFilters"
                    :key="f.value"
                    @click="activeTrigger = f.value"
                    class="shrink-0 flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-semibold transition-colors"
                    :class="activeTrigger === f.value
                        ? 'bg-slate-900 text-white'
                        : 'bg-white border border-gray-200 text-gray-600 hover:border-gray-300'"
                >
                    {{ f.label }}
                </button>
            </div>
            <SavedViews view="preventives" :current="{ trigger: activeTrigger, status: activeStatus }" @apply="applySavedView" />
            <button @click="resetPrefs" class="shrink-0 text-xs text-gray-400 hover:text-gray-600 transition-colors whitespace-nowrap" title="Restablecer preferencias de esta vista">
                Restablecer
            </button>
        </div>

        <!-- Active toggle -->
        <div class="flex gap-1 mb-5 p-1 bg-gray-100 rounded-xl w-fit">
            <button
                v-for="s in activeFilters"
                :key="s.value"
                @click="activeStatus = s.value"
                class="px-4 py-1.5 rounded-lg text-xs font-semibold transition-colors"
                :class="activeStatus === s.value ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500 hover:text-gray-700'"
            >
                {{ s.label }}
            </button>
        </div>

        <!-- Skeleton -->
        <div v-if="loading" class="space-y-3">
            <div v-for="i in 5" :key="i" class="bg-white rounded-2xl border border-gray-100 p-4 space-y-3">
                <div class="flex items-center gap-2">
                    <div class="skeleton w-4 h-4 rounded-full shrink-0" />
                    <div class="skeleton h-4 w-1/3 rounded" />
                    <div class="skeleton h-5 w-16 rounded-full ml-auto" />
                </div>
                <div class="skeleton h-5 w-2/3 rounded" />
                <div class="flex gap-2">
                    <div class="skeleton h-3 w-24 rounded" />
                    <div class="skeleton h-3 w-20 rounded" />
                </div>
            </div>
        </div>

        <!-- Plan list -->
        <div v-else-if="plans.length" class="space-y-3">
            <div
                v-for="plan in plans"
                :key="plan.id"
                class="bg-white rounded-2xl border border-gray-100 shadow-sm hover:shadow-md hover:border-gray-200 transition-all p-4"
            >
                <!-- Top row: number + trigger badge -->
                <div class="flex items-center gap-2 mb-2">
                    <span class="w-2 h-2 rounded-full shrink-0" :class="plan.is_active ? 'bg-emerald-500' : 'bg-gray-300'" />
                    <span class="font-mono text-xs text-gray-500">{{ plan.plan_number }}</span>
                    <span class="ml-auto text-xs font-bold px-2 py-0.5 rounded-full" :class="triggerBadge[plan.trigger_source]">
                        {{ triggerLabel[plan.trigger_source] ?? plan.trigger_source }}
                    </span>
                    <FavoriteStar type="preventives" :id="plan.id" size="w-4 h-4" />
                </div>

                <!-- Name -->
                <p class="text-sm font-bold text-gray-900 leading-snug mb-1.5">{{ plan.name }}</p>

                <!-- Equipment -->
                <RouterLink
                    v-if="plan.equipment"
                    :to="{ name: 'ops.equipos.show', params: { id: plan.equipment.id } }"
                    class="inline-flex items-center gap-1 text-xs text-indigo-600 hover:text-indigo-800 font-medium mb-3"
                >
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    {{ plan.equipment.code }} — {{ plan.equipment.name }}
                </RouterLink>

                <!-- Stats row -->
                <div class="flex items-center gap-4 flex-wrap">

                    <!-- Frequency -->
                    <div class="flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-gray-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="text-xs text-gray-600 font-medium">{{ plan.frequency_label }}</span>
                    </div>

                    <!-- Duration -->
                    <div v-if="plan.estimated_duration_minutes" class="flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-gray-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="text-xs text-gray-500">{{ formatDuration(plan.estimated_duration_minutes) }}</span>
                    </div>

                    <!-- Times executed -->
                    <div v-if="plan.schedule?.times_executed" class="flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-gray-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="text-xs text-gray-500">{{ plan.schedule.times_executed }} ejecuciones</span>
                    </div>

                    <!-- Next due / overdue -->
                    <div class="ml-auto shrink-0">
                        <template v-if="plan.schedule">
                            <span v-if="plan.schedule.is_overdue" class="inline-flex items-center gap-1 text-xs font-semibold text-red-600 bg-red-50 border border-red-200 px-2 py-0.5 rounded-full">
                                <span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse" />
                                Vencido
                            </span>
                            <span v-else-if="plan.schedule.next_due_at" class="text-xs" :class="dueSoonClass(plan.schedule.next_due_at)">
                                Próx: {{ formatNextDue(plan.schedule.next_due_at) }}
                            </span>
                            <span v-else-if="plan.schedule.next_due_meter" class="text-xs text-gray-500">
                                Próx: {{ plan.schedule.next_due_meter.toFixed(0) }} h
                            </span>
                            <span v-else class="text-xs text-gray-500">Sin fecha próxima</span>
                        </template>
                        <span v-else class="text-xs text-gray-500">Sin programa</span>
                    </div>
                </div>

                <!-- Last completed -->
                <div v-if="plan.schedule?.last_completed_at" class="mt-2 pt-2 border-t border-gray-50">
                    <p class="text-xs text-gray-500">
                        Última ejecución: {{ formatDate(plan.schedule.last_completed_at) }}
                    </p>
                </div>
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
            icon="calendar"
            title="Sin planes preventivos"
            subtitle="No hay planes con los filtros seleccionados."
        />

    </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { useApi } from '../composables/useApi.js'
import { useViewPreferences } from '../composables/useViewPreferences.js'
import EmptyState from '../components/EmptyState.vue'
import FavoriteStar from '../components/FavoriteStar.vue'
import SavedViews from '../components/SavedViews.vue'

const api = useApi()
const plans = ref([])
const loading = ref(true)
const loadingMore = ref(false)
const nextCursor = ref(null)
const total = ref(0)
const { trigger: activeTrigger, status: activeStatus, reset: resetPrefs } = useViewPreferences('preventives', { trigger: '', status: 'active' })

function applySavedView(state) {
    activeTrigger.value = state.trigger ?? ''
    activeStatus.value = state.status ?? 'active'
}

const triggerFilters = [
    { label: 'Todos', value: '' },
    { label: 'Calendario', value: 'calendar' },
    { label: 'Horómetro', value: 'meter' },
    { label: 'Híbrido', value: 'hybrid' },
    { label: 'Manual', value: 'manual' },
]

const activeFilters = [
    { label: 'Activos', value: 'active' },
    { label: 'Todos', value: '' },
]

const triggerLabel = {
    calendar: 'Calendario', meter: 'Horómetro', hybrid: 'Híbrido', manual: 'Manual',
}
const triggerBadge = {
    calendar: 'bg-blue-100 text-blue-700',
    meter: 'bg-amber-100 text-amber-700',
    hybrid: 'bg-emerald-100 text-emerald-700',
    manual: 'bg-gray-100 text-gray-600',
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function formatDate(iso) {
    if (!iso) { return null }
    return new Date(iso).toLocaleDateString('es', { day: 'numeric', month: 'short', year: 'numeric' })
}

function formatNextDue(iso) {
    if (!iso) { return '—' }
    const d = new Date(iso)
    const diffDays = Math.ceil((d - Date.now()) / 864e5)
    if (diffDays === 0) { return 'Hoy' }
    if (diffDays === 1) { return 'Mañana' }
    if (diffDays > 0 && diffDays <= 30) { return `en ${diffDays}d` }
    return d.toLocaleDateString('es', { day: 'numeric', month: 'short' })
}

function dueSoonClass(iso) {
    const diffDays = Math.ceil((new Date(iso) - Date.now()) / 864e5)
    if (diffDays <= 7) { return 'text-amber-600 font-semibold' }
    return 'text-gray-500'
}

function formatDuration(minutes) {
    if (!minutes) { return null }
    if (minutes < 60) { return `${minutes} min` }
    const h = Math.floor(minutes / 60)
    const m = minutes % 60
    return m > 0 ? `${h}h ${m}min` : `${h}h`
}

// ── API ───────────────────────────────────────────────────────────────────────

function buildParams(cursor = null) {
    const params = new URLSearchParams({ per_page: '50' })
    if (activeTrigger.value) { params.set('trigger_source', activeTrigger.value) }
    if (activeStatus.value === 'active') { params.set('is_active', 'true') }
    if (cursor) { params.set('cursor', cursor) }
    return params.toString()
}

async function load() {
    loading.value = true
    nextCursor.value = null
    try {
        const res = await api.get(`maintenance-plans?${buildParams()}`)
        plans.value = res?.data ?? []
        total.value = res?.meta?.total ?? plans.value.length
        nextCursor.value = res?.meta?.next_cursor ?? null
    } catch { /* silent */ } finally {
        loading.value = false
    }
}

async function loadMore() {
    if (!nextCursor.value || loadingMore.value) { return }
    loadingMore.value = true
    try {
        const res = await api.get(`maintenance-plans?${buildParams(nextCursor.value)}`)
        plans.value = [...plans.value, ...(res?.data ?? [])]
        nextCursor.value = res?.meta?.next_cursor ?? null
    } catch { /* silent */ } finally {
        loadingMore.value = false
    }
}

watch([activeTrigger, activeStatus], load)
onMounted(load)
</script>
