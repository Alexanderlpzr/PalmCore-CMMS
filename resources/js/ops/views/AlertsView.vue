<template>
    <div class="p-5 lg:p-8 max-w-3xl mx-auto">

        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-xl font-bold text-gray-900">Alertas</h1>
                <p v-if="!loading" class="text-sm text-gray-500 mt-0.5">{{ total }} alerta{{ total !== 1 ? 's' : '' }}</p>
            </div>
        </div>

        <!-- Severity filter pills -->
        <div class="flex gap-1.5 mb-4 overflow-x-auto pb-1">
            <button
                v-for="f in severityFilters"
                :key="f.value"
                @click="activeSeverity = f.value"
                class="shrink-0 flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-semibold transition-colors"
                :class="activeSeverity === f.value
                    ? 'bg-slate-900 text-white'
                    : 'bg-white border border-gray-200 text-gray-600 hover:border-gray-300'"
            >
                <span v-if="f.dot" class="w-1.5 h-1.5 rounded-full" :class="f.dot" />
                {{ f.label }}
            </button>
        </div>

        <!-- Status toggle -->
        <div class="flex gap-1 mb-5 p-1 bg-gray-100 rounded-xl w-fit">
            <button
                v-for="s in statusFilters"
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
            <div v-for="i in 4" :key="i" class="bg-white rounded-2xl border border-gray-100 p-4 space-y-2">
                <div class="flex items-center gap-2">
                    <div class="skeleton w-5 h-5 rounded-full shrink-0" />
                    <div class="skeleton h-4 w-1/4 rounded" />
                </div>
                <div class="skeleton h-4 w-3/4 rounded" />
                <div class="skeleton h-3 w-1/2 rounded" />
            </div>
        </div>

        <!-- Alert list -->
        <div v-else-if="alerts.length" class="space-y-3">
            <div
                v-for="alert in alerts"
                :key="alert.id"
                class="bg-white rounded-2xl border shadow-sm transition-all"
                :class="severityBorder[alert.severity]"
            >
                <div class="p-4">
                    <div class="flex items-start gap-3">
                        <!-- Severity icon -->
                        <div class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0 mt-0.5" :class="severityIconBg[alert.severity]">
                            <svg v-if="alert.severity === 'critical'" class="w-4 h-4" :class="severityIconColor[alert.severity]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                            </svg>
                            <svg v-else-if="alert.severity === 'warning'" class="w-4 h-4" :class="severityIconColor[alert.severity]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9.303 3.376c.866 1.5-.217 3.374-1.948 3.374H4.645c-1.73 0-2.813-1.874-1.948-3.374l7.658-13.598c.866-1.5 3.032-1.5 3.898 0l7.658 13.598z" />
                            </svg>
                            <svg v-else class="w-4 h-4" :class="severityIconColor[alert.severity]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                            </svg>
                        </div>

                        <!-- Content -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1 flex-wrap">
                                <span class="text-xs font-bold uppercase tracking-wide" :class="severityTextColor[alert.severity]">
                                    {{ severityLabel[alert.severity] }}
                                </span>
                                <span class="text-xs text-gray-500 bg-gray-100 px-1.5 py-0.5 rounded-full">{{ categoryLabel[alert.category] ?? alert.category }}</span>
                            </div>
                            <p class="text-sm font-semibold text-gray-900 leading-snug">{{ alert.title }}</p>
                            <p class="text-xs text-gray-500 mt-1 leading-relaxed">{{ alert.message }}</p>
                            <p class="text-xs text-gray-500 mt-2">{{ relativeTime(alert.created_at) }}</p>
                        </div>

                        <!-- Status badge for closed -->
                        <span
                            v-if="alert.status !== 'open'"
                            class="shrink-0 text-xs font-bold px-2 py-1 rounded-full"
                            :class="alert.status === 'resolved' ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500'"
                        >
                            {{ alert.status === 'resolved' ? 'Resuelta' : 'Descartada' }}
                        </span>
                    </div>

                    <!-- Actions for open alerts -->
                    <div v-if="alert.status === 'open'" class="flex gap-2 mt-3 pt-3 border-t border-gray-50">
                        <button
                            @click="resolveAlert(alert)"
                            :disabled="alert._loading"
                            class="flex-1 text-xs font-semibold py-2 rounded-xl bg-emerald-50 hover:bg-emerald-100 text-emerald-700 disabled:opacity-50 transition-colors"
                        >
                            {{ alert._loading === 'resolve' ? '…' : 'Resolver' }}
                        </button>
                        <button
                            v-if="alert.severity !== 'critical'"
                            @click="dismissAlert(alert)"
                            :disabled="alert._loading"
                            class="flex-1 text-xs font-semibold py-2 rounded-xl bg-gray-50 hover:bg-gray-100 text-gray-600 disabled:opacity-50 transition-colors"
                        >
                            {{ alert._loading === 'dismiss' ? '…' : 'Descartar' }}
                        </button>
                    </div>
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
            icon="bell"
            title="Sin alertas"
            :subtitle="`No hay alertas ${activeStatus === 'open' ? 'abiertas' : 'cerradas'} con los filtros actuales.`"
        />

    </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'
import { useApi } from '../composables/useApi.js'
import EmptyState from '../components/EmptyState.vue'

const api = useApi()
const alerts = ref([])
const loading = ref(true)
const loadingMore = ref(false)
const total = ref(0)
const nextCursor = ref(null)
const activeSeverity = ref('')
const activeStatus = ref('open')

const severityFilters = [
    { label: 'Todas', value: '' },
    { label: 'Críticas', value: 'critical', dot: 'bg-red-500' },
    { label: 'Advertencia', value: 'warning', dot: 'bg-amber-500' },
    { label: 'Informativas', value: 'info', dot: 'bg-blue-400' },
]

const statusFilters = [
    { label: 'Abiertas', value: 'open' },
    { label: 'Cerradas', value: 'resolved' },
]

const severityLabel = { critical: 'Crítico', warning: 'Advertencia', info: 'Informativo' }
const severityTextColor = { critical: 'text-red-600', warning: 'text-amber-600', info: 'text-blue-600' }
const severityBorder = { critical: 'border-red-200', warning: 'border-amber-200', info: 'border-blue-100' }
const severityIconBg = { critical: 'bg-red-50', warning: 'bg-amber-50', info: 'bg-blue-50' }
const severityIconColor = { critical: 'text-red-500', warning: 'text-amber-500', info: 'text-blue-500' }

const categoryLabel = {
    inventory: 'Inventario',
    reliability: 'Confiabilidad',
    maintenance: 'Mantenimiento',
    automation: 'Automatización',
    work_order: 'Órdenes de trabajo',
    system: 'Sistema',
}

function relativeTime(iso) {
    if (!iso) { return '' }
    const diff = Date.now() - new Date(iso).getTime()
    const h = Math.floor(diff / 36e5)
    if (h < 1) { return 'hace menos de 1h' }
    if (h < 24) { return `hace ${h}h` }
    const d = Math.floor(h / 24)
    if (d < 7) { return `hace ${d}d` }
    return new Date(iso).toLocaleDateString('es', { day: 'numeric', month: 'short' })
}

function buildParams(cursor = null) {
    const params = new URLSearchParams({ per_page: '25' })
    if (activeSeverity.value) { params.set('severity', activeSeverity.value) }
    if (activeStatus.value) { params.set('status', activeStatus.value) }
    if (cursor) { params.set('cursor', cursor) }
    return params.toString()
}

async function load() {
    loading.value = true
    nextCursor.value = null
    try {
        const res = await api.get(`alerts?${buildParams()}`)
        alerts.value = (res?.data ?? []).map(a => ({ ...a, _loading: null }))
        total.value = res?.meta?.total ?? alerts.value.length
        nextCursor.value = res?.meta?.next_cursor ?? null
    } catch { /* silent */ } finally {
        loading.value = false
    }
}

async function loadMore() {
    if (!nextCursor.value || loadingMore.value) { return }
    loadingMore.value = true
    try {
        const res = await api.get(`alerts?${buildParams(nextCursor.value)}`)
        alerts.value = [...alerts.value, ...(res?.data ?? []).map(a => ({ ...a, _loading: null }))]
        nextCursor.value = res?.meta?.next_cursor ?? null
    } catch { /* silent */ } finally {
        loadingMore.value = false
    }
}

async function resolveAlert(alert) {
    alert._loading = 'resolve'
    try {
        await api.patch(`alerts/${alert.id}/resolve`, {})
        alert.status = 'resolved'
        total.value = Math.max(0, total.value - 1)
        if (activeStatus.value === 'open') {
            alerts.value = alerts.value.filter(a => a.id !== alert.id)
        }
    } catch (err) {
        alert._loading = null
    }
}

async function dismissAlert(alert) {
    alert._loading = 'dismiss'
    try {
        await api.patch(`alerts/${alert.id}/dismiss`, {})
        alert.status = 'dismissed'
        total.value = Math.max(0, total.value - 1)
        if (activeStatus.value === 'open') {
            alerts.value = alerts.value.filter(a => a.id !== alert.id)
        }
    } catch (err) {
        alert._loading = null
    }
}

watch([activeSeverity, activeStatus], load)
onMounted(load)
</script>
