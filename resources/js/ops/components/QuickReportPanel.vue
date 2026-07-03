<template>
    <SlidePanel
        :open="open"
        title="Reportar problema"
        description="La solicitud será revisada por el equipo de mantenimiento."
        @close="$emit('close')"
    >
        <form class="space-y-4 px-6 py-6" @submit.prevent="submit">
            <!-- request_type -->
            <div>
                <label for="req-type" class="block text-xs font-semibold text-gray-700 mb-1">Tipo de solicitud</label>
                <select
                    id="req-type"
                    v-model="form.request_type"
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                >
                    <option value="corrective">Correctiva</option>
                    <option value="emergency">Emergencia</option>
                    <option value="predictive">Predictiva</option>
                    <option value="improvement">Mejora</option>
                </select>
            </div>

            <!-- priority -->
            <div>
                <label for="req-priority" class="block text-xs font-semibold text-gray-700 mb-1">Prioridad</label>
                <select
                    id="req-priority"
                    v-model="form.priority"
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                >
                    <option value="p1_critical">P1 Crítica</option>
                    <option value="p2_high">P2 Alta</option>
                    <option value="p3_medium">P3 Media</option>
                    <option value="p4_low">P4 Baja</option>
                </select>
            </div>

            <!-- title -->
            <div>
                <label for="req-title" class="block text-xs font-semibold text-gray-700 mb-1">Título</label>
                <input
                    id="req-title"
                    v-model="form.title"
                    type="text"
                    required
                    maxlength="255"
                    placeholder="Ej: Ruido inusual en rodamiento"
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                />
            </div>

            <!-- description -->
            <div>
                <label for="req-description" class="block text-xs font-semibold text-gray-700 mb-1">Descripción</label>
                <textarea
                    id="req-description"
                    v-model="form.description"
                    required
                    rows="4"
                    placeholder="Describe el problema con detalle: qué observaste, cuándo comenzó, si afecta la operación..."
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                />
            </div>
        </form>

        <template #footer>
            <div class="flex justify-end gap-3 px-6 py-4 border-t border-gray-100">
                <button
                    type="button"
                    class="border border-gray-200 text-gray-600 px-4 py-2 rounded-lg text-sm"
                    @click="$emit('close')"
                >
                    Cancelar
                </button>
                <button
                    type="button"
                    :disabled="loading"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-semibold disabled:opacity-60"
                    @click="submit"
                >
                    {{ loading ? 'Enviando...' : 'Reportar' }}
                </button>
            </div>
        </template>
    </SlidePanel>
</template>

<script setup>
import { ref, watch } from 'vue'
import { useApi } from '../composables/useApi.js'
import { useToast } from '../composables/useToast.js'
import SlidePanel from './SlidePanel.vue'

const props = defineProps({
    equipmentId: {
        type: String,
        required: true,
    },
    open: {
        type: Boolean,
        required: true,
    },
})

const emit = defineEmits(['close', 'created'])

const api = useApi()
const toast = useToast()

const loading = ref(false)

const defaultForm = () => ({
    request_type: 'corrective',
    priority: 'p3_medium',
    title: '',
    description: '',
})

const form = ref(defaultForm())

watch(() => props.open, (isOpen) => {
    if (isOpen) {
        form.value = defaultForm()
    }
})

async function submit() {
    loading.value = true
    try {
        const data = await api.post('maintenance-requests', {
            ...form.value,
            equipment_id: props.equipmentId,
        })
        toast.success('Solicitud reportada')
        emit('created', data)
        emit('close')
    } catch (err) {
        toast.error(err.message)
    } finally {
        loading.value = false
    }
}
</script>
