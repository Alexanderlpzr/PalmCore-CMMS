<template>
    <SlidePanel
        :open="open"
        title="Crear orden de trabajo"
        @close="$emit('close')"
    >
        <form class="space-y-4 px-6 py-6" @submit.prevent="submit">
            <!-- work_order_type -->
            <div>
                <label for="wo-type" class="block text-xs font-semibold text-gray-700 mb-1">Tipo de orden</label>
                <select
                    id="wo-type"
                    v-model="form.work_order_type"
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                >
                    <option value="corrective">Correctiva</option>
                    <option value="preventive">Preventiva</option>
                    <option value="predictive">Predictiva</option>
                    <option value="inspection">Inspección</option>
                    <option value="improvement">Mejora</option>
                    <option value="emergency">Emergencia</option>
                </select>
            </div>

            <!-- priority -->
            <div>
                <label for="wo-priority" class="block text-xs font-semibold text-gray-700 mb-1">Prioridad</label>
                <select
                    id="wo-priority"
                    v-model="form.priority"
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                >
                    <option value="p1_critical">P1 Crítica</option>
                    <option value="p2_high">P2 Alta</option>
                    <option value="p3_medium">P3 Media</option>
                    <option value="p4_low">P4 Baja</option>
                    <option value="p5_planned">P5 Planificada</option>
                </select>
            </div>

            <!-- title -->
            <div>
                <label for="wo-title" class="block text-xs font-semibold text-gray-700 mb-1">Título</label>
                <input
                    id="wo-title"
                    v-model="form.title"
                    type="text"
                    required
                    maxlength="255"
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                />
            </div>

            <!-- description -->
            <div>
                <label for="wo-description" class="block text-xs font-semibold text-gray-700 mb-1">Descripción</label>
                <textarea
                    id="wo-description"
                    v-model="form.description"
                    required
                    rows="3"
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
                    {{ loading ? 'Creando...' : 'Crear OT' }}
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
    defaultType: {
        type: String,
        default: 'corrective',
    },
})

const emit = defineEmits(['close', 'created'])

const api = useApi()
const toast = useToast()

const loading = ref(false)

const defaultForm = () => ({
    work_order_type: props.defaultType,
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
        const data = await api.post('work-orders', {
            ...form.value,
            equipment_id: props.equipmentId,
        })
        toast.success('OT creada')
        emit('created', data)
        emit('close')
    } catch (err) {
        toast.error(err.message)
    } finally {
        loading.value = false
    }
}
</script>
