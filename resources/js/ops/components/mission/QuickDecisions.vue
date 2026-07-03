<template>
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
        <h2 class="text-xs font-bold uppercase tracking-widest text-gray-500 mb-4">Decisiones rápidas</h2>

        <Transition name="fade" mode="out-in">
            <div :key="workOrder.status" class="flex flex-col sm:flex-row sm:items-center gap-2.5 flex-wrap">
                <button
                    v-if="primary"
                    @click="run(primary)"
                    :disabled="transitioning"
                    class="inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 transition-colors shadow-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2"
                >
                    <svg v-if="transitioning" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
                    {{ primary.label }}
                </button>

                <button
                    v-for="action in secondary"
                    :key="action.key"
                    @click="run(action)"
                    :disabled="transitioning"
                    class="inline-flex items-center justify-center gap-1.5 px-4 py-3 rounded-xl text-sm font-semibold border transition-colors disabled:opacity-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2"
                    :class="action.tone === 'danger'
                        ? 'border-red-200 text-red-600 hover:bg-red-50 focus-visible:ring-red-500'
                        : 'border-gray-200 text-gray-700 hover:bg-gray-50 focus-visible:ring-indigo-500'"
                >
                    {{ action.label }}
                </button>
            </div>
        </Transition>

        <!-- Confirmación inline de cancelación — sin diálogo nativo, en el propio flujo -->
        <div v-if="confirmingCancel" role="alert" class="mt-3 flex items-center gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3">
            <p class="text-xs text-red-700 flex-1">¿Confirmas cancelar esta orden de trabajo? Esta acción no se puede deshacer.</p>
            <button @click="confirmingCancel = false" class="text-xs font-semibold text-gray-500 hover:text-gray-700 shrink-0 focus:outline-none focus-visible:ring-2 focus-visible:ring-gray-400 rounded">Volver</button>
            <button @click="run({ status: 'cancelled' })" :disabled="transitioning" class="text-xs font-semibold text-white bg-red-600 hover:bg-red-700 px-3 py-1.5 rounded-lg disabled:opacity-50 shrink-0 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2">
                Sí, cancelar
            </button>
        </div>

        <p v-if="transitionError" role="alert" aria-live="polite" class="mt-3 text-xs text-red-600">{{ transitionError }}</p>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
    workOrder: { type: Object, required: true },
    transitioning: { type: Boolean, default: false },
    transitionError: { type: String, default: null },
})

const emit = defineEmits(['transition', 'open-completion', 'open-evidence', 'open-support', 'download-pdf'])

const confirmingCancel = ref(false)

// Presentation-only mapping — the real authority for what's allowed is
// WorkOrderStatus::allowedTransitions() on the backend, which rejects
// anything else. This only decides which of those allowed moves are
// surfaced, and how, per the brief's "no more wondering which button" goal.
const groups = computed(() => {
    const status = props.workOrder.status

    if (status === 'draft') {
        return { primary: { status: 'planned', label: 'Planificar' }, secondary: [] }
    }
    if (status === 'planned') {
        return {
            primary: { status: 'in_progress', label: 'Iniciar misión' },
            secondary: [{ key: 'cancel', label: 'Cancelar', tone: 'danger', cancel: true }],
        }
    }
    if (status === 'in_progress') {
        return {
            primary: { key: 'complete', label: 'Finalizar misión', complete: true },
            secondary: [
                { key: 'pause', status: 'on_hold', label: 'Pausar' },
                { key: 'evidence', label: 'Registrar evidencia', evidence: true },
                { key: 'support', label: 'Solicitar apoyo', support: true },
                { key: 'cancel', label: 'Cancelar', tone: 'danger', cancel: true },
            ],
        }
    }
    if (status === 'on_hold') {
        return {
            primary: { status: 'in_progress', label: 'Reanudar' },
            secondary: [{ key: 'cancel', label: 'Cancelar', tone: 'danger', cancel: true }],
        }
    }
    if (status === 'completed') {
        return {
            primary: { status: 'verified', label: 'Verificar' },
            secondary: [
                { key: 'reopen', status: 'in_progress', label: 'Reabrir' },
                { key: 'pdf', label: 'Descargar PDF', pdf: true },
            ],
        }
    }
    if (status === 'verified') {
        return {
            primary: { status: 'closed', label: 'Cerrar' },
            secondary: [{ key: 'pdf', label: 'Descargar PDF', pdf: true }],
        }
    }
    if (status === 'closed') {
        return { primary: null, secondary: [{ key: 'pdf', label: 'Descargar PDF', pdf: true }] }
    }
    return { primary: null, secondary: [] }
})

const primary = computed(() => groups.value.primary)
const secondary = computed(() => groups.value.secondary)

function run(action) {
    if (action.cancel) {
        confirmingCancel.value = true
        return
    }
    if (action.status === 'cancelled') {
        confirmingCancel.value = false
        emit('transition', 'cancelled')
        return
    }
    if (action.complete) {
        emit('open-completion')
        return
    }
    if (action.evidence) {
        emit('open-evidence')
        return
    }
    if (action.support) {
        emit('open-support')
        return
    }
    if (action.pdf) {
        emit('download-pdf')
        return
    }
    if (action.status) {
        emit('transition', action.status)
    }
}
</script>
