<template>
    <div class="p-5 lg:p-8 max-w-5xl mx-auto">

        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-xl font-bold text-gray-900">Solicitudes de mantenimiento</h1>
                <p v-if="!loading" class="text-sm text-gray-500 mt-0.5">{{ requests.length }} solicitudes</p>
            </div>
            <button
                @click="createInFilament"
                class="flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-4 py-2 rounded-xl transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                Nueva solicitud
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
            <div v-for="i in 5" :key="i" class="bg-white rounded-2xl border border-gray-100 p-4 space-y-2">
                <div class="flex justify-between">
                    <div class="skeleton h-4 w-1/2 rounded" />
                    <div class="skeleton h-5 w-16 rounded-full" />
                </div>
                <div class="skeleton h-3 w-1/3 rounded" />
            </div>
        </div>

        <!-- Request list -->
        <div v-else-if="requests.length" class="space-y-2">
            <RouterLink
                v-for="mr in requests"
                :key="mr.id"
                :to="{ name: 'ops.solicitudes.show', params: { id: mr.id } }"
                class="block bg-white rounded-2xl border border-gray-100 shadow-sm hover:shadow-md hover:border-gray-200 transition-all p-4"
            >
                <div class="flex items-start justify-between gap-3 mb-2">
                    <div class="flex items-start gap-2.5 flex-1 min-w-0">
                        <!-- Priority dot -->
                        <div class="w-2 h-2 rounded-full mt-2 shrink-0" :class="priorityDot[mr.priority] ?? 'bg-gray-400'" />
                        <p class="text-sm font-semibold text-gray-900 leading-snug">{{ mr.title }}</p>
                    </div>
                    <Badge :tone="status(mr.status).tone" :label="status(mr.status).label" class="shrink-0" />
                </div>

                <div class="flex items-center gap-3 text-xs text-gray-500 ml-4.5">
                    <span v-if="mr.equipment?.code" class="font-mono font-semibold text-gray-500">{{ mr.equipment.code }}</span>
                    <span v-if="mr.request_type" class="capitalize">{{ mr.request_type }}</span>
                    <span>{{ relativeTime(mr.created_at) }}</span>
                </div>
            </RouterLink>
        </div>

        <!-- Empty -->
        <EmptyState
            v-else
            icon="clipboard"
            title="Sin solicitudes"
            subtitle="No hay solicitudes con los filtros seleccionados."
        />

    </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { useApi } from '../composables/useApi.js'
import { useAuthStore } from '../stores/auth.js'
import { describe, MAINTENANCE_REQUEST_STATUS } from '../../shared/design.js'
import Badge from '../components/Badge.vue'
import EmptyState from '../components/EmptyState.vue'

const api = useApi()
const auth = useAuthStore()
const requests = ref([])
const loading = ref(true)
const activeFilter = ref('submitted,under_review')

const filters = [
    { label: 'Pendientes', value: 'submitted,under_review' },
    { label: 'Aprobadas', value: 'approved' },
    { label: 'Rechazadas', value: 'rejected' },
    { label: 'Todas', value: '' },
]

// Priority dot — colors aligned with the shared PRIORITY tones (danger/warning/info/neutral).
const priorityDot = { p1_critical: 'bg-red-500', p2_high: 'bg-amber-500', p3_medium: 'bg-blue-500', p4_low: 'bg-gray-300' }

const status = (s) => describe(MAINTENANCE_REQUEST_STATUS, s)

function relativeTime(dateStr) {
    const diff = Date.now() - new Date(dateStr).getTime()
    const h = Math.floor(diff / 36e5)
    if (h < 1) return 'hace menos de 1h'
    if (h < 24) return `hace ${h}h`
    return `hace ${Math.floor(h / 24)}d`
}

async function load() {
    loading.value = true
    try {
        const statusParam = activeFilter.value ? `&status=${activeFilter.value}` : ''
        const res = await api.get(`maintenance-requests?per_page=50${statusParam}`)
        requests.value = res?.data ?? []
    } catch { /* silent */ } finally {
        loading.value = false
    }
}

function createInFilament() {
    window.location.href = `/admin/${auth.tenantSlug}/maintenance-requests/create`
}

watch(activeFilter, load)
onMounted(load)
</script>
