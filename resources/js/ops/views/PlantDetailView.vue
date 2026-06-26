<template>
    <div class="min-h-full bg-gray-50">

        <!-- Loading skeleton -->
        <div v-if="loading" class="bg-white border-b border-gray-100 px-4 lg:px-8 py-5">
            <div class="max-w-4xl mx-auto">
                <div class="skeleton h-3 w-24 rounded mb-4" />
                <div class="skeleton h-7 w-1/2 rounded mb-2" />
                <div class="skeleton h-3 w-1/3 rounded" />
                <div class="grid grid-cols-3 gap-3 mt-6">
                    <div v-for="i in 3" :key="i" class="skeleton h-16 rounded-xl" />
                </div>
            </div>
        </div>

        <template v-else-if="plant">

            <!-- ── Sticky header ──────────────────────────────────────────────── -->
            <div class="bg-white border-b border-gray-100 sticky top-0 z-20 shadow-sm">
                <div class="max-w-4xl mx-auto px-4 lg:px-8 pt-3 pb-0">

                    <!-- Breadcrumb + back -->
                    <div class="flex items-center gap-2 text-xs mb-3 flex-wrap">
                        <button @click="goBack" class="flex items-center gap-1 text-gray-500 hover:text-gray-700 transition-colors shrink-0">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
                            {{ backLabel }}
                        </button>
                        <svg class="w-3 h-3 text-gray-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                        <span class="text-gray-700 font-medium truncate">{{ plant.name }}</span>
                    </div>

                    <!-- Identity row -->
                    <div class="flex items-start gap-3 mb-4">
                        <div class="w-10 h-10 rounded-xl bg-emerald-100 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21"/>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-mono font-bold text-gray-500 uppercase tracking-widest leading-none">{{ plant.code }}</p>
                            <h1 class="text-xl font-bold text-gray-900 mt-0.5 leading-tight">{{ plant.name }}</h1>
                            <p v-if="plant.city || plant.address" class="text-xs text-gray-500 mt-0.5">
                                {{ [plant.city, plant.country_code].filter(Boolean).join(', ') }}
                            </p>
                        </div>
                        <span v-if="!plant.is_active" class="shrink-0 text-xs font-semibold px-2 py-0.5 rounded-full bg-gray-100 text-gray-500">Inactiva</span>
                    </div>

                    <!-- KPI strip -->
                    <div class="grid grid-cols-3 gap-2 mb-4">
                        <div class="rounded-xl p-2.5 bg-emerald-50">
                            <p class="text-xs font-bold uppercase tracking-wider text-emerald-600 leading-none mb-1">Disponibilidad</p>
                            <p class="text-lg font-bold text-gray-900 leading-none">
                                {{ plant.kpi?.avg_availability != null ? plant.kpi.avg_availability.toFixed(1) + '%' : '—' }}
                            </p>
                        </div>
                        <div class="rounded-xl p-2.5 bg-blue-50">
                            <p class="text-xs font-bold uppercase tracking-wider text-blue-600 leading-none mb-1">Equipos</p>
                            <p class="text-lg font-bold text-gray-900 leading-none">{{ plant.equipment_count }}</p>
                        </div>
                        <div class="rounded-xl p-2.5 bg-red-50">
                            <p class="text-xs font-bold uppercase tracking-wider text-red-600 leading-none mb-1">Fallas</p>
                            <p class="text-lg font-bold text-gray-900 leading-none">{{ plant.kpi?.total_failures ?? '—' }}</p>
                        </div>
                    </div>

                    <!-- Tab bar -->
                    <div class="flex gap-0 overflow-x-auto border-t border-gray-100">
                        <button v-for="tab in tabs" :key="tab.id" @click="activeTab = tab.id"
                            class="shrink-0 px-4 py-3 text-sm font-medium transition-colors border-b-2 -mb-px"
                            :class="activeTab === tab.id
                                ? 'border-emerald-500 text-emerald-700 font-semibold'
                                : 'border-transparent text-gray-500 hover:text-gray-800 hover:border-gray-300'">
                            {{ tab.label }}
                            <span v-if="tab.count" class="ml-1 text-xs font-bold bg-gray-100 text-gray-500 rounded-full px-1.5 py-0.5">{{ tab.count }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- ── Content ──────────────────────────────────────────────────────── -->
            <div class="max-w-4xl mx-auto px-4 lg:px-8 py-6 space-y-6">

                <!-- Áreas -->
                <div v-if="activeTab === 'areas'">
                    <h2 class="text-xs font-bold uppercase tracking-widest text-gray-500 mb-3">Áreas</h2>

                    <div v-if="plant.areas?.length" class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                        <button v-for="area in plant.areas" :key="area.id"
                            @click="navigateToArea(area)"
                            class="bg-white rounded-2xl border border-gray-100 shadow-sm hover:border-emerald-200 hover:shadow-md transition-all p-4 text-left flex items-start gap-3">
                            <div class="w-8 h-8 rounded-lg bg-slate-100 flex items-center justify-center shrink-0 mt-0.5">
                                <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/>
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-mono font-bold text-gray-400 uppercase">{{ area.code }}</p>
                                <p class="text-sm font-bold text-gray-900 mt-0.5">{{ area.name }}</p>
                                <p v-if="area.description" class="text-xs text-gray-500 mt-0.5 line-clamp-1">{{ area.description }}</p>
                                <p class="text-xs text-gray-400 mt-1">{{ area.equipment_count }} equipo{{ area.equipment_count !== 1 ? 's' : '' }}</p>
                            </div>
                            <svg class="w-4 h-4 text-gray-300 shrink-0 mt-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                        </button>
                    </div>

                    <div v-else class="bg-white rounded-2xl border border-gray-100 shadow-sm py-10 text-center text-xs text-gray-500">
                        Sin áreas registradas en esta planta
                    </div>
                </div>

                <!-- Indicadores -->
                <div v-if="activeTab === 'kpis'">
                    <h2 class="text-xs font-bold uppercase tracking-widest text-gray-500 mb-3">Indicadores de flota</h2>

                    <div v-if="plant.kpi" class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                            <p class="text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">Disponibilidad promedio</p>
                            <p class="text-3xl font-bold" :class="plant.kpi.avg_availability >= 95 ? 'text-emerald-600' : plant.kpi.avg_availability >= 85 ? 'text-amber-500' : 'text-red-500'">
                                {{ plant.kpi.avg_availability != null ? plant.kpi.avg_availability.toFixed(1) + '%' : '—' }}
                            </p>
                        </div>
                        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                            <p class="text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">MTBF promedio</p>
                            <p class="text-3xl font-bold text-gray-900">
                                {{ plant.kpi.avg_mtbf != null ? plant.kpi.avg_mtbf.toFixed(0) + ' h' : '—' }}
                            </p>
                        </div>
                        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                            <p class="text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">Total de fallas</p>
                            <p class="text-3xl font-bold text-red-600">{{ plant.kpi.total_failures }}</p>
                        </div>
                    </div>
                    <div v-else class="bg-white rounded-2xl border border-gray-100 shadow-sm py-10 text-center text-xs text-gray-500">
                        KPIs aún no calculados para esta planta
                    </div>
                </div>

                <!-- Mapa (placeholder) -->
                <div v-if="activeTab === 'mapa'">
                    <h2 class="text-xs font-bold uppercase tracking-widest text-gray-500 mb-3">Ubicación</h2>

                    <div v-if="plant.address" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 mb-4">
                        <p class="text-xs text-gray-500 mb-0.5">Dirección</p>
                        <p class="text-sm font-semibold text-gray-800">{{ plant.address }}</p>
                        <p v-if="plant.city" class="text-xs text-gray-500 mt-0.5">{{ [plant.city, plant.state_province, plant.country_code].filter(Boolean).join(', ') }}</p>
                    </div>

                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-12 flex flex-col items-center gap-3 text-center">
                        <svg class="w-12 h-12 text-gray-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 00-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0z"/>
                        </svg>
                        <p class="text-sm font-semibold text-gray-400">Mapa en desarrollo</p>
                        <p v-if="plant.latitude && plant.longitude" class="text-xs text-gray-400 font-mono">
                            {{ Number(plant.latitude).toFixed(5) }}, {{ Number(plant.longitude).toFixed(5) }}
                        </p>
                    </div>
                </div>

            </div>
        </template>

        <!-- Not found -->
        <div v-else class="p-8 text-center text-xs text-gray-500">Planta no encontrada</div>

    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useApi } from '../composables/useApi.js'

const route  = useRoute()
const router = useRouter()
const api    = useApi()

const loading = ref(true)
const plant   = ref(null)
const activeTab = ref('areas')

// ── Tabs ──────────────────────────────────────────────────────────────────────

const tabs = computed(() => {
    if (!plant.value) { return [] }
    return [
        { id: 'areas', label: 'Áreas', count: plant.value.areas?.length || null },
        { id: 'kpis',  label: 'Indicadores' },
        { id: 'mapa',  label: 'Mapa' },
    ]
})

// ── Back navigation ───────────────────────────────────────────────────────────

const backLabel = computed(() => {
    const from = route.query.from
    if (from === 'ops.equipos') { return 'Equipos' }
    if (from === 'ops.equipos.show') { return 'Equipo' }
    if (from === 'ops.dashboard') { return 'Dashboard' }
    return 'Volver'
})

function goBack() {
    const from = route.query.from
    const fromId = route.query.fromId
    if (from && fromId && ['ops.equipos.show'].includes(from)) {
        router.push({ name: from, params: { id: fromId } })
    } else if (from && ['ops.equipos', 'ops.dashboard'].includes(from)) {
        router.push({ name: from })
    } else {
        router.push({ name: 'ops.equipos' })
    }
}

function navigateToArea(area) {
    router.push({
        name: 'ops.areas.show',
        params: { id: area.id },
        query: { from: 'ops.plantes.show', fromId: plant.value.id },
    })
}

// ── Data loading ──────────────────────────────────────────────────────────────

async function loadPlant() {
    try {
        const res = await api.get(`plants/${route.params.id}/summary`)
        plant.value = res ?? null
    } catch { /* silent */ } finally {
        loading.value = false
    }
}

onMounted(loadPlant)
</script>
