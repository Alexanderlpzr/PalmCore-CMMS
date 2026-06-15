<template>
    <div class="min-h-screen bg-gray-50">

        <!-- Top bar -->
        <div class="bg-white border-b border-gray-100 sticky top-0 z-10">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 py-3 flex items-center gap-3">
                <RouterLink
                    :to="{ name: 'ops.solicitudes' }"
                    class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 text-gray-400 transition-colors"
                >
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                </RouterLink>
                <span class="font-mono text-sm text-gray-500 truncate">{{ mr?.request_number ?? '' }}</span>
                <span
                    v-if="mr"
                    class="ml-auto shrink-0 text-xs font-bold px-2.5 py-1 rounded-full"
                    :class="statusBadge[mr.status]"
                >
                    {{ statusLabel[mr.status] ?? mr.status }}
                </span>
            </div>
        </div>

        <!-- Skeleton -->
        <div v-if="loading" class="max-w-3xl mx-auto px-4 sm:px-6 py-6 space-y-4">
            <div class="skeleton h-7 w-2/3 rounded-lg" />
            <div class="skeleton h-4 w-1/2 rounded" />
            <div class="bg-white rounded-2xl border border-gray-100 p-5 space-y-3">
                <div v-for="i in 5" :key="i" class="skeleton h-4 rounded" :style="`width: ${85 - i * 10}%`" />
            </div>
        </div>

        <!-- Error -->
        <div v-else-if="error" class="max-w-3xl mx-auto px-4 py-20 text-center">
            <p class="text-sm text-red-600">{{ error }}</p>
            <button @click="load" class="mt-3 text-xs text-gray-500 underline">Reintentar</button>
        </div>

        <!-- Content -->
        <div v-else-if="mr" class="max-w-3xl mx-auto px-4 sm:px-6 py-5 space-y-5">

            <!-- Title + meta -->
            <div>
                <h1 class="text-xl font-bold text-gray-900 leading-tight">{{ mr.title ?? mr.description }}</h1>
                <div class="flex items-center flex-wrap gap-x-2 gap-y-1.5 mt-2">
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full" :class="priorityBadge[mr.priority]">
                        {{ priorityLabel[mr.priority] ?? mr.priority }}
                    </span>
                    <span class="text-xs text-gray-400">{{ typeLabel[mr.request_type] ?? mr.request_type }}</span>
                    <RouterLink
                        v-if="mr.equipment"
                        :to="{ name: 'ops.equipos.show', params: { id: mr.equipment.id } }"
                        class="text-xs text-indigo-600 hover:text-indigo-800 font-medium"
                    >
                        · {{ mr.equipment.code }} — {{ mr.equipment.name }}
                    </RouterLink>
                </div>
            </div>

            <!-- Work Order link (when converted) -->
            <RouterLink
                v-if="mr.work_order"
                :to="{ name: 'ops.ordenes.show', params: { id: mr.work_order.id } }"
                class="flex items-center gap-3 bg-indigo-50 border border-indigo-200 rounded-2xl p-3.5 hover:bg-indigo-100 transition-colors"
            >
                <div class="w-8 h-8 rounded-xl bg-indigo-600 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.35 3.836c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m8.9-4.414c.376.023.75.05 1.124.08 1.131.094 1.976 1.057 1.976 2.192V16.5A2.25 2.25 0 0118 18.75h-2.25m-7.5-10.5H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V18.75m-7.5-10.5h6.375c.621 0 1.125.504 1.125 1.125v9.375m-8.25-3l1.5 1.5 3-3.75" />
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-semibold text-indigo-800">Orden de trabajo generada</p>
                    <p class="font-mono text-xs text-indigo-600 mt-0.5">{{ mr.work_order.work_order_number }}</p>
                </div>
                <svg class="w-4 h-4 text-indigo-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                </svg>
            </RouterLink>

            <!-- Status actions -->
            <div v-if="primaryTransition || secondaryTransitions.length" class="flex gap-2 flex-wrap">
                <button
                    v-if="primaryTransition"
                    @click="transition(primaryTransition.status)"
                    :disabled="transitioning"
                    class="flex-1 sm:flex-none flex items-center justify-center gap-1.5 px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60 transition-colors"
                >
                    {{ transitioning ? '…' : primaryTransition.label }}
                </button>
                <button
                    v-for="t in secondaryTransitions"
                    :key="t.status"
                    @click="transition(t.status)"
                    :disabled="transitioning"
                    class="px-4 py-2.5 rounded-xl text-sm font-semibold text-gray-700 bg-white border border-gray-200 hover:border-gray-300 disabled:opacity-60 transition-colors"
                    :class="t.danger ? 'text-red-600 border-red-200 hover:border-red-300 hover:bg-red-50' : ''"
                >
                    {{ t.label }}
                </button>
                <p v-if="transitionError" class="w-full text-xs text-red-600">{{ transitionError }}</p>
            </div>

            <!-- Tabs -->
            <div class="flex border-b border-gray-200">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    @click="activeTab = tab.key"
                    class="shrink-0 px-4 py-2.5 text-sm font-semibold border-b-2 transition-colors"
                    :class="activeTab === tab.key
                        ? 'border-indigo-600 text-indigo-600'
                        : 'border-transparent text-gray-500 hover:text-gray-700'"
                >
                    {{ tab.label }}
                    <span
                        v-if="tab.count != null && tab.count > 0"
                        class="ml-1.5 text-[10px] bg-gray-100 text-gray-500 rounded-full px-1.5 py-0.5"
                    >{{ tab.count }}</span>
                </button>
            </div>

            <!-- Tab: Detalles -->
            <div v-if="activeTab === 'details'" class="space-y-4">

                <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
                    <InfoRow label="Tipo" :value="typeLabel[mr.request_type] ?? mr.request_type" />
                    <InfoRow label="Prioridad" :value="priorityLabel[mr.priority] ?? mr.priority" />
                    <InfoRow label="Equipo" :value="mr.equipment ? `${mr.equipment.code} — ${mr.equipment.name}` : null" />
                    <InfoRow label="Fecha límite solicitada" :value="formatDate(mr.requested_due_date)" />
                    <InfoRow label="Enviado" :value="formatDate(mr.submitted_at)" />
                    <InfoRow label="Revisado" :value="formatDate(mr.reviewed_at)" />
                    <InfoRow label="Aprobado" :value="formatDate(mr.approved_at)" />
                    <InfoRow label="Rechazado" :value="formatDate(mr.rejected_at)" />
                    <InfoRow label="Creado" :value="formatDate(mr.created_at)" />
                </div>

                <div v-if="mr.description" class="bg-white rounded-2xl border border-gray-100 p-4">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Descripción</p>
                    <p class="text-sm text-gray-700 whitespace-pre-line">{{ mr.description }}</p>
                </div>

                <div v-if="mr.rejection_reason" class="bg-red-50 border border-red-200 rounded-2xl p-4">
                    <p class="text-[10px] font-bold text-red-500 uppercase tracking-widest mb-2">Motivo de rechazo</p>
                    <p class="text-sm text-red-800 whitespace-pre-line">{{ mr.rejection_reason }}</p>
                </div>

            </div>

            <!-- Tab: Comentarios (read-only) -->
            <div v-else-if="activeTab === 'comments'">

                <div v-if="mr.comments?.length" class="space-y-2">
                    <div
                        v-for="c in mr.comments"
                        :key="c.id"
                        class="bg-white rounded-2xl border p-4"
                        :class="c.is_internal ? 'border-amber-200 bg-amber-50/40' : 'border-gray-100'"
                    >
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-sm font-semibold text-gray-900">{{ c.user?.name ?? 'Usuario' }}</p>
                            <div class="flex items-center gap-1.5">
                                <span v-if="c.is_internal" class="text-[10px] border border-amber-300 text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded-full font-semibold">Interno</span>
                                <span class="text-xs text-gray-400">{{ relativeTime(c.created_at) }}</span>
                            </div>
                        </div>
                        <p class="text-sm text-gray-700 whitespace-pre-line">{{ c.body }}</p>
                    </div>
                </div>

                <div v-else class="py-16 text-center">
                    <p class="text-sm text-gray-400">Sin comentarios</p>
                </div>

            </div>

        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, defineComponent, h } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { useApi } from '../composables/useApi.js'

const route = useRoute()
const api = useApi()

const mr = ref(null)
const loading = ref(true)
const error = ref(null)
const activeTab = ref('details')
const transitioning = ref(false)
const transitionError = ref(null)

// ── Label maps ────────────────────────────────────────────────────────────────

const statusLabel = {
    draft: 'Borrador', submitted: 'Enviado', under_review: 'En Revisión',
    approved: 'Aprobado', rejected: 'Rechazado', cancelled: 'Cancelado', converted: 'Convertido a OT',
}
const statusBadge = {
    draft: 'bg-gray-100 text-gray-600',
    submitted: 'bg-blue-100 text-blue-700',
    under_review: 'bg-amber-100 text-amber-700',
    approved: 'bg-emerald-100 text-emerald-700',
    rejected: 'bg-red-100 text-red-600',
    cancelled: 'bg-gray-100 text-gray-500',
    converted: 'bg-indigo-100 text-indigo-700',
}
const priorityLabel = {
    p1_critical: 'Crítica', p2_high: 'Alta', p3_medium: 'Media', p4_low: 'Baja',
}
const priorityBadge = {
    p1_critical: 'bg-red-100 text-red-700',
    p2_high: 'bg-orange-100 text-orange-700',
    p3_medium: 'bg-yellow-100 text-yellow-700',
    p4_low: 'bg-gray-100 text-gray-600',
}
const typeLabel = {
    corrective: 'Correctivo', preventive: 'Preventivo', predictive: 'Predictivo',
    inspection: 'Inspección', emergency: 'Emergencia',
}

// ── Transitions ───────────────────────────────────────────────────────────────

const transitionMap = {
    draft: [
        { status: 'submitted', label: 'Enviar solicitud', primary: true },
        { status: 'cancelled', label: 'Cancelar', primary: false, danger: true },
    ],
    submitted: [
        { status: 'under_review', label: 'Iniciar revisión', primary: true },
        { status: 'cancelled', label: 'Cancelar', primary: false, danger: true },
    ],
    under_review: [
        { status: 'approved', label: 'Aprobar', primary: true },
        { status: 'rejected', label: 'Rechazar', primary: false, danger: true },
        { status: 'submitted', label: 'Devolver', primary: false },
    ],
    rejected: [
        { status: 'submitted', label: 'Reenviar', primary: true },
    ],
}

const primaryTransition = computed(() => transitionMap[mr.value?.status]?.find(t => t.primary) ?? null)
const secondaryTransitions = computed(() => transitionMap[mr.value?.status]?.filter(t => !t.primary) ?? [])

// ── Tabs ──────────────────────────────────────────────────────────────────────

const tabs = computed(() => [
    { key: 'details', label: 'Detalles' },
    { key: 'comments', label: 'Comentarios', count: mr.value?.comments?.length ?? null },
])

// ── Helpers ───────────────────────────────────────────────────────────────────

function formatDate(iso) {
    if (!iso) { return null }
    return new Date(iso).toLocaleString('es-MX', {
        day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
    })
}

function relativeTime(iso) {
    if (!iso) { return '' }
    const diff = Date.now() - new Date(iso).getTime()
    const h = Math.floor(diff / 36e5)
    if (h < 1) { return 'hace menos de 1h' }
    if (h < 24) { return `hace ${h}h` }
    return `hace ${Math.floor(h / 24)}d`
}

// ── InfoRow ───────────────────────────────────────────────────────────────────

const InfoRow = defineComponent({
    props: { label: String, value: [String, Number] },
    setup(props) {
        return () => {
            if (props.value == null || props.value === '') { return null }
            return h('div', { class: 'flex items-start justify-between gap-4 px-4 py-2.5 border-b border-gray-50 last:border-0' }, [
                h('span', { class: 'text-xs text-gray-400 shrink-0 pt-0.5' }, props.label),
                h('span', { class: 'text-xs font-medium text-gray-900 text-right break-words max-w-[60%]' }, String(props.value)),
            ])
        }
    },
})

// ── API ───────────────────────────────────────────────────────────────────────

async function load() {
    loading.value = true
    error.value = null
    try {
        const res = await api.get(`maintenance-requests/${route.params.id}`)
        mr.value = res?.data ?? res
    } catch (err) {
        error.value = err?.message ?? 'Error al cargar la solicitud'
    } finally {
        loading.value = false
    }
}

async function transition(status) {
    transitioning.value = true
    transitionError.value = null
    try {
        const res = await api.patch(`maintenance-requests/${mr.value.id}/status`, { status })
        mr.value = res?.data ?? res
    } catch (err) {
        transitionError.value = err?.message ?? 'Error al cambiar el estado'
    } finally {
        transitioning.value = false
    }
}

onMounted(load)
</script>
