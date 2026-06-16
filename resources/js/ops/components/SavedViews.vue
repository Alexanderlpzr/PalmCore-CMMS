<template>
    <div class="relative shrink-0">
        <button
            @click="open = ! open"
            class="flex items-center gap-1 text-xs font-medium text-gray-500 hover:text-gray-700 transition-colors whitespace-nowrap"
            title="Vistas guardadas"
        >
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 6h18M6 12h12M10 18h4" />
            </svg>
            Vistas
            <span v-if="views.length" class="text-[10px] font-bold bg-gray-100 text-gray-500 rounded-full px-1.5">{{ views.length }}</span>
        </button>

        <template v-if="open">
            <!-- click-away -->
            <div class="fixed inset-0 z-30" @click="close" />

            <div class="absolute right-0 mt-1.5 z-40 w-64 bg-white rounded-xl shadow-xl border border-gray-100 overflow-hidden">
                <!-- Save current -->
                <div class="p-2 border-b border-gray-50">
                    <div v-if="naming" class="flex items-center gap-1">
                        <input
                            ref="nameInput"
                            v-model="name"
                            type="text"
                            maxlength="40"
                            placeholder="Nombre de la vista"
                            class="flex-1 min-w-0 text-sm px-2 py-1.5 rounded-lg border border-gray-200 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                            @keyup.enter="confirmSave"
                            @keyup.esc="naming = false"
                        />
                        <button
                            @click="confirmSave"
                            :disabled="! name.trim()"
                            class="shrink-0 px-2.5 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 disabled:opacity-40 text-white text-xs font-semibold transition-colors"
                        >Guardar</button>
                    </div>
                    <button
                        v-else
                        @click="startNaming"
                        class="w-full flex items-center gap-2 px-2 py-1.5 rounded-lg text-sm font-medium text-emerald-700 hover:bg-emerald-50 transition-colors"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Guardar vista actual
                    </button>
                </div>

                <!-- List -->
                <div class="max-h-64 overflow-y-auto py-1">
                    <p v-if="! views.length" class="px-3 py-5 text-center text-xs text-gray-400">No hay vistas guardadas.</p>
                    <div
                        v-for="v in views"
                        :key="v.id"
                        class="group flex items-center gap-1 px-1.5"
                    >
                        <button
                            @click="applyView(v)"
                            class="flex-1 min-w-0 text-left px-2 py-1.5 rounded-lg hover:bg-gray-50 transition-colors"
                        >
                            <span class="block text-sm text-gray-800 truncate">{{ v.name }}</span>
                            <span class="block text-[11px] text-gray-400">{{ formatDate(v.createdAt) }}</span>
                        </button>
                        <button
                            @click="remove(v.id)"
                            class="shrink-0 p-1.5 rounded-lg text-gray-300 hover:text-red-500 hover:bg-red-50 opacity-0 group-hover:opacity-100 transition-all"
                            title="Eliminar vista"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 6h18M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>

<script setup>
import { ref, nextTick } from 'vue'
import { useSavedViews } from '../composables/useSavedViews.js'

const props = defineProps({
    view: { type: String, required: true },
    current: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['apply'])

const { views, save, remove } = useSavedViews(props.view)

const open = ref(false)
const naming = ref(false)
const name = ref('')
const nameInput = ref(null)

function close() {
    open.value = false
    naming.value = false
}

async function startNaming() {
    naming.value = true
    name.value = ''
    await nextTick()
    nameInput.value?.focus()
}

function confirmSave() {
    const trimmed = name.value.trim()
    if (! trimmed) { return }
    save(trimmed, props.current)
    naming.value = false
    name.value = ''
}

function applyView(v) {
    emit('apply', v.state)
    close()
}

function formatDate(iso) {
    try {
        return new Date(iso).toLocaleDateString('es', { day: 'numeric', month: 'short', year: 'numeric' })
    } catch {
        return ''
    }
}
</script>
