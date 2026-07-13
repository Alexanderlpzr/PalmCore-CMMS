<template>
    <div class="p-5 lg:p-8 max-w-5xl mx-auto">

        <!-- Header -->
        <div class="flex items-start justify-between gap-4 mb-6">
            <div>
                <h1 class="text-xl font-bold text-gray-900">Ronda de horómetros</h1>
                <p class="text-sm text-gray-500 mt-0.5">
                    Escribe la lectura de cada dial. Si el horómetro fue cambiado, anota lo que marca ahora:
                    el sistema lo reconoce como reemplazo y no pierde las horas anteriores.
                </p>
            </div>
            <button
                @click="submit"
                :disabled="submitting || !filledCount"
                class="shrink-0 px-4 py-2 rounded-xl bg-slate-900 text-white text-sm font-semibold disabled:opacity-40"
            >
                {{ submitting ? 'Guardando…' : `Guardar ronda (${filledCount})` }}
            </button>
        </div>

        <!-- Search -->
        <input
            v-model="search"
            type="search"
            placeholder="Filtrar equipos…"
            class="w-full sm:w-80 mb-5 rounded-xl border border-gray-200 px-3.5 py-2 text-sm"
        />

        <!-- Failures from the last round -->
        <div v-if="failed.length" class="mb-5 bg-red-50 border border-red-100 rounded-2xl p-4">
            <p class="text-sm font-semibold text-red-700 mb-1">
                {{ failed.length }} lectura(s) no se pudieron guardar
            </p>
            <ul class="text-xs text-red-600 space-y-0.5">
                <li v-for="f in failed" :key="f.equipment_id">
                    {{ nameOf(f.equipment_id) }}: {{ f.error }}
                </li>
            </ul>
        </div>

        <!-- Skeleton -->
        <div v-if="loading" class="space-y-2">
            <div v-for="i in 6" :key="i" class="skeleton h-14 rounded-xl" />
        </div>

        <!-- Rows -->
        <div v-else class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="hidden sm:grid grid-cols-12 gap-3 px-4 py-2.5 bg-gray-50 border-b border-gray-100 text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                <div class="col-span-5">Equipo</div>
                <div class="col-span-2 text-right">Lectura actual</div>
                <div class="col-span-2 text-right">Acumulado</div>
                <div class="col-span-3 text-right">Nueva lectura</div>
            </div>

            <div
                v-for="item in visible"
                :key="item.id"
                class="grid grid-cols-1 sm:grid-cols-12 gap-2 sm:gap-3 px-4 py-3 border-b border-gray-100 last:border-0 items-center"
            >
                <div class="sm:col-span-5 min-w-0">
                    <p class="text-sm font-medium text-gray-900 truncate">{{ item.name }}</p>
                    <p class="text-xs text-gray-500">{{ item.code }}</p>
                </div>

                <div class="sm:col-span-2 text-left sm:text-right text-sm text-gray-600">
                    {{ item.current_meter_reading != null ? formatNumber(item.current_meter_reading) : '—' }}
                </div>

                <div class="sm:col-span-2 text-left sm:text-right text-sm text-gray-500">
                    {{ formatNumber(item.accumulated_meter_reading ?? 0) }}
                </div>

                <div class="sm:col-span-3">
                    <input
                        v-model="readings[item.id]"
                        type="number"
                        step="0.1"
                        min="0"
                        inputmode="decimal"
                        placeholder="—"
                        class="w-full rounded-xl border px-3 py-2 text-sm text-right"
                        :class="isReset(item) ? 'border-amber-400 bg-amber-50' : 'border-gray-200'"
                    />
                    <p v-if="isReset(item)" class="text-[11px] text-amber-700 mt-1 text-right">
                        Menor que la actual → se registrará como cambio de horómetro.
                    </p>
                </div>
            </div>

            <p v-if="!visible.length" class="text-sm text-gray-500 text-center py-10">
                Ningún equipo coincide con el filtro.
            </p>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useApi } from '../composables/useApi.js'
import { useToast } from '../composables/useToast.js'

const api = useApi()
const toast = useToast()

const equipment = ref([])
const readings = reactive({})
const failed = ref([])
const search = ref('')
const loading = ref(true)
const submitting = ref(false)

const visible = computed(() => {
    const term = search.value.trim().toLowerCase()
    if (!term) return equipment.value

    return equipment.value.filter(
        (e) => e.name?.toLowerCase().includes(term) || e.code?.toLowerCase().includes(term),
    )
})

const filledCount = computed(() =>
    Object.values(readings).filter((v) => v !== '' && v != null).length,
)

/**
 * A reading below the current dial is not a typo — it is a meter that was
 * replaced. Warn, but never block: this is exactly the data the old system
 * refused to accept.
 */
function isReset(item) {
    const value = readings[item.id]
    if (value === '' || value == null || item.current_meter_reading == null) return false

    return Number(value) < Number(item.current_meter_reading)
}

function nameOf(equipmentId) {
    return equipment.value.find((e) => e.id === equipmentId)?.name ?? equipmentId
}

function formatNumber(value) {
    return new Intl.NumberFormat('es', { maximumFractionDigits: 1 }).format(value)
}

async function load() {
    loading.value = true
    try {
        const response = await api.get('equipment?per_page=200')
        equipment.value = response.data ?? []
        for (const item of equipment.value) readings[item.id] = ''
    } catch (e) {
        toast.error(e.message)
    } finally {
        loading.value = false
    }
}

async function submit() {
    const payload = Object.entries(readings)
        .filter(([, value]) => value !== '' && value != null)
        .map(([equipment_id, value]) => ({ equipment_id, reading_value: Number(value) }))

    if (!payload.length) return

    submitting.value = true
    failed.value = []

    try {
        const response = await api.post(
            'meter-readings/bulk',
            { readings: payload },
            { 'Idempotency-Key': crypto.randomUUID() },
        )

        failed.value = response.meta.failed ?? []

        const saved = response.meta.recorded
        if (failed.value.length) {
            toast.info(`${saved} lectura(s) guardadas, ${failed.value.length} con error.`)
        } else {
            toast.success(`${saved} lectura(s) guardadas.`)
        }

        for (const key of Object.keys(readings)) readings[key] = ''
        await load()
    } catch (e) {
        toast.error(e.message)
    } finally {
        submitting.value = false
    }
}

onMounted(load)
</script>
