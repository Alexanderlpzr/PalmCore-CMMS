<template>
    <Transition name="bulk">
        <div v-if="count > 0" class="fixed bottom-4 left-1/2 -translate-x-1/2 z-40 w-[calc(100%-2rem)] max-w-2xl">
            <div class="bg-slate-900 text-white rounded-2xl shadow-2xl border border-slate-700 px-3 py-2 flex items-center gap-2">

                <!-- Confirmation step -->
                <template v-if="pending">
                    <span class="text-sm px-2 flex-1">
                        ¿{{ pending.verb }} <strong>{{ count }}</strong> elemento{{ count !== 1 ? 's' : '' }}?
                    </span>
                    <button
                        @click="pending = null"
                        class="px-3 py-1.5 rounded-lg text-sm font-semibold text-slate-300 hover:bg-slate-800 transition-colors"
                    >Cancelar</button>
                    <button
                        @click="confirm"
                        class="px-3 py-1.5 rounded-lg text-sm font-semibold text-white transition-colors"
                        :class="pending.danger ? 'bg-red-600 hover:bg-red-700' : 'bg-emerald-600 hover:bg-emerald-700'"
                    >Confirmar</button>
                </template>

                <!-- Selection + actions -->
                <template v-else>
                    <span class="text-sm font-semibold px-2 shrink-0">{{ count }} seleccionado{{ count !== 1 ? 's' : '' }}</span>
                    <div class="w-px h-5 bg-slate-700 shrink-0" />

                    <div class="flex items-center gap-1 flex-1 overflow-x-auto">
                        <div v-for="action in actions" :key="action.key" class="relative shrink-0">
                            <button
                                @click="onAction(action)"
                                class="px-3 py-1.5 rounded-lg text-sm font-medium text-slate-200 hover:bg-slate-800 transition-colors flex items-center gap-1"
                            >
                                {{ action.label }}
                                <span v-if="action.options" class="text-xs text-slate-500">▾</span>
                            </button>

                            <!-- Options popover -->
                            <div
                                v-if="action.options && openKey === action.key"
                                class="absolute bottom-full left-0 mb-1 bg-white text-gray-800 rounded-xl shadow-xl border border-gray-100 py-1 min-w-36 z-10"
                            >
                                <button
                                    v-for="opt in action.options"
                                    :key="opt.value"
                                    @click="choose(action, opt)"
                                    class="w-full text-left px-3 py-1.5 text-sm hover:bg-gray-50 transition-colors"
                                >{{ opt.label }}</button>
                            </div>
                        </div>
                    </div>

                    <button
                        @click="emit('clear')"
                        class="px-2.5 py-1.5 rounded-lg text-sm font-medium text-slate-400 hover:bg-slate-800 transition-colors shrink-0"
                    >Limpiar</button>
                </template>
            </div>
        </div>
    </Transition>
</template>

<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
    count: { type: Number, default: 0 },
    // [{ key, label, danger?, options?: [{ value, label }] }]
    actions: { type: Array, default: () => [] },
})

const emit = defineEmits(['apply', 'clear'])

const pending = ref(null)
const openKey = ref(null)

function onAction(action) {
    if (action.options) {
        openKey.value = openKey.value === action.key ? null : action.key
        return
    }
    choose(action, null)
}

function choose(action, opt) {
    openKey.value = null
    pending.value = {
        action: action.key,
        value: opt ? opt.value : null,
        danger: !! action.danger,
        verb: opt ? `${action.label}: ${opt.label} en` : `${action.label}`,
    }
}

function confirm() {
    emit('apply', { action: pending.value.action, value: pending.value.value })
    pending.value = null
}

// Reset transient UI whenever the selection empties.
watch(() => props.count, (n) => {
    if (n === 0) { pending.value = null; openKey.value = null }
})
</script>

<style scoped>
.bulk-enter-active, .bulk-leave-active { transition: all 0.18s ease; }
.bulk-enter-from, .bulk-leave-to { opacity: 0; transform: translate(-50%, 12px); }
</style>
