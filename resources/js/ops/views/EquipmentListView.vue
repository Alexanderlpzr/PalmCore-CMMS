<template>
    <div class="p-5 lg:p-8 max-w-6xl mx-auto">

        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-xl font-bold text-gray-900">Equipos</h1>
                <p v-if="!loading" class="text-sm text-gray-400 mt-0.5">{{ total }} equipos registrados</p>
            </div>
            <button class="flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-4 py-2 rounded-xl transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                Nuevo equipo
            </button>
        </div>

        <!-- Search -->
        <div class="relative mb-5">
            <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
            <input
                v-model="search"
                type="text"
                placeholder="Buscar por código, nombre o área..."
                class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent bg-white"
            />
        </div>

        <!-- Status filter tabs -->
        <div class="flex gap-1.5 mb-5 overflow-x-auto pb-1">
            <button
                v-for="f in filters"
                :key="f.value"
                @click="activeFilter = f.value"
                class="shrink-0 px-3.5 py-1.5 rounded-full text-xs font-semibold transition-colors"
                :class="activeFilter === f.value
                    ? 'bg-slate-900 text-white'
                    : 'bg-white border border-gray-200 text-gray-600 hover:border-gray-300'"
            >
                {{ f.label }}
            </button>
        </div>

        <!-- Grid skeleton -->
        <div v-if="loading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div v-for="i in 6" :key="i" class="bg-white rounded-2xl border border-gray-100 p-4 space-y-3">
                <div class="skeleton h-4 w-1/3 rounded" />
                <div class="skeleton h-5 w-2/3 rounded" />
                <div class="flex gap-2">
                    <div class="skeleton h-5 w-16 rounded-full" />
                    <div class="skeleton h-5 w-12 rounded-full" />
                </div>
                <div class="skeleton h-3 w-1/2 rounded" />
            </div>
        </div>

        <!-- Equipment cards grid -->
        <div v-else-if="filteredEquipment.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <RouterLink
                v-for="eq in filteredEquipment"
                :key="eq.id"
                :to="{ name: 'ops.equipos.show', params: { id: eq.id } }"
                class="block"
            >
                <EquipmentCard :equipment="eq" />
            </RouterLink>
        </div>

        <!-- Empty state -->
        <div v-else class="flex flex-col items-center justify-center py-20 text-center">
            <div class="w-16 h-16 rounded-2xl bg-gray-100 flex items-center justify-center mb-4">
                <svg class="w-8 h-8 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                </svg>
            </div>
            <p class="text-sm font-medium text-gray-700">Sin equipos encontrados</p>
            <p class="text-xs text-gray-400 mt-1">Intenta ajustar los filtros de búsqueda</p>
        </div>

    </div>
</template>

<script setup>
import { ref, computed, onMounted, defineComponent, h } from 'vue'
import { RouterLink } from 'vue-router'
import { useApi } from '../composables/useApi.js'

const api = useApi()
const equipment = ref([])
const loading = ref(true)
const search = ref('')
const activeFilter = ref('all')
const total = ref(0)

const filters = [
    { label: 'Todos', value: 'all' },
    { label: 'Activos', value: 'active' },
    { label: 'En mantenimiento', value: 'under_maintenance' },
    { label: 'Inactivos', value: 'inactive' },
]

const filteredEquipment = computed(() => {
    let list = equipment.value
    if (activeFilter.value !== 'all') {
        list = list.filter(e => e.status === activeFilter.value)
    }
    if (search.value.trim()) {
        const q = search.value.toLowerCase()
        list = list.filter(e =>
            e.code?.toLowerCase().includes(q) ||
            e.name?.toLowerCase().includes(q) ||
            e.area?.name?.toLowerCase().includes(q) ||
            e.plant?.name?.toLowerCase().includes(q)
        )
    }
    return list
})

const statusConfig = {
    active:             { label: 'Activo',           color: 'bg-emerald-100 text-emerald-700' },
    inactive:           { label: 'Inactivo',         color: 'bg-gray-100 text-gray-600' },
    under_maintenance:  { label: 'En mantenimiento', color: 'bg-amber-100 text-amber-700' },
    retired:            { label: 'Retirado',         color: 'bg-red-100 text-red-700' },
}

const criticalityConfig = {
    critical: { label: 'Crítico', color: 'text-red-600 bg-red-50' },
    high:     { label: 'Alto',    color: 'text-orange-600 bg-orange-50' },
    medium:   { label: 'Medio',   color: 'text-yellow-600 bg-yellow-50' },
    low:      { label: 'Bajo',    color: 'text-green-600 bg-green-50' },
}

const EquipmentCard = defineComponent({
    props: { equipment: Object },
    setup(props) {
        const status = computed(() => statusConfig[props.equipment.status] ?? { label: props.equipment.status, color: 'bg-gray-100 text-gray-600' })
        const crit = computed(() => criticalityConfig[props.equipment.criticality] ?? null)

        return () => h('div', {
            class: 'bg-white rounded-2xl border border-gray-100 shadow-sm hover:shadow-md hover:border-gray-200 transition-all cursor-pointer p-4 flex flex-col gap-3',
        }, [
            // Header row: code + status badge
            h('div', { class: 'flex items-start justify-between gap-2' }, [
                h('span', { class: 'text-xs font-mono font-bold text-gray-400 tracking-widest' }, props.equipment.code),
                h('span', { class: `text-[10px] font-semibold px-2 py-0.5 rounded-full shrink-0 ${status.value.color}` }, status.value.label),
            ]),

            // Name
            h('p', { class: 'text-sm font-semibold text-gray-900 leading-snug' }, props.equipment.name),

            // Criticality + priority
            h('div', { class: 'flex gap-1.5 flex-wrap' }, [
                crit.value ? h('span', { class: `text-[10px] font-semibold px-2 py-0.5 rounded-full ${crit.value.color}` }, crit.value.label) : null,
                props.equipment.priority ? h('span', { class: 'text-[10px] font-semibold px-2 py-0.5 rounded-full bg-slate-100 text-slate-600' }, props.equipment.priority?.toUpperCase()) : null,
            ]),

            // Location
            h('p', { class: 'text-xs text-gray-400 flex items-center gap-1' }, [
                h('svg', { class: 'w-3 h-3 shrink-0', fill: 'none', viewBox: '0 0 24 24', stroke: 'currentColor', 'stroke-width': '2', innerHTML: `<path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0z"/>` }),
                [props.equipment.plant?.name, props.equipment.area?.name].filter(Boolean).join(' — ') || '—',
            ]),
        ])
    },
})

onMounted(async () => {
    try {
        const res = await api.get('equipment?per_page=100&include=plant,area')
        equipment.value = res?.data ?? []
        total.value = res?.meta?.total ?? equipment.value.length
    } catch { /* silent */ } finally {
        loading.value = false
    }
})
</script>
