<template>
    <SlidePanel :open="open" title="Cerrar misión" description="Registra el resultado antes de marcar esta orden como completada." @close="close">
        <div class="p-5 space-y-6">
            <!-- Validaciones -->
            <div v-if="blockingIssues.length" role="status" aria-live="polite" class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                <p class="text-xs font-bold text-amber-800 mb-2">Antes de cerrar, completa lo siguiente:</p>
                <ul class="space-y-1">
                    <li v-for="item in blockingIssues" :key="item.key" class="text-xs text-amber-700 flex items-start gap-1.5">
                        <span class="mt-0.5" aria-hidden="true">•</span>
                        <span>{{ item.hint }}</span>
                    </li>
                </ul>
            </div>
            <div v-else-if="nonBlockingIssues.length" class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                <p class="text-xs font-bold text-gray-600 mb-2">Recomendado, no obligatorio:</p>
                <ul class="space-y-1">
                    <li v-for="item in nonBlockingIssues" :key="item.key" class="text-xs text-gray-500 flex items-start gap-1.5">
                        <span class="mt-0.5" aria-hidden="true">•</span>
                        <span>{{ item.hint }}</span>
                    </li>
                </ul>
            </div>

            <!-- Estado final -->
            <div>
                <label id="final-state-label" class="text-xs font-semibold text-gray-700 block mb-2">¿Puede volver a operación?</label>
                <div role="radiogroup" aria-labelledby="final-state-label" class="grid grid-cols-3 gap-2">
                    <button
                        v-for="opt in finalStateOptions"
                        :key="opt.value"
                        @click="finalState = opt.value"
                        type="button"
                        role="radio"
                        :aria-checked="finalState === opt.value"
                        class="rounded-xl border-2 px-3 py-2.5 text-xs font-semibold transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2"
                        :class="finalState === opt.value
                            ? opt.activeClass
                            : 'border-gray-200 text-gray-500 hover:border-gray-300'"
                    >
                        {{ opt.label }}
                    </button>
                </div>
            </div>

            <!-- Resultado: esperado -> conseguido -->
            <div>
                <label for="completion-result" class="text-xs font-semibold text-gray-700 block mb-2">Resultado obtenido</label>
                <div class="rounded-xl border border-gray-100 bg-gray-50/70 px-3 py-2.5 mb-2">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">Objetivo esperado</p>
                    <p class="text-xs text-gray-600 mt-0.5">{{ expectedOutcome }}</p>
                </div>
                <textarea
                    id="completion-result"
                    v-model="resultText"
                    rows="4"
                    placeholder="Describe qué se hizo y cómo quedó el equipo..."
                    class="w-full text-sm border border-gray-200 rounded-xl px-3 py-2.5 resize-none focus:outline-none focus:ring-2 focus:ring-indigo-500 placeholder-gray-400"
                />
            </div>

            <!-- Resumen final -->
            <div class="rounded-xl border border-gray-100 divide-y divide-gray-50">
                <p class="text-xs font-bold text-gray-700 px-4 pt-3 pb-2">Resumen antes de confirmar</p>
                <div class="px-4 py-2 flex items-center justify-between text-xs">
                    <span class="text-gray-500">Equipo</span>
                    <span class="font-semibold text-gray-800">{{ summary.equipment ?? '—' }}</span>
                </div>
                <div class="px-4 py-2 flex items-center justify-between text-xs">
                    <span class="text-gray-500">Tiempo empleado</span>
                    <span class="font-semibold text-gray-800">{{ timeSpentLabel }}</span>
                </div>
                <div class="px-4 py-2 flex items-center justify-between text-xs">
                    <span class="text-gray-500">Evidencias</span>
                    <span class="font-semibold text-gray-800">{{ summary.evidence_count }}</span>
                </div>
                <div class="px-4 py-2 flex items-center justify-between text-xs">
                    <span class="text-gray-500">Checklist</span>
                    <span class="font-semibold text-gray-800">{{ summary.checklist_label }}</span>
                </div>
                <div class="px-4 py-2 flex items-center justify-between text-xs pb-3">
                    <span class="text-gray-500">Resultado</span>
                    <span class="font-semibold text-gray-800">{{ finalStateLabel }}</span>
                </div>
            </div>

            <p v-if="error" role="alert" aria-live="polite" class="text-xs text-red-600">{{ error }}</p>
        </div>

        <template #footer>
            <div class="px-5 py-4 border-t border-gray-100 flex justify-end gap-2">
                <button @click="close" class="px-4 py-2.5 text-xs font-semibold text-gray-600 hover:text-gray-900 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-400 rounded-lg">Volver</button>
                <button
                    @click="confirm"
                    :disabled="!canConfirm || submitting"
                    class="inline-flex items-center gap-2 px-5 py-3 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl disabled:opacity-40 transition-colors shadow-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2"
                    :class="{ 'ready-pulse': justBecameReady }"
                >
                    <svg v-if="submitting" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
                    {{ submitting ? 'Cerrando misión…' : 'Confirmar cierre de misión' }}
                </button>
            </div>
        </template>
    </SlidePanel>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import SlidePanel from '../SlidePanel.vue'

const props = defineProps({
    open: { type: Boolean, required: true },
    workOrder: { type: Object, required: true },
    submitting: { type: Boolean, default: false },
    error: { type: String, default: null },
})

const emit = defineEmits(['close', 'completed'])

const finalStateOptions = [
    { value: 'operational', label: 'Sí', activeClass: 'border-emerald-500 bg-emerald-50 text-emerald-700' },
    { value: 'partial', label: 'Parcialmente', activeClass: 'border-amber-500 bg-amber-50 text-amber-700' },
    { value: 'not_operational', label: 'No', activeClass: 'border-red-500 bg-red-50 text-red-700' },
]

const finalState = ref('operational')
const resultText = ref('')

watch(() => props.open, (isOpen) => {
    if (isOpen) {
        finalState.value = 'operational'
        resultText.value = props.workOrder.work_performed ?? ''
    }
})

const readiness = computed(() => props.workOrder.mission?.completion?.readiness ?? [])
const summary = computed(() => props.workOrder.mission?.completion?.summary ?? {})
const expectedOutcome = computed(() => summary.value.expected_outcome ?? props.workOrder.mission?.expected_outcome ?? '—')

// Server-derived readiness reflects the last saved state; a filled-in
// resultText in this very form also satisfies "result" even before saving,
// so the button doesn't stay disabled while the technician is mid-typing.
const liveReadiness = computed(() => readiness.value.map(item =>
    item.key === 'result' ? { ...item, satisfied: item.satisfied || resultText.value.trim().length > 0 } : item
))
const blockingIssues = computed(() => liveReadiness.value.filter(i => i.blocking && !i.satisfied))
const nonBlockingIssues = computed(() => liveReadiness.value.filter(i => !i.blocking && !i.satisfied))
const canConfirm = computed(() => blockingIssues.value.length === 0)

// A one-shot pulse the instant the confirm button flips from disabled to
// enabled — acknowledges the moment the mission is actually ready to close.
const justBecameReady = ref(false)
watch(canConfirm, (ready, wasReady) => {
    if (ready && !wasReady) {
        justBecameReady.value = true
        setTimeout(() => { justBecameReady.value = false }, 650)
    }
})

const finalStateLabel = computed(() => finalStateOptions.find(o => o.value === finalState.value)?.label ?? '—')
const timeSpentLabel = computed(() => summary.value.time_spent_hours != null ? `${summary.value.time_spent_hours} h` : '—')

function close() {
    emit('close')
}

function confirm() {
    if (!canConfirm.value) return
    emit('completed', {
        work_performed: `[Estado final: ${finalStateLabel.value}]\n\n${resultText.value.trim()}`,
    })
}
</script>
