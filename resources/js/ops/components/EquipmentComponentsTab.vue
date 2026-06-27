<template>
    <div>
        <!-- Header row: count + add button -->
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-xs font-bold uppercase tracking-widest text-gray-500">
                Componentes<span v-if="componentCount > 0" class="ml-1.5 font-bold bg-gray-100 text-gray-500 rounded-full px-1.5 py-0.5">{{ componentCount }}</span>
            </h2>
            <button @click="openAddRoot" class="flex items-center gap-1 text-xs font-semibold text-emerald-600 hover:text-emerald-800 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Agregar
            </button>
        </div>

        <!-- Loading skeleton -->
        <div v-if="loading" class="space-y-2">
            <div v-for="i in 3" :key="i" class="skeleton h-14 rounded-2xl" />
        </div>

        <!-- Tree list -->
        <div v-else-if="flatList.length" class="bg-white rounded-2xl border border-gray-100 shadow-sm divide-y divide-gray-50">
            <div
                v-for="item in flatList"
                :key="item.id"
                class="flex items-center gap-2 px-3 py-2.5 hover:bg-gray-50 transition-colors group"
                :style="{ paddingLeft: item._depth * 24 + 12 + 'px' }"
            >
                <!-- Expand/collapse chevron -->
                <button
                    v-if="item._hasChildren"
                    @click="toggleExpand(item.id)"
                    class="w-5 h-5 flex items-center justify-center shrink-0 text-gray-400 hover:text-gray-700 transition-colors"
                >
                    <svg
                        class="w-3.5 h-3.5 transition-transform duration-150"
                        :class="expandedIds.has(item.id) ? 'rotate-90' : ''"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"
                    >
                        <polyline points="9 18 15 12 9 6"/>
                    </svg>
                </button>
                <div v-else class="w-5 shrink-0" />

                <!-- Criticality dot -->
                <div class="w-2 h-2 rounded-full shrink-0" :class="critDotClass(item.criticality)" />

                <!-- Main info -->
                <div class="flex-1 min-w-0 flex items-center gap-2 flex-wrap">
                    <div class="min-w-0 flex items-baseline gap-1.5">
                        <span v-if="item.code" class="text-xs font-mono font-bold text-gray-400 uppercase tracking-wide shrink-0">{{ item.code }}</span>
                        <span class="text-sm font-medium text-gray-900 truncate">{{ item.name }}</span>
                    </div>

                    <span v-if="item.part_number" class="text-xs font-mono text-slate-400 bg-slate-50 px-1.5 py-0.5 rounded shrink-0">{{ item.part_number }}</span>

                    <!-- Status badge -->
                    <span :class="statusBadgeClass(item.status)" class="shrink-0">{{ item.status_label }}</span>

                    <!-- Criticality badge -->
                    <span class="shrink-0 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium" :class="critBadgeClass(item.criticality)">
                        {{ critLabel(item.criticality) }}
                    </span>

                    <span v-if="item.manufacturer || item.model" class="text-xs text-gray-400 shrink-0">
                        {{ [item.manufacturer, item.model].filter(Boolean).join(' · ') }}
                    </span>

                    <span v-if="item.worked_hours" class="text-xs text-gray-400 shrink-0">
                        {{ item.worked_hours.toLocaleString() }} h<template v-if="item.useful_life_hours"> / {{ item.useful_life_hours.toLocaleString() }} h</template>
                    </span>
                </div>

                <!-- Actions (visible on hover) -->
                <div class="flex items-center gap-0.5 shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
                    <button
                        @click="openAddChild(item)"
                        class="p-1.5 rounded-lg text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 transition-colors"
                        title="Agregar subcomponente"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    </button>
                    <button
                        @click="openEdit(item)"
                        class="p-1.5 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition-colors"
                        title="Editar"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/></svg>
                    </button>
                    <button
                        @click="confirmDelete(item)"
                        class="p-1.5 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 transition-colors"
                        title="Eliminar"
                    >
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
            <p class="text-xs text-gray-500">Sin componentes registrados. Agrega el primero.</p>
            <button @click="openAddRoot" class="text-xs font-semibold text-emerald-600 hover:text-emerald-800 transition-colors mt-1">+ Agregar componente</button>
        </div>

        <!-- ── Form modal ──────────────────────────────────────────────────────── -->
        <Teleport to="body">
            <div v-if="showForm" class="fixed inset-0 z-50 bg-black/40 flex items-end sm:items-center justify-center p-0 sm:p-4" @click.self="closeForm">
                <div class="bg-white w-full sm:max-w-lg rounded-t-3xl sm:rounded-2xl shadow-xl overflow-y-auto max-h-[90vh]">
                    <div class="flex items-center justify-between px-5 pt-5 pb-3 border-b border-gray-100">
                        <div>
                            <h3 class="text-sm font-bold text-gray-900">{{ editingComponent ? 'Editar componente' : 'Agregar componente' }}</h3>
                            <p v-if="formParentId && !editingComponent" class="text-xs text-gray-400 mt-0.5">Subcomponente del nodo seleccionado</p>
                        </div>
                        <button @click="closeForm" class="text-gray-400 hover:text-gray-700 transition-colors">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>

                    <form @submit.prevent="saveComponent" class="px-5 py-4 space-y-4">
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
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Número de parte</label>
                                <input v-model="form.part_number" type="text" placeholder="SKF-6205"
                                    class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent font-mono" />
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Criticidad</label>
                                <select v-model="form.criticality"
                                    class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500">
                                    <option v-for="opt in criticalityOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Estado</label>
                                <select v-model="form.status"
                                    class="w-full px-3 py-2 text-sm border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500">
                                    <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
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

                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Horas trabajadas</label>
                                <input v-model.number="form.worked_hours" type="number" min="0" step="0.1"
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
                            <button type="submit" :disabled="formSaving"
                                class="px-5 py-2 text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl transition-colors disabled:opacity-50">
                                {{ formSaving ? 'Guardando…' : (editingComponent ? 'Guardar cambios' : 'Agregar') }}
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
                    <p class="text-xs text-gray-600 mb-4">
                        ¿Eliminar <span class="font-semibold">{{ deleteTarget.name }}</span>?
                        <template v-if="deleteTarget.children_count > 0"> Tiene {{ deleteTarget.children_count }} subcomponente(s) que también serán eliminados.</template>
                        Esta acción no se puede deshacer.
                    </p>
                    <div class="flex justify-end gap-2">
                        <button @click="deleteTarget = null" class="px-4 py-2 text-sm font-semibold text-gray-600 hover:text-gray-900 transition-colors">Cancelar</button>
                        <button @click="deleteComponent" :disabled="deleting"
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
import { ref, computed, onMounted } from 'vue'
import { useApi } from '../composables/useApi.js'

const props = defineProps({
    equipmentId: { type: String, required: true },
})

const api = useApi()

// ── State ─────────────────────────────────────────────────────────────────────

const components = ref([]) // root nodes with nested children
const loading = ref(false)
const expandedIds = ref(new Set())

const showForm = ref(false)
const editingComponent = ref(null) // null = new, object = edit
const formParentId = ref(null) // for "add child" action

const deleteTarget = ref(null)
const deleting = ref(false)

const form = ref(emptyForm())
const formSaving = ref(false)
const formError = ref('')

function emptyForm() {
    return {
        code: '',
        part_number: '',
        name: '',
        manufacturer: '',
        model: '',
        serial_number: '',
        criticality: 'medium',
        status: 'active',
        useful_life_hours: null,
        worked_hours: null,
        notes: '',
    }
}

// ── Data loading ──────────────────────────────────────────────────────────────

async function loadTree() {
    loading.value = true
    try {
        const res = await api.get(`equipment/${props.equipmentId}/components/tree`)
        components.value = res?.data ?? []
    } catch { /* silent */ } finally {
        loading.value = false
    }
}

// ── Tree helpers ──────────────────────────────────────────────────────────────

function countAll(nodes) {
    return nodes.reduce((sum, n) => sum + 1 + countAll(n.children ?? []), 0)
}

const componentCount = computed(() => countAll(components.value))

function flattenTree(nodes, depth = 0) {
    return nodes.flatMap(node => [
        {
            ...node,
            _depth: depth,
            _hasChildren: (node.children?.length ?? 0) > 0,
        },
        ...(expandedIds.value.has(node.id) && node.children?.length
            ? flattenTree(node.children, depth + 1)
            : []),
    ])
}

const flatList = computed(() => flattenTree(components.value))

function toggleExpand(id) {
    const next = new Set(expandedIds.value)
    if (next.has(id)) {
        next.delete(id)
    } else {
        next.add(id)
    }
    expandedIds.value = next
}

// ── Form actions ──────────────────────────────────────────────────────────────

function openAddRoot() {
    editingComponent.value = null
    formParentId.value = null
    form.value = emptyForm()
    formError.value = ''
    showForm.value = true
}

function openAddChild(node) {
    editingComponent.value = null
    formParentId.value = node.id
    form.value = emptyForm()
    formError.value = ''
    showForm.value = true
    // Auto-expand the parent so the new child appears
    const next = new Set(expandedIds.value)
    next.add(node.id)
    expandedIds.value = next
}

function openEdit(node) {
    editingComponent.value = node
    formParentId.value = null
    form.value = {
        code: node.code ?? '',
        part_number: node.part_number ?? '',
        name: node.name ?? '',
        manufacturer: node.manufacturer ?? '',
        model: node.model ?? '',
        serial_number: node.serial_number ?? '',
        criticality: node.criticality ?? 'medium',
        status: node.status ?? 'active',
        useful_life_hours: node.useful_life_hours ?? null,
        worked_hours: node.worked_hours ?? null,
        notes: node.notes ?? '',
    }
    formError.value = ''
    showForm.value = true
}

function closeForm() {
    showForm.value = false
    editingComponent.value = null
    formParentId.value = null
}

async function saveComponent() {
    if (formSaving.value) { return }
    formSaving.value = true
    formError.value = ''

    const payload = {
        name: form.value.name,
        criticality: form.value.criticality,
        status: form.value.status,
        code: form.value.code || null,
        part_number: form.value.part_number || null,
        manufacturer: form.value.manufacturer || null,
        model: form.value.model || null,
        serial_number: form.value.serial_number || null,
        useful_life_hours: form.value.useful_life_hours || null,
        worked_hours: form.value.worked_hours || null,
        notes: form.value.notes || null,
    }

    try {
        if (editingComponent.value) {
            await api.patch(`equipment/${props.equipmentId}/components/${editingComponent.value.id}`, payload)
        } else {
            if (formParentId.value) {
                payload.parent_id = formParentId.value
            }
            await api.post(`equipment/${props.equipmentId}/components`, payload)
        }
        closeForm()
        await loadTree()
    } catch (err) {
        formError.value = err?.message ?? 'Error al guardar. Verifica los datos e intenta de nuevo.'
    } finally {
        formSaving.value = false
    }
}

// ── Delete ────────────────────────────────────────────────────────────────────

function confirmDelete(node) {
    deleteTarget.value = node
}

async function deleteComponent() {
    if (deleting.value || !deleteTarget.value) { return }
    deleting.value = true
    try {
        await api.del(`equipment/${props.equipmentId}/components/${deleteTarget.value.id}`)
        deleteTarget.value = null
        await loadTree()
    } catch { /* silent */ } finally {
        deleting.value = false
    }
}

// ── Status helpers ────────────────────────────────────────────────────────────

const statusColors = {
    active:   'bg-emerald-100 text-emerald-700',
    degraded: 'bg-amber-100 text-amber-700',
    failed:   'bg-red-100 text-red-700',
    replaced: 'bg-blue-100 text-blue-700',
    retired:  'bg-gray-100 text-gray-600',
}

function statusBadgeClass(status) {
    return `inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${statusColors[status] ?? statusColors.active}`
}

const statusOptions = [
    { value: 'active',   label: 'Operativo' },
    { value: 'degraded', label: 'Degradado' },
    { value: 'failed',   label: 'Falla' },
    { value: 'replaced', label: 'Reemplazado' },
    { value: 'retired',  label: 'Retirado' },
]

// ── Criticality helpers ───────────────────────────────────────────────────────

const critDotColors = {
    critical: 'bg-red-500',
    high:     'bg-orange-400',
    medium:   'bg-amber-400',
    low:      'bg-emerald-400',
}

function critDotClass(criticality) {
    return critDotColors[criticality] ?? 'bg-gray-300'
}

const critBadgeColors = {
    critical: 'bg-red-100 text-red-700',
    high:     'bg-orange-100 text-orange-700',
    medium:   'bg-amber-100 text-amber-700',
    low:      'bg-emerald-100 text-emerald-700',
}

function critBadgeClass(criticality) {
    return critBadgeColors[criticality] ?? 'bg-gray-100 text-gray-500'
}

function critLabel(criticality) {
    return { critical: 'Crítico', high: 'Alto', medium: 'Medio', low: 'Bajo' }[criticality] ?? criticality
}

const criticalityOptions = [
    { value: 'critical', label: 'Crítico' },
    { value: 'high',     label: 'Alto' },
    { value: 'medium',   label: 'Medio' },
    { value: 'low',      label: 'Bajo' },
]

// ── Lifecycle ─────────────────────────────────────────────────────────────────

onMounted(loadTree)

defineExpose({ componentCount })
</script>
