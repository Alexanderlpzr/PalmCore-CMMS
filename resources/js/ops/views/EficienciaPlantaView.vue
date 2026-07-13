<template>
    <div class="p-5 lg:p-8 max-w-6xl mx-auto">

        <!-- Header -->
        <div class="flex items-start justify-between gap-4 mb-6">
            <div>
                <h1 class="text-xl font-bold text-gray-900">Eficiencia de planta</h1>
                <p class="text-sm text-gray-500 mt-0.5">
                    Horas efectivas sobre horas programadas. Sin calendario de producción no hay denominador,
                    y sin denominador no hay eficiencia.
                </p>
            </div>
            <select
                v-if="plants.length > 1"
                v-model="plantId"
                class="shrink-0 rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm"
            >
                <option v-for="plant in plants" :key="plant.id" :value="plant.id">{{ plant.name }}</option>
            </select>
        </div>

        <!-- KPI tiles -->
        <div v-if="loadingKpis" class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
            <div v-for="i in 4" :key="i" class="skeleton h-24 rounded-2xl" />
        </div>

        <div v-else class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Eficiencia</p>
                <p class="text-2xl font-bold mt-1" :class="efficiencyColor">
                    {{ kpis.efficiency_percentage != null ? kpis.efficiency_percentage.toFixed(2) + ' %' : '—' }}
                </p>
                <p v-if="kpis.efficiency_percentage == null" class="text-[11px] text-amber-600 mt-1">
                    El mes no tiene horas programadas.
                </p>
            </div>

            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Horas efectivas</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">{{ hours(kpis.effective_hours) }}</p>
                <p class="text-[11px] text-gray-500 mt-1">de {{ hours(kpis.programmed_hours) }} programadas</p>
            </div>

            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Horas perdidas</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">{{ hours(kpis.lost_hours) }}</p>
                <p class="text-[11px] text-gray-500 mt-1">
                    {{ hours(kpis.maintenance_lost_hours) }} de mantenimiento
                </p>
            </div>

            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">MTBF / MTTR planta</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">
                    {{ kpis.mtbf_hours != null ? kpis.mtbf_hours.toFixed(1) : '—' }}
                    <span class="text-sm font-medium text-gray-400">
                        / {{ kpis.mttr_hours != null ? kpis.mttr_hours.toFixed(1) : '—' }} h
                    </span>
                </p>
                <p class="text-[11px] text-gray-500 mt-1">{{ kpis.failure_count ?? 0 }} falla(s) de mantenimiento</p>
            </div>
        </div>

        <!-- Monthly history -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 mb-6">
            <h2 class="text-sm font-semibold text-gray-900 mb-4">Meses cerrados</h2>

            <div v-if="history.length" class="flex items-end gap-2 h-40">
                <div v-for="month in history" :key="month.period" class="flex-1 flex flex-col items-center gap-1.5 min-w-0">
                    <span class="text-[11px] font-semibold text-gray-700">
                        {{ month.efficiency_percentage != null ? Math.round(month.efficiency_percentage) + '%' : '—' }}
                    </span>
                    <div
                        class="w-full rounded-t-lg transition-all"
                        :class="month.efficiency_percentage >= 90 ? 'bg-emerald-500' : month.efficiency_percentage >= 80 ? 'bg-amber-500' : 'bg-red-500'"
                        :style="{ height: Math.max(4, (month.efficiency_percentage ?? 0)) + '%' }"
                    />
                    <span class="text-[10px] text-gray-500 truncate w-full text-center">{{ month.period }}</span>
                </div>
            </div>

            <p v-else class="text-sm text-gray-500">
                Aún no hay meses cerrados. El primero se congela automáticamente el día 1.
            </p>
        </div>

        <!-- Production calendar -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
            <div class="flex items-start justify-between gap-4 mb-4">
                <div>
                    <h2 class="text-sm font-semibold text-gray-900">Calendario de producción</h2>
                    <p class="text-xs text-gray-500 mt-0.5">
                        Horas que la planta debía correr cada día. Cero es un valor legítimo: un domingo sin fruta
                        no es un mal día, es un día que nunca se programó.
                    </p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <input v-model="month" type="month" class="rounded-xl border border-gray-200 px-3 py-2 text-sm" />
                    <button
                        @click="saveCalendar"
                        :disabled="savingCalendar"
                        class="px-4 py-2 rounded-xl bg-slate-900 text-white text-sm font-semibold disabled:opacity-40"
                    >
                        {{ savingCalendar ? 'Guardando…' : 'Guardar' }}
                    </button>
                </div>
            </div>

            <!-- Bulk fill -->
            <div class="flex flex-wrap items-center gap-2 mb-4 text-sm">
                <span class="text-gray-600">Llenar todos los días con</span>
                <input
                    v-model="bulkHours"
                    type="number"
                    step="0.1"
                    min="0"
                    max="24"
                    class="w-24 rounded-xl border border-gray-200 px-3 py-1.5 text-sm"
                />
                <span class="text-gray-600">h</span>
                <button @click="fillAll" class="px-3 py-1.5 rounded-xl border border-gray-200 text-xs font-semibold text-gray-700">
                    Aplicar
                </button>
                <span class="ml-auto text-gray-600">
                    Total del mes: <strong class="text-gray-900">{{ hours(calendarTotal) }}</strong>
                </span>
            </div>

            <div v-if="loadingCalendar" class="grid grid-cols-7 gap-2">
                <div v-for="i in 28" :key="i" class="skeleton h-16 rounded-xl" />
            </div>

            <div v-else class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-2">
                <div
                    v-for="day in days"
                    :key="day.date"
                    class="rounded-xl border p-2"
                    :class="isWeekend(day.date) ? 'border-gray-100 bg-gray-50' : 'border-gray-200'"
                >
                    <p class="text-[11px] font-medium text-gray-500 mb-1">{{ dayLabel(day.date) }}</p>
                    <input
                        v-model="day.hours"
                        type="number"
                        step="0.1"
                        min="0"
                        max="24"
                        class="w-full rounded-lg border border-gray-200 px-2 py-1.5 text-sm text-right"
                    />
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useApi } from '../composables/useApi.js'
import { useToast } from '../composables/useToast.js'

const api = useApi()
const toast = useToast()

const plants = ref([])
const plantId = ref('')
const kpis = ref({})
const history = ref([])
const days = ref([])
const month = ref(new Date().toISOString().slice(0, 7))
const bulkHours = ref(22.6)

const loadingKpis = ref(true)
const loadingCalendar = ref(true)
const savingCalendar = ref(false)

const efficiencyColor = computed(() => {
    const value = kpis.value.efficiency_percentage
    if (value == null) return 'text-gray-400'
    if (value >= 90) return 'text-emerald-600'
    if (value >= 80) return 'text-amber-600'
    return 'text-red-600'
})

const calendarTotal = computed(() =>
    days.value.reduce((sum, day) => sum + (Number(day.hours) || 0), 0),
)

function hours(value) {
    return `${Number(value ?? 0).toFixed(1)} h`
}

function isWeekend(date) {
    const weekday = new Date(`${date}T00:00:00`).getDay()
    return weekday === 0 || weekday === 6
}

function dayLabel(date) {
    return new Intl.DateTimeFormat('es', { weekday: 'short', day: '2-digit' })
        .format(new Date(`${date}T00:00:00`))
}

/** Every day of the selected month, pre-filled with whatever is already saved. */
function buildDays(saved) {
    const [year, monthNumber] = month.value.split('-').map(Number)
    const total = new Date(year, monthNumber, 0).getDate()
    const byDate = Object.fromEntries(saved.map((d) => [d.calendar_date, d.programmed_hours]))

    days.value = Array.from({ length: total }, (_, i) => {
        const date = `${month.value}-${String(i + 1).padStart(2, '0')}`
        return { date, hours: byDate[date] ?? '' }
    })
}

function fillAll() {
    for (const day of days.value) {
        day.hours = isWeekend(day.date) ? day.hours : bulkHours.value
    }
}

async function loadPlants() {
    const response = await api.get('plants')
    plants.value = response.data ?? []
    plantId.value = plants.value[0]?.id ?? ''
}

async function loadKpis() {
    if (!plantId.value) return
    loadingKpis.value = true
    try {
        const [current, past] = await Promise.all([
            api.get(`plants/${plantId.value}/kpis`),
            api.get(`plants/${plantId.value}/kpis/history`),
        ])
        kpis.value = current.data
        history.value = past.data ?? []
    } catch (e) {
        toast.error(e.message)
    } finally {
        loadingKpis.value = false
    }
}

async function loadCalendar() {
    if (!plantId.value) return
    loadingCalendar.value = true
    try {
        const response = await api.get(
            `plants/${plantId.value}/production-calendar?month=${month.value}-01`,
        )
        buildDays(response.data ?? [])
    } catch (e) {
        toast.error(e.message)
    } finally {
        loadingCalendar.value = false
    }
}

async function saveCalendar() {
    savingCalendar.value = true
    try {
        const payload = days.value
            .filter((day) => day.hours !== '' && day.hours != null)
            .map((day) => ({ calendar_date: day.date, programmed_hours: Number(day.hours) }))

        if (!payload.length) {
            toast.info('No hay días con horas programadas.')
            return
        }

        await api.put(`plants/${plantId.value}/production-calendar`, { days: payload })
        toast.success('Calendario guardado')
        await loadKpis()
    } catch (e) {
        toast.error(e.message)
    } finally {
        savingCalendar.value = false
    }
}

watch(plantId, () => {
    loadKpis()
    loadCalendar()
})
watch(month, loadCalendar)

onMounted(async () => {
    await loadPlants()
    await Promise.all([loadKpis(), loadCalendar()])
})
</script>
