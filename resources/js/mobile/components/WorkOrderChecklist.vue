<template>
    <div class="space-y-4">
        <!-- Progress -->
        <div class="bg-zinc-900 rounded-2xl p-4">
            <div class="flex items-center justify-between mb-2">
                <p class="text-xs font-medium text-zinc-400 uppercase tracking-wide">Trabajo a ejecutar</p>
                <p class="text-xs font-semibold" :class="isComplete ? 'text-green-400' : 'text-amber-400'">
                    {{ progress.resolved }} / {{ progress.total }}
                </p>
            </div>
            <div class="h-1.5 bg-zinc-800 rounded-full overflow-hidden">
                <div
                    class="h-full rounded-full transition-all duration-300"
                    :class="isComplete ? 'bg-green-500' : 'bg-amber-500'"
                    :style="{ width: progressPercent + '%' }"
                />
            </div>
            <p v-if="missingRequired > 0" class="text-xs text-zinc-400 mt-2">
                Faltan {{ missingRequired }} medición(es) obligatoria(s) para poder cerrar la OT.
            </p>
        </div>

        <!-- Tasks -->
        <div v-for="task in tasks" :key="task.id" class="bg-zinc-900 rounded-2xl overflow-hidden">
            <div class="px-4 py-4 border-b border-zinc-800">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-semibold text-zinc-100 text-sm leading-snug">{{ task.title }}</p>
                        <p v-if="task.description" class="text-xs text-zinc-400 mt-1">{{ task.description }}</p>
                        <p v-if="task.estimated_minutes" class="text-xs text-zinc-400 mt-1">
                            ~{{ task.estimated_minutes }} min
                        </p>
                    </div>
                    <span
                        class="shrink-0 text-[11px] font-semibold px-2 py-1 rounded-lg"
                        :class="statusClass(task.status)"
                    >
                        {{ task.status_label }}
                    </span>
                </div>
                <p v-if="task.skipped_reason" class="text-xs text-zinc-400 mt-2 italic">
                    Omitida: {{ task.skipped_reason }}
                </p>
            </div>

            <!-- Checklist items -->
            <div v-if="task.checklist.length" class="divide-y divide-zinc-800">
                <div v-for="item in task.checklist" :key="item.id" class="px-4 py-4 space-y-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm text-zinc-200 leading-snug">
                                {{ item.label }}
                                <span v-if="item.is_required" class="text-amber-400">*</span>
                            </p>
                            <p v-if="item.expected_range_label" class="text-xs text-zinc-400 mt-0.5">
                                Rango esperado: {{ item.expected_range_label }}
                            </p>
                        </div>
                        <span
                            v-if="item.is_out_of_range"
                            class="shrink-0 text-[11px] font-semibold px-2 py-1 rounded-lg bg-red-500/15 text-red-400"
                        >
                            Fuera de rango
                        </span>
                    </div>

                    <!-- Boolean -->
                    <div v-if="item.item_type === 'boolean'" class="grid grid-cols-2 gap-2">
                        <button
                            v-for="option in [{ v: true, l: 'Sí' }, { v: false, l: 'No' }]"
                            :key="String(option.v)"
                            :disabled="!canEdit(task) || saving === item.id"
                            @click="save(task, item, option.v)"
                            class="py-3 rounded-xl text-sm font-semibold transition active:scale-95 disabled:opacity-40"
                            :class="item.value_boolean === option.v
                                ? 'bg-amber-500 text-zinc-900'
                                : 'bg-zinc-800 text-zinc-300'"
                        >
                            {{ option.l }}
                        </button>
                    </div>

                    <!-- Numeric -->
                    <div v-else-if="item.item_type === 'numeric'" class="flex gap-2">
                        <input
                            v-model="drafts[item.id]"
                            type="number"
                            step="any"
                            inputmode="decimal"
                            :disabled="!canEdit(task)"
                            :placeholder="item.unit ? `Valor en ${item.unit}` : 'Valor'"
                            class="flex-1 min-w-0 bg-zinc-800 text-zinc-100 rounded-xl px-4 py-3 text-base border focus:outline-none disabled:opacity-40"
                            :class="item.is_out_of_range ? 'border-red-500/60' : 'border-zinc-700 focus:border-amber-500'"
                        />
                        <button
                            :disabled="!canEdit(task) || saving === item.id || drafts[item.id] === '' || drafts[item.id] == null"
                            @click="save(task, item, Number(drafts[item.id]))"
                            class="shrink-0 px-5 rounded-xl bg-amber-500 text-zinc-900 text-sm font-semibold active:scale-95 disabled:opacity-40 disabled:active:scale-100"
                        >
                            {{ saving === item.id ? '…' : 'Guardar' }}
                        </button>
                    </div>

                    <!-- Text -->
                    <div v-else class="flex gap-2">
                        <input
                            v-model="drafts[item.id]"
                            type="text"
                            :disabled="!canEdit(task)"
                            placeholder="Observación"
                            class="flex-1 min-w-0 bg-zinc-800 text-zinc-100 rounded-xl px-4 py-3 text-sm border border-zinc-700 focus:border-amber-500 focus:outline-none disabled:opacity-40"
                        />
                        <button
                            :disabled="!canEdit(task) || saving === item.id || !drafts[item.id]"
                            @click="save(task, item, drafts[item.id])"
                            class="shrink-0 px-5 rounded-xl bg-amber-500 text-zinc-900 text-sm font-semibold active:scale-95 disabled:opacity-40"
                        >
                            {{ saving === item.id ? '…' : 'Guardar' }}
                        </button>
                    </div>

                    <p v-if="item.is_answered" class="text-xs text-zinc-400">
                        Registrado: <span class="text-zinc-300 font-medium">{{ item.display_value }}</span>
                    </p>
                </div>
            </div>

            <!-- Task actions -->
            <div v-if="canEdit(task)" class="px-4 py-3 flex gap-2 bg-zinc-900/60">
                <button
                    @click="complete(task)"
                    :disabled="acting === task.id"
                    class="flex-1 py-3 rounded-xl bg-green-500/15 text-green-400 text-sm font-semibold active:scale-95 disabled:opacity-40"
                >
                    Marcar como hecha
                </button>
                <button
                    @click="askSkip(task)"
                    :disabled="acting === task.id"
                    class="px-4 py-3 rounded-xl bg-zinc-800 text-zinc-300 text-sm font-semibold active:scale-95 disabled:opacity-40"
                >
                    Omitir
                </button>
            </div>
        </div>

        <p v-if="!tasks.length && !loading" class="text-sm text-zinc-400 text-center py-6">
            Esta orden no tiene tareas de checklist.
        </p>

        <!-- Skip reason -->
        <BottomSheet v-model:open="showSkipSheet" title="Omitir tarea">
            <div class="px-5 py-4 space-y-4">
                <p class="text-sm text-zinc-400">
                    Una tarea omitida sin motivo es un preventivo que nadie puede auditar. Explica por qué no se ejecutó.
                </p>
                <textarea
                    v-model="skipReason"
                    rows="3"
                    placeholder="Ej.: sin repuesto en almacén"
                    class="w-full bg-zinc-800 text-zinc-100 rounded-xl px-4 py-3 text-sm border border-zinc-700 focus:border-amber-500 focus:outline-none resize-none"
                />
                <button
                    :disabled="skipReason.trim().length < 3"
                    @click="confirmSkip"
                    class="w-full py-4 rounded-2xl bg-amber-500 text-zinc-900 font-semibold active:scale-95 disabled:opacity-40"
                >
                    Omitir tarea
                </button>
            </div>
        </BottomSheet>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useApi } from '../composables/useApi.js'
import { usePendingActions } from '../composables/usePendingActions.js'
import { useToast } from '../composables/useToast.js'
import BottomSheet from './BottomSheet.vue'

const props = defineProps({
    workOrderId: { type: String, required: true },
})

const api = useApi()
const pendingActions = usePendingActions()
const toast = useToast()

const tasks = ref([])
const progress = ref({ resolved: 0, total: 0 })
const missingRequired = ref(0)
const loading = ref(true)
const saving = ref(null)
const acting = ref(null)
const drafts = reactive({})

const showSkipSheet = ref(false)
const skipReason = ref('')
const skipTarget = ref(null)

const progressPercent = computed(() =>
    progress.value.total ? Math.round((progress.value.resolved / progress.value.total) * 100) : 0,
)
const isComplete = computed(() => progress.value.total > 0 && progress.value.resolved === progress.value.total)

/** A resolved task is history: its answers are no longer editable. */
function canEdit(task) {
    return task.status === 'pending' || task.status === 'in_progress'
}

function statusClass(status) {
    return {
        pending: 'bg-zinc-800 text-zinc-400',
        in_progress: 'bg-blue-500/15 text-blue-400',
        done: 'bg-green-500/15 text-green-400',
        skipped: 'bg-zinc-800 text-zinc-400',
    }[status] ?? 'bg-zinc-800 text-zinc-400'
}

async function load() {
    loading.value = true
    try {
        const response = await api.get(`work-orders/${props.workOrderId}/tasks`)
        tasks.value = response.data
        progress.value = response.meta.progress
        missingRequired.value = response.meta.missing_required

        for (const task of tasks.value) {
            for (const item of task.checklist) {
                drafts[item.id] = item.value_numeric ?? item.value_text ?? ''
            }
        }
    } catch (e) {
        toast.error(e.message)
    } finally {
        loading.value = false
    }
}

async function save(task, item, value) {
    saving.value = item.id
    try {
        const result = await pendingActions.queueOrSubmitChecklistResult(
            props.workOrderId, task.id, item.id, value,
        )

        if (result.queued) {
            toast.info('Guardado localmente. Se enviará al recuperar señal.')
            // Optimistic: the técnico must see his own answer even with no signal.
            item.is_answered = true
            item.value_boolean = typeof value === 'boolean' ? value : item.value_boolean
            item.value_numeric = typeof value === 'number' ? value : item.value_numeric
            return
        }

        Object.assign(item, result.data.data)

        if (item.is_out_of_range) {
            toast.error(`${item.label}: ${item.display_value} está fuera del rango esperado.`)
        }

        await refreshMeta()
    } catch (e) {
        toast.error(e.message)
    } finally {
        saving.value = null
    }
}

async function complete(task) {
    acting.value = task.id
    try {
        const result = await pendingActions.queueOrSubmitTaskAction(props.workOrderId, task.id, 'complete')

        if (result.queued) {
            toast.info('Guardado localmente. Se enviará al recuperar señal.')
            return
        }

        toast.success('Tarea completada')
        await load()
    } catch (e) {
        // The 409 the server sends when a required measurement is missing is the
        // whole point of this screen — show it, don't swallow it.
        toast.error(e.message)
    } finally {
        acting.value = null
    }
}

function askSkip(task) {
    skipTarget.value = task
    skipReason.value = ''
    showSkipSheet.value = true
}

async function confirmSkip() {
    const task = skipTarget.value
    acting.value = task.id
    try {
        const result = await pendingActions.queueOrSubmitTaskAction(
            props.workOrderId, task.id, 'skip', skipReason.value.trim(),
        )
        showSkipSheet.value = false

        if (result.queued) {
            toast.info('Guardado localmente. Se enviará al recuperar señal.')
            return
        }

        toast.success('Tarea omitida')
        await load()
    } catch (e) {
        toast.error(e.message)
    } finally {
        acting.value = null
    }
}

async function refreshMeta() {
    const response = await api.get(`work-orders/${props.workOrderId}/tasks`)
    progress.value = response.meta.progress
    missingRequired.value = response.meta.missing_required
}

onMounted(load)
</script>
