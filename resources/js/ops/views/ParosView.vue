<template>
    <div class="p-5 lg:p-8 max-w-6xl mx-auto">

        <!-- Header -->
        <div class="flex items-start justify-between gap-4 mb-6">
            <div>
                <h1 class="text-xl font-bold text-gray-900">Paros</h1>
                <p class="text-sm text-gray-500 mt-0.5">
                    La mayoría de los paros no genera una orden de trabajo. Aquí se registran todos.
                </p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <!-- El reporte de horas perdidas sale del sistema, no de esta pantalla -->
                <button
                    v-if="plantId"
                    @click="downloadLostHours"
                    :disabled="downloadingReport"
                    class="px-4 py-2 rounded-xl bg-white border border-gray-200 text-gray-700 text-sm font-semibold hover:bg-gray-50 disabled:opacity-50 transition-colors"
                >
                    {{ downloadingReport ? 'Generando…' : 'Horas perdidas (PDF)' }}
                </button>
                <button
                    @click="openRegister"
                    class="px-4 py-2 rounded-xl bg-slate-900 text-white text-sm font-semibold hover:bg-slate-800 transition-colors"
                >
                    Registrar paro
                </button>
            </div>
        </div>

        <!-- Plant selector -->
        <div v-if="plants.length > 1" class="mb-5">
            <select v-model="plantId" class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm">
                <option v-for="plant in plants" :key="plant.id" :value="plant.id">{{ plant.name }}</option>
            </select>
        </div>

        <!-- Lost hours by Tipo I -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 mb-6">
            <div class="flex items-baseline justify-between mb-4">
                <h2 class="text-sm font-semibold text-gray-900">Horas perdidas este mes</h2>
                <p class="text-2xl font-bold text-gray-900">{{ formatHours(lostHours.total_hours) }}</p>
            </div>

            <div v-if="loadingLost" class="space-y-2">
                <div v-for="i in 3" :key="i" class="skeleton h-6 rounded" />
            </div>

            <div v-else-if="lostHours.by_category?.length" class="space-y-2.5">
                <div v-for="row in lostHours.by_category" :key="row.category">
                    <div class="flex items-center justify-between text-xs mb-1">
                        <span class="font-medium text-gray-700">
                            {{ row.label }}
                            <span
                                v-if="row.is_maintenance_responsibility"
                                class="ml-1.5 text-[10px] font-semibold text-slate-500 bg-slate-100 rounded px-1.5 py-0.5"
                            >mantenimiento</span>
                        </span>
                        <span class="font-semibold text-gray-900">{{ formatHours(row.hours) }}</span>
                    </div>
                    <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                        <div
                            class="h-full rounded-full"
                            :class="row.is_maintenance_responsibility ? 'bg-red-500' : 'bg-slate-400'"
                            :style="{ width: barWidth(row.hours) + '%' }"
                        />
                    </div>
                </div>
                <p class="text-xs text-gray-500 pt-2">
                    En rojo, lo que mantenimiento debe. En gris, lo que la planta sufre pero no le corresponde.
                </p>
            </div>

            <p v-else class="text-sm text-gray-500">Sin paros registrados este mes.</p>
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap gap-2 mb-4">
            <button
                v-for="f in filters"
                :key="f.value"
                @click="activeFilter = f.value"
                class="px-3.5 py-1.5 rounded-full text-xs font-semibold transition-colors"
                :class="activeFilter === f.value
                    ? 'bg-slate-900 text-white'
                    : 'bg-white border border-gray-200 text-gray-600 hover:border-gray-300'"
            >
                {{ f.label }}
            </button>
        </div>

        <!-- Events -->
        <div v-if="loading" class="space-y-3">
            <div v-for="i in 4" :key="i" class="bg-white rounded-2xl border border-gray-100 p-4">
                <div class="skeleton h-4 w-1/3 rounded mb-2" />
                <div class="skeleton h-3 w-1/2 rounded" />
            </div>
        </div>

        <div v-else-if="events.length" class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <div v-for="event in events" :key="event.id" class="p-4 border-b border-gray-100 last:border-0">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-sm font-semibold text-gray-900">
                                {{ event.stoppage_category_label ?? 'Sin clasificar' }}
                            </span>
                            <span
                                v-if="event.is_ongoing"
                                class="text-[11px] font-semibold text-red-600 bg-red-50 rounded-lg px-2 py-0.5"
                            >En curso</span>
                            <span
                                v-if="!event.affects_production"
                                class="text-[11px] font-semibold text-gray-500 bg-gray-100 rounded-lg px-2 py-0.5"
                            >Sin impacto en producción</span>
                        </div>
                        <p class="text-sm text-gray-600 mt-0.5">
                            {{ event.stoppage_cause || 'Sin causa específica' }}
                        </p>
                        <p class="text-xs text-gray-500 mt-1">
                            {{ event.equipment?.name ?? 'Paro de planta' }}
                            · {{ formatDateTime(event.started_at) }}
                            <span v-if="event.work_order_number"> · OT {{ event.work_order_number }}</span>
                        </p>
                    </div>

                    <div class="shrink-0 text-right">
                        <p class="text-sm font-semibold text-gray-900">
                            {{ event.duration_minutes != null ? formatDuration(event.duration_minutes) : '—' }}
                        </p>
                        <button
                            v-if="event.is_ongoing"
                            @click="closeEvent(event)"
                            :disabled="closing === event.id"
                            class="mt-1 text-xs font-semibold text-slate-700 hover:text-slate-900 disabled:opacity-40"
                        >
                            {{ closing === event.id ? 'Cerrando…' : 'Cerrar paro' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <p v-else class="text-sm text-gray-500 text-center py-10">No hay paros registrados.</p>

        <!-- Register modal -->
        <div v-if="showRegister" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40" @click.self="showRegister = false">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="font-semibold text-gray-900">Registrar paro</h2>
                </div>

                <form @submit.prevent="submit" class="p-5 space-y-4">
                    <!-- Scope -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">¿Qué paró?</label>
                        <div class="flex gap-1 p-1 bg-gray-100 rounded-xl w-fit mb-2">
                            <button
                                v-for="s in scopes"
                                :key="s.value"
                                type="button"
                                @click="form.scope = s.value"
                                class="px-4 py-1.5 rounded-lg text-xs font-semibold transition-colors"
                                :class="form.scope === s.value ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500'"
                            >
                                {{ s.label }}
                            </button>
                        </div>
                        <select
                            v-if="form.scope === 'equipment'"
                            v-model="form.equipment_id"
                            required
                            class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm"
                        >
                            <option value="">Selecciona el equipo…</option>
                            <option v-for="e in equipment" :key="e.id" :value="e.id">
                                {{ e.code }} — {{ e.name }}
                            </option>
                        </select>
                        <p v-else class="text-xs text-gray-500">
                            Paro de planta: falta de fruta, corte de energía, esperas de proceso. Ningún equipo falló.
                        </p>
                    </div>

                    <!-- Tipo I -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Tipo I — clasificación</label>
                        <select v-model="form.stoppage_category" required class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm">
                            <option value="">Selecciona…</option>
                            <option v-for="(label, value) in categories" :key="value" :value="value">{{ label }}</option>
                        </select>
                    </div>

                    <!-- Tipo II -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">
                            Tipo II — causa específica <span class="font-normal text-gray-400">(opcional)</span>
                        </label>
                        <input
                            v-model="form.stoppage_cause"
                            type="text"
                            maxlength="120"
                            placeholder="Ej.: atasco en prensa 2"
                            class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm"
                        />
                    </div>

                    <!-- Times -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Inicio</label>
                            <input v-model="form.started_at" type="datetime-local" required class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">
                                Fin <span class="font-normal text-gray-400">(si ya terminó)</span>
                            </label>
                            <input v-model="form.ended_at" type="datetime-local" class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm" />
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input v-model="form.affects_production" type="checkbox" class="rounded border-gray-300" />
                        Restó horas de producción
                    </label>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Notas</label>
                        <textarea v-model="form.notes" rows="2" class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm resize-none" />
                    </div>

                    <div class="flex gap-2 pt-2">
                        <button type="button" @click="showRegister = false" class="flex-1 py-2.5 rounded-xl border border-gray-200 text-sm font-semibold text-gray-700">
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="submitting"
                            class="flex-1 py-2.5 rounded-xl bg-slate-900 text-white text-sm font-semibold disabled:opacity-40"
                        >
                            {{ submitting ? 'Guardando…' : 'Registrar' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useApi } from '../composables/useApi.js'
import { useToast } from '../composables/useToast.js'

const api = useApi()
const toast = useToast()

// Tipo I — mirrors App\Domain\Assets\Enums\StoppageCategory.
const categories = {
    mechanical: 'Mecánico',
    electrical: 'Eléctrico',
    instrumentation: 'Instrumentación',
    process: 'Proceso',
    operational: 'Operacional',
    raw_material: 'Falta de fruta',
    utilities: 'Servicios industriales',
    external: 'Externo',
    planned: 'Programado',
    other: 'Otro',
}

const scopes = [
    { value: 'equipment', label: 'Un equipo' },
    { value: 'plant', label: 'Toda la planta' },
]

const filters = [
    { value: 'all', label: 'Todos' },
    { value: 'ongoing', label: 'En curso' },
    { value: 'maintenance', label: 'De mantenimiento' },
]

const plants = ref([])
const equipment = ref([])
const events = ref([])
const lostHours = ref({ total_hours: 0, by_category: [] })
const plantId = ref('')
const activeFilter = ref('all')
const loading = ref(true)
const loadingLost = ref(true)
const submitting = ref(false)
const closing = ref(null)
const showRegister = ref(false)

const form = reactive({
    scope: 'equipment',
    equipment_id: '',
    stoppage_category: '',
    stoppage_cause: '',
    started_at: '',
    ended_at: '',
    affects_production: true,
    notes: '',
})

const maxHours = computed(() =>
    Math.max(...(lostHours.value.by_category ?? []).map((r) => r.hours), 1),
)

function barWidth(hours) {
    return Math.round((hours / maxHours.value) * 100)
}

function formatHours(hours) {
    return `${Number(hours ?? 0).toFixed(1)} h`
}

function formatDuration(minutes) {
    const h = Math.floor(minutes / 60)
    const m = minutes % 60
    return h > 0 ? `${h}h ${m}min` : `${m}min`
}

function formatDateTime(iso) {
    if (!iso) return '—'
    return new Intl.DateTimeFormat('es', {
        day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit',
    }).format(new Date(iso))
}

function openRegister() {
    Object.assign(form, {
        scope: 'equipment',
        equipment_id: '',
        stoppage_category: '',
        stoppage_cause: '',
        started_at: toLocalInput(new Date()),
        ended_at: '',
        affects_production: true,
        notes: '',
    })
    showRegister.value = true
}

/** datetime-local wants "YYYY-MM-DDTHH:mm" in local time, not an ISO string. */
function toLocalInput(date) {
    const pad = (n) => String(n).padStart(2, '0')
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}

async function loadPlants() {
    const response = await api.get('plants')
    plants.value = response.data ?? []
    plantId.value = plants.value[0]?.id ?? ''
}

async function loadEquipment() {
    const response = await api.get('equipment?per_page=200')
    equipment.value = response.data ?? []
}

async function loadEvents() {
    loading.value = true
    try {
        const params = new URLSearchParams({ per_page: '50' })
        if (plantId.value) params.set('plant_id', plantId.value)
        if (activeFilter.value === 'ongoing') params.set('ongoing', '1')

        const response = await api.get(`downtime-events?${params}`)
        let rows = response.data ?? []

        if (activeFilter.value === 'maintenance') {
            rows = rows.filter((e) => e.is_maintenance_responsibility)
        }

        events.value = rows
    } catch (e) {
        toast.error(e.message)
    } finally {
        loading.value = false
    }
}

const downloadingReport = ref(false)

async function downloadLostHours() {
    if (!plantId.value || downloadingReport.value) return
    downloadingReport.value = true
    try {
        const month = new Date().toISOString().slice(0, 7)
        await api.download(`reports/lost-hours/${plantId.value}`, `HORAS-PERDIDAS-${month}.pdf`)
    } catch (e) {
        toast.error('No se pudo generar el reporte de horas perdidas')
    } finally {
        downloadingReport.value = false
    }
}

async function loadLostHours() {
    if (!plantId.value) return
    loadingLost.value = true
    try {
        const response = await api.get(`plants/${plantId.value}/lost-hours`)
        lostHours.value = response.data
    } catch (e) {
        toast.error(e.message)
    } finally {
        loadingLost.value = false
    }
}

async function submit() {
    submitting.value = true
    try {
        await api.post('downtime-events', {
            ...(form.scope === 'equipment'
                ? { equipment_id: form.equipment_id }
                : { plant_id: plantId.value }),
            stoppage_category: form.stoppage_category,
            stoppage_cause: form.stoppage_cause || null,
            started_at: new Date(form.started_at).toISOString(),
            ended_at: form.ended_at ? new Date(form.ended_at).toISOString() : null,
            affects_production: form.affects_production,
            notes: form.notes || null,
        }, { 'Idempotency-Key': crypto.randomUUID() })

        toast.success('Paro registrado')
        showRegister.value = false
        await Promise.all([loadEvents(), loadLostHours()])
    } catch (e) {
        toast.error(e.message)
    } finally {
        submitting.value = false
    }
}

async function closeEvent(event) {
    closing.value = event.id
    try {
        await api.patch(`downtime-events/${event.id}/end`, {})
        toast.success('Paro cerrado')
        await Promise.all([loadEvents(), loadLostHours()])
    } catch (e) {
        toast.error(e.message)
    } finally {
        closing.value = null
    }
}

watch([plantId, activeFilter], () => {
    loadEvents()
    loadLostHours()
})

onMounted(async () => {
    await loadPlants()
    await Promise.all([loadEquipment(), loadEvents(), loadLostHours()])
})
</script>
