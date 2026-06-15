<template>
    <div class="min-h-screen bg-gray-50">

        <!-- Top bar -->
        <div class="bg-white border-b border-gray-100 sticky top-0 z-10">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 py-3 flex items-center gap-3">
                <RouterLink
                    :to="{ name: 'ops.ordenes' }"
                    class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 text-gray-400 transition-colors"
                >
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                </RouterLink>
                <span class="font-mono text-sm text-gray-500 truncate">{{ wo?.work_order_number ?? '' }}</span>
                <span
                    v-if="wo"
                    class="ml-auto shrink-0 text-xs font-bold px-2.5 py-1 rounded-full"
                    :class="statusBadge[wo.status]"
                >
                    {{ statusLabel[wo.status] ?? wo.status }}
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
        <div v-else-if="wo" class="max-w-3xl mx-auto px-4 sm:px-6 py-5 space-y-5">

            <!-- Title + meta -->
            <div>
                <h1 class="text-xl font-bold text-gray-900 leading-tight">{{ wo.title }}</h1>
                <div class="flex items-center flex-wrap gap-x-2 gap-y-1.5 mt-2">
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full" :class="priorityBadge[wo.priority]">
                        {{ priorityLabel[wo.priority] ?? wo.priority }}
                    </span>
                    <span class="text-xs text-gray-400">{{ typeLabel[wo.work_order_type] ?? wo.work_order_type }}</span>
                    <RouterLink
                        v-if="wo.equipment"
                        :to="{ name: 'ops.equipos.show', params: { id: wo.equipment.id } }"
                        class="text-xs text-indigo-600 hover:text-indigo-800 font-medium"
                    >
                        · {{ wo.equipment.code }} — {{ wo.equipment.name }}
                    </RouterLink>
                </div>
            </div>

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
                >
                    {{ t.label }}
                </button>
                <p v-if="transitionError" class="w-full text-xs text-red-600">{{ transitionError }}</p>
            </div>

            <!-- Tabs -->
            <div class="flex border-b border-gray-200 overflow-x-auto">
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
                    <InfoRow label="Tipo" :value="typeLabel[wo.work_order_type] ?? wo.work_order_type" />
                    <InfoRow label="Planta" :value="wo.plant?.name" />
                    <InfoRow label="Área" :value="wo.area?.name" />
                    <InfoRow label="Equipo detenido" :value="wo.equipment_stopped === true ? 'Sí' : wo.equipment_stopped === false ? 'No' : null" />
                    <InfoRow label="Tiempo de paro" :value="wo.downtime_minutes != null ? `${wo.downtime_minutes} min` : null" />
                    <InfoRow label="Inicio planificado" :value="formatDate(wo.planned_start_at)" />
                    <InfoRow label="Fin planificado" :value="formatDate(wo.planned_end_at)" />
                    <InfoRow label="Inicio real" :value="formatDate(wo.started_at ?? wo.actual_start_at)" />
                    <InfoRow label="Completado" :value="formatDate(wo.completed_at ?? wo.actual_end_at)" />
                    <InfoRow label="Horas planif." :value="wo.planned_labor_hours != null ? `${wo.planned_labor_hours} h` : null" />
                    <InfoRow label="Horas reales" :value="wo.actual_labor_hours != null ? `${wo.actual_labor_hours} h` : null" />
                    <InfoRow label="Costo total" :value="wo.actual_cost_total != null ? formatCurrency(wo.actual_cost_total, wo.currency_code) : null" />
                    <InfoRow label="Creado" :value="formatDate(wo.created_at)" />
                </div>

                <div v-if="wo.description" class="bg-white rounded-2xl border border-gray-100 p-4">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Descripción</p>
                    <p class="text-sm text-gray-700 whitespace-pre-line">{{ wo.description }}</p>
                </div>

                <div v-if="wo.instructions" class="bg-white rounded-2xl border border-gray-100 p-4">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Instrucciones</p>
                    <p class="text-sm text-gray-700 whitespace-pre-line">{{ wo.instructions }}</p>
                </div>

                <div v-if="wo.work_performed || wo.failure_cause || wo.root_cause" class="bg-white rounded-2xl border border-gray-100 p-4 space-y-4">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Resultado</p>
                    <div v-if="wo.work_performed">
                        <p class="text-xs text-gray-500 mb-1">Trabajo realizado</p>
                        <p class="text-sm text-gray-700 whitespace-pre-line">{{ wo.work_performed }}</p>
                    </div>
                    <div v-if="wo.failure_cause">
                        <p class="text-xs text-gray-500 mb-1">Causa de falla</p>
                        <p class="text-sm text-gray-700 whitespace-pre-line">{{ wo.failure_cause }}</p>
                    </div>
                    <div v-if="wo.root_cause">
                        <p class="text-xs text-gray-500 mb-1">Causa raíz</p>
                        <p class="text-sm text-gray-700 whitespace-pre-line">{{ wo.root_cause }}</p>
                    </div>
                </div>

            </div>

            <!-- Tab: Técnicos -->
            <div v-else-if="activeTab === 'technicians'">
                <div v-if="!wo.technicians?.length" class="py-12 text-center">
                    <p class="text-sm text-gray-400">Sin técnicos asignados</p>
                </div>
                <div v-else class="space-y-2">
                    <div
                        v-for="t in wo.technicians"
                        :key="t.id"
                        class="bg-white rounded-2xl border border-gray-100 p-4 flex items-center gap-3"
                    >
                        <div class="w-10 h-10 rounded-xl bg-indigo-50 flex items-center justify-center shrink-0">
                            <span class="text-sm font-bold text-indigo-600">{{ initials(t.user?.name) }}</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-900">{{ t.user?.name ?? 'Sin nombre' }}</p>
                            <p class="text-xs text-gray-400">{{ roleLabel[t.role] ?? t.role }}</p>
                        </div>
                        <div v-if="t.planned_hours != null" class="text-right shrink-0">
                            <p class="text-xs font-semibold text-gray-700">{{ t.planned_hours }} h</p>
                            <p class="text-[10px] text-gray-400">planif.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab: Partes -->
            <div v-else-if="activeTab === 'parts'">
                <div v-if="!wo.parts?.length" class="py-12 text-center">
                    <p class="text-sm text-gray-400">Sin partes registradas</p>
                </div>
                <div v-else class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
                    <div
                        v-for="p in wo.parts"
                        :key="p.id"
                        class="p-4 flex items-start gap-3 border-b border-gray-50 last:border-0"
                    >
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1">
                                <span v-if="p.part_code" class="font-mono text-[10px] bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded">{{ p.part_code }}</span>
                                <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full" :class="partStatusBadge[p.status]">{{ partStatusLabel[p.status] ?? p.status }}</span>
                            </div>
                            <p class="text-sm font-medium text-gray-900">{{ p.description }}</p>
                        </div>
                        <div class="text-right shrink-0">
                            <p class="text-sm font-semibold text-gray-900">{{ p.quantity }} {{ p.unit }}</p>
                            <p v-if="p.total_cost != null" class="text-xs text-gray-400">${{ p.total_cost.toFixed(2) }}</p>
                        </div>
                    </div>
                    <div v-if="wo.actual_cost_parts != null" class="p-4 flex justify-between items-center bg-gray-50">
                        <span class="text-sm font-semibold text-gray-700">Total partes</span>
                        <span class="text-sm font-bold text-gray-900">${{ wo.actual_cost_parts.toFixed(2) }}</span>
                    </div>
                </div>
            </div>

            <!-- Tab: Comentarios -->
            <div v-else-if="activeTab === 'comments'" class="space-y-3">

                <div v-if="wo.comments?.length" class="space-y-2">
                    <div
                        v-for="c in wo.comments"
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

                <div v-else class="py-6 text-center">
                    <p class="text-sm text-gray-400">Sin comentarios aún</p>
                </div>

                <!-- Compose -->
                <div class="bg-white rounded-2xl border border-gray-100 p-4">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3">Añadir comentario</p>
                    <textarea
                        v-model="newComment"
                        rows="3"
                        placeholder="Escribe un comentario..."
                        class="w-full text-sm border border-gray-200 rounded-xl px-3 py-2.5 resize-none focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent placeholder-gray-300"
                    />
                    <div class="flex items-center justify-between mt-2.5">
                        <label class="flex items-center gap-2 text-xs text-gray-500 cursor-pointer select-none">
                            <input type="checkbox" v-model="commentInternal" class="rounded border-gray-300" />
                            Nota interna
                        </label>
                        <button
                            @click="submitComment"
                            :disabled="!newComment.trim() || submittingComment"
                            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl disabled:opacity-50 transition-colors"
                        >
                            {{ submittingComment ? '…' : 'Enviar' }}
                        </button>
                    </div>
                    <p v-if="commentError" class="mt-2 text-xs text-red-600">{{ commentError }}</p>
                </div>

            </div>

        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, defineComponent, h } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { useApi } from '../composables/useApi.js'
import { useAuthStore } from '../stores/auth.js'

const route = useRoute()
const api = useApi()
const auth = useAuthStore()

const wo = ref(null)
const loading = ref(true)
const error = ref(null)
const activeTab = ref('details')
const transitioning = ref(false)
const transitionError = ref(null)
const newComment = ref('')
const commentInternal = ref(false)
const submittingComment = ref(false)
const commentError = ref(null)

// ── Label maps ────────────────────────────────────────────────────────────────

const statusLabel = {
    draft: 'Borrador', planned: 'Planificada', in_progress: 'En Ejecución',
    on_hold: 'En Espera', completed: 'Completada', verified: 'Verificada',
    closed: 'Cerrada', cancelled: 'Cancelada',
}
const statusBadge = {
    draft: 'bg-gray-100 text-gray-600',
    planned: 'bg-blue-100 text-blue-700',
    in_progress: 'bg-indigo-100 text-indigo-700',
    on_hold: 'bg-amber-100 text-amber-700',
    completed: 'bg-emerald-100 text-emerald-700',
    verified: 'bg-teal-100 text-teal-700',
    closed: 'bg-gray-100 text-gray-500',
    cancelled: 'bg-red-100 text-red-600',
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
const roleLabel = {
    lead_technician: 'Técnico líder', technician: 'Técnico', helper: 'Ayudante', inspector: 'Inspector',
}
const partStatusBadge = {
    requested: 'bg-blue-100 text-blue-700',
    reserved: 'bg-amber-100 text-amber-700',
    issued: 'bg-indigo-100 text-indigo-700',
    used: 'bg-emerald-100 text-emerald-700',
    returned: 'bg-gray-100 text-gray-600',
}
const partStatusLabel = {
    requested: 'Solicitada', reserved: 'Reservada', issued: 'Emitida', used: 'Usada', returned: 'Devuelta',
}

// ── Transition map ────────────────────────────────────────────────────────────

const transitionMap = {
    draft: [{ status: 'planned', label: 'Planificar', primary: true }],
    planned: [
        { status: 'in_progress', label: 'Iniciar', primary: true },
        { status: 'cancelled', label: 'Cancelar', primary: false },
    ],
    in_progress: [
        { status: 'completed', label: 'Completar', primary: true },
        { status: 'on_hold', label: 'Pausar', primary: false },
    ],
    on_hold: [
        { status: 'in_progress', label: 'Reanudar', primary: true },
        { status: 'cancelled', label: 'Cancelar', primary: false },
    ],
    completed: [
        { status: 'verified', label: 'Verificar', primary: true },
        { status: 'in_progress', label: 'Reabrir', primary: false },
    ],
    verified: [{ status: 'closed', label: 'Cerrar', primary: true }],
}

const primaryTransition = computed(() => transitionMap[wo.value?.status]?.find(t => t.primary) ?? null)
const secondaryTransitions = computed(() => transitionMap[wo.value?.status]?.filter(t => !t.primary) ?? [])

// ── Tabs ──────────────────────────────────────────────────────────────────────

const tabs = computed(() => [
    { key: 'details', label: 'Detalles' },
    { key: 'technicians', label: 'Técnicos', count: wo.value?.technicians?.length ?? null },
    { key: 'parts', label: 'Partes', count: wo.value?.parts?.length ?? null },
    { key: 'comments', label: 'Comentarios', count: wo.value?.comments?.length ?? null },
])

// ── Helpers ───────────────────────────────────────────────────────────────────

function initials(name) {
    if (!name) { return '?' }
    return name.split(' ').slice(0, 2).map(n => n[0]).join('').toUpperCase()
}

function formatDate(iso) {
    if (!iso) { return null }
    return new Date(iso).toLocaleString('es-MX', {
        day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
    })
}

function formatCurrency(amount, code) {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: code ?? 'MXN' }).format(amount)
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
        const res = await api.get(`work-orders/${route.params.id}`)
        wo.value = res?.data ?? res
    } catch (err) {
        error.value = err?.message ?? 'Error al cargar la orden de trabajo'
    } finally {
        loading.value = false
    }
}

async function transition(status) {
    transitioning.value = true
    transitionError.value = null
    try {
        const res = await api.patch(`work-orders/${wo.value.id}/status`, { status })
        wo.value = res?.data ?? res
    } catch (err) {
        transitionError.value = err?.message ?? 'Error al cambiar el estado'
    } finally {
        transitioning.value = false
    }
}

async function submitComment() {
    if (!newComment.value.trim()) { return }
    submittingComment.value = true
    commentError.value = null
    try {
        const res = await api.post(`work-orders/${wo.value.id}/comments`, {
            body: newComment.value.trim(),
            is_internal: commentInternal.value,
        })
        const raw = res?.data ?? res
        wo.value.comments = [
            ...(wo.value.comments ?? []),
            { ...raw, user: { name: auth.userName ?? 'Tú' } },
        ]
        newComment.value = ''
        commentInternal.value = false
    } catch (err) {
        commentError.value = err?.message ?? 'Error al enviar el comentario'
    } finally {
        submittingComment.value = false
    }
}

onMounted(load)
</script>
