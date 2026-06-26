<template>
    <div>
        <!-- Header row: count + add button -->
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-xs font-bold uppercase tracking-widest text-gray-500">
                Componentes<span v-if="components.length" class="ml-1.5 font-bold bg-gray-100 text-gray-500 rounded-full px-1.5 py-0.5">{{ components.length }}</span>
            </h2>
            <button @click="openAdd" class="flex items-center gap-1 text-xs font-semibold text-emerald-600 hover:text-emerald-800 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Agregar
            </button>
        </div>

        <!-- Loading skeleton -->
        <div v-if="loading" class="space-y-3">
            <div v-for="i in 3" :key="i" class="skeleton h-16 rounded-2xl" />
        </div>

        <!-- Component list -->
        <div v-else-if="components.length" class="space-y-2">
            <div v-for="comp in components" :key="comp.id"
                class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 flex items-start gap-3">
                <!-- Criticality dot -->
                <div class="w-2.5 h-2.5 rounded-full shrink-0 mt-1.5" :class="critDot(comp.criticality)" />

                <div class="flex-1 min-w-0">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p v-if="comp.code" class="text-xs font-mono font-bold text-gray-400 uppercase tracking-wide leading-none mb-0.5">{{ comp.code }}</p>
                            <p class="text-sm font-semibold text-gray-900 leading-tight">{{ comp.name }}</p>
                        </div>
                        <span class="shrink-0 text-xs font-semibold px-1.5 py-0.5 rounded-full" :class="critBadge(comp.criticality)">
                            {{ critLabel(comp.criticality) }}
                        </span>
                    </div>

                    <div class="flex flex-wrap gap-x-4 gap-y-0.5 mt-1.5 text-xs text-gray-500">
                        <span v-if="comp.manufacturer">{{ comp.manufacturer }}</span>
                        <span v-if="comp.model" class="font-mono">{{ comp.model }}</span>
                        <span v-if="comp.serial_number">S/N: {{ comp.serial_number }}</span>
                        <span v-if="comp.useful_life_hours">{{ comp.useful_life_hours.toLocaleString() }} h</span>
                    </div>

                    <p v-if="comp.notes" class="text-xs text-gray-500 mt-1 line-clamp-2 leading-snug">{{ comp.notes }}</p>
                </div>

                <!-- Actions -->
                <div class="flex items-center gap-1 shrink-0">
                    <button @click="openEdit(comp)" class="p-1.5 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-50 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/></svg>
                    </button>
                    <button @click="confirmDelete(comp)" class="p-1.5 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Empty state -->
        <div v-else class="bg-white rounded-2xl border border-gray-100 shadow-sm py-10 flex flex-col items-center gap-2 text-center">
            <svg class="w-8 h-8 text-gray-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l5.653-4.655m5.8-7.425c.316-.316.316-.828 0-1.143L9.79 2.94a.806.806 0 0 0-1.143 0L7.09 4.508a.806.806 0 0 0 0 1.143l5.4 5.4c.316.316.828.316 1.143 0l1.787-1.787z"/>
            </svg>
            <p class="text-xs text-gray-500">Sin componentes registrados</p>
            <button @click="openAdd" class="text-xs font-semibold text-emerald-600 hover:text-emerald-800 transition-colors mt-1">+ Agregar componente</button>
        </div>

        <!-- ── Form modal ──────────────────────────────────────────────────────── -->
        <Teleport to="body">
            <div v-if="showForm" class="fixed inset-0 z-50 bg-black/40 flex items-end sm:items-center justify-center p-0 sm:p-4" @click.self="closeForm">
                <div class="bg-white w-full sm:max-w-lg rounded-t-3xl sm:rounded-2xl shadow-xl overflow-y-auto max-h-[90vh]">
                    <div class="flex items-center justify-between px-5 pt-5 pb-3 border-b border-gray-100">
                        <h3 class="text-sm font-bold text-gray-900">{{ editing ? 'Editar componente' : 'Agregar componente' }}</h3>
                        <button @click="closeForm" class="text-gray-400 hover:text-gray-700 transition-colors">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>

                    <form @submit.prevent="submitForm" class="px-5 py-4 space-y-4">
                        <div class="grid grid-cols-2 gap-3">
                            <div class="col-span-2">
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Nombre <span class="text-red-500">*</span></label>
                                <input v-model="form.name" type="text" required placeholder="Ej: Bomba centrífuga principal"
                                    class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent" />
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Código</label>
                                <input v-model="form.code" type="text" placeholder="COMP-001"
                                    class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent font-mono" />
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Criticidad</label>
                                <select v-model="form.criticality"
                                    class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500">
                                    <option value="critical">Crítico</option>
                                    <option value="high">Alto</option>
                                    <option value="medium">Medio</option>
                                    <option value="low">Bajo</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Fabricante</label>
                                <input v-model="form.manufacturer" type="text" placeholder="Ej: Siemens"
                                    class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500" />
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Modelo</label>
                                <input v-model="form.model" type="text"
                                    class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500" />
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">N° de serie</label>
                                <input v-model="form.serial_number" type="text"
                                    class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500 font-mono" />
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Vida útil (horas)</label>
                                <input v-model.number="form.useful_life_hours" type="number" min="1"
                                    class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500" />
                            </div>

                            <div class="col-span-2">
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Observaciones</label>
                                <textarea v-model="form.notes" rows="3" placeholder="Notas adicionales…"
                                    class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500 resize-none" />
                            </div>
                        </div>

                        <p v-if="formError" class="text-xs text-red-600 font-medium">{{ formError }}</p>

                        <div class="flex items-center justify-end gap-2 pt-1">
                            <button type="button" @click="closeForm"
                                class="px-4 py-2 text-sm font-semibold text-gray-600 hover:text-gray-900 transition-colors">
                                Cancelar
                            </button>
                            <button type="submit" :disabled="saving"
                                class="px-5 py-2 text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl transition-colors disabled:opacity-50">
                                {{ saving ? 'Guardando…' : (editing ? 'Guardar cambios' : 'Agregar') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- ── Delete confirmation ─────────────────────────────────────────────── -->
        <Teleport to="body">
            <div v-if="deleteTarget" class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4" @click.self="deleteTarget = null">
                <div class="bg-white w-full max-w-sm rounded-2xl shadow-xl p-5">
                    <h3 class="text-sm font-bold text-gray-900 mb-1">Eliminar componente</h3>
                    <p class="text-xs text-gray-600 mb-4">¿Eliminar <span class="font-semibold">{{ deleteTarget.name }}</span>? Esta acción no se puede deshacer.</p>
                    <div class="flex justify-end gap-2">
                        <button @click="deleteTarget = null" class="px-4 py-2 text-sm font-semibold text-gray-600 hover:text-gray-900 transition-colors">Cancelar</button>
                        <button @click="doDelete" :disabled="deleting"
                            class="px-5 py-2 text-sm font-semibold text-white bg-red-600 hover:bg-red-700 rounded-xl transition-colors disabled:opacity-50">
                            {{ deleting ? 'Eliminando…' : 'Eliminar' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useApi } from '../composables/useApi.js'

const props = defineProps({
    equipmentId: { type: String, required: true },
})

const api = useApi()

// ── State ─────────────────────────────────────────────────────────────────────

const loading = ref(true)
const components = ref([])

const showForm = ref(false)
const editing = ref(null)
const saving = ref(false)
const formError = ref(null)

const deleteTarget = ref(null)
const deleting = ref(false)

const emptyForm = () => ({
    code: '',
    name: '',
    manufacturer: '',
    model: '',
    serial_number: '',
    criticality: 'medium',
    useful_life_hours: null,
    notes: '',
})

const form = ref(emptyForm())

// ── Data loading ──────────────────────────────────────────────────────────────

async function loadComponents() {
    loading.value = true
    try {
        const res = await api.get(`equipment/${props.equipmentId}/components`)
        components.value = res?.data ?? []
    } catch { /* silent */ } finally {
        loading.value = false
    }
}

// ── Form ──────────────────────────────────────────────────────────────────────

function openAdd() {
    editing.value = null
    form.value = emptyForm()
    formError.value = null
    showForm.value = true
}

function openEdit(comp) {
    editing.value = comp
    form.value = {
        code: comp.code ?? '',
        name: comp.name,
        manufacturer: comp.manufacturer ?? '',
        model: comp.model ?? '',
        serial_number: comp.serial_number ?? '',
        criticality: comp.criticality ?? 'medium',
        useful_life_hours: comp.useful_life_hours ?? null,
        notes: comp.notes ?? '',
    }
    formError.value = null
    showForm.value = true
}

function closeForm() {
    showForm.value = false
    editing.value = null
}

async function submitForm() {
    if (saving.value) { return }
    saving.value = true
    formError.value = null

    const payload = {
        name: form.value.name,
        criticality: form.value.criticality,
        code: form.value.code || null,
        manufacturer: form.value.manufacturer || null,
        model: form.value.model || null,
        serial_number: form.value.serial_number || null,
        useful_life_hours: form.value.useful_life_hours || null,
        notes: form.value.notes || null,
    }

    try {
        if (editing.value) {
            const res = await api.patch(`equipment/${props.equipmentId}/components/${editing.value.id}`, payload)
            const idx = components.value.findIndex(c => c.id === editing.value.id)
            if (idx !== -1) { components.value[idx] = res.data }
        } else {
            const res = await api.post(`equipment/${props.equipmentId}/components`, payload)
            components.value.push(res.data)
        }
        closeForm()
    } catch (err) {
        formError.value = err?.message ?? 'Error al guardar. Verifica los datos e intenta de nuevo.'
    } finally {
        saving.value = false
    }
}

// ── Delete ────────────────────────────────────────────────────────────────────

function confirmDelete(comp) {
    deleteTarget.value = comp
}

async function doDelete() {
    if (deleting.value || !deleteTarget.value) { return }
    deleting.value = true
    try {
        await api.delete(`equipment/${props.equipmentId}/components/${deleteTarget.value.id}`)
        components.value = components.value.filter(c => c.id !== deleteTarget.value.id)
        deleteTarget.value = null
    } catch { /* silent */ } finally {
        deleting.value = false
    }
}

// ── Criticality helpers ───────────────────────────────────────────────────────

function critDot(c) {
    return { critical: 'bg-red-500', high: 'bg-orange-400', medium: 'bg-amber-400', low: 'bg-gray-300' }[c] ?? 'bg-gray-300'
}

function critBadge(c) {
    return {
        critical: 'bg-red-100 text-red-700',
        high: 'bg-orange-100 text-orange-700',
        medium: 'bg-amber-100 text-amber-700',
        low: 'bg-gray-100 text-gray-500',
    }[c] ?? 'bg-gray-100 text-gray-500'
}

function critLabel(c) {
    return { critical: 'Crítico', high: 'Alto', medium: 'Medio', low: 'Bajo' }[c] ?? c
}

// ── Lifecycle ─────────────────────────────────────────────────────────────────

onMounted(loadComponents)

// Expose component count for parent to display in tab nav
defineExpose({ componentCount: () => components.value.length })
</script>
