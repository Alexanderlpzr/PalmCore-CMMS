<template>
    <div class="p-5 lg:p-8 max-w-5xl mx-auto">

        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-xl font-bold text-gray-900">Almacenes</h1>
            <p v-if="!loading" class="text-sm text-gray-500 mt-0.5">{{ warehouses.length }} almacén{{ warehouses.length !== 1 ? 'es' : '' }}</p>
        </div>

        <!-- Fleet summary strip -->
        <div v-if="!loading && warehouses.length" class="grid grid-cols-3 gap-3 mb-6">
            <div class="bg-white rounded-2xl border border-gray-100 p-3.5 text-center">
                <p class="text-2xl font-bold text-gray-900">{{ totalItems }}</p>
                <p class="text-xs text-gray-500 mt-0.5">ítems en stock</p>
            </div>
            <div class="bg-white rounded-2xl border border-gray-100 p-3.5 text-center">
                <p class="text-2xl font-bold text-gray-900">{{ formatCurrency(totalValue) }}</p>
                <p class="text-xs text-gray-500 mt-0.5">valor total</p>
            </div>
            <div class="bg-white rounded-2xl border border-gray-100 p-3.5 text-center">
                <p class="text-2xl font-bold" :class="totalLowStock > 0 ? 'text-red-600' : 'text-gray-900'">{{ totalLowStock }}</p>
                <p class="text-xs text-gray-500 mt-0.5">stock bajo</p>
            </div>
        </div>

        <!-- Skeleton -->
        <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div v-for="i in 4" :key="i" class="bg-white rounded-2xl border border-gray-100 p-5 space-y-3">
                <div class="skeleton h-5 w-2/3 rounded" />
                <div class="skeleton h-3 w-1/3 rounded" />
                <div class="grid grid-cols-3 gap-2 mt-4">
                    <div v-for="j in 3" :key="j" class="skeleton h-12 rounded-xl" />
                </div>
            </div>
        </div>

        <!-- Warehouse grid -->
        <div v-else-if="warehouses.length" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div
                v-for="wh in warehouses"
                :key="wh.id"
                class="bg-white rounded-2xl border shadow-sm hover:shadow-md transition-all cursor-pointer group"
                :class="wh.is_active ? 'border-gray-100 hover:border-gray-200' : 'border-gray-100 opacity-70'"
                @click="selectedWarehouse = wh"
            >
                <div class="p-5">
                    <!-- Top row -->
                    <div class="flex items-start justify-between gap-2 mb-1">
                        <div class="flex items-center gap-2">
                            <div class="w-9 h-9 rounded-xl bg-indigo-50 flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-base font-bold text-gray-900 leading-tight group-hover:text-indigo-600 transition-colors">{{ wh.name }}</p>
                                <span class="font-mono text-xs text-gray-500">{{ wh.code }}</span>
                            </div>
                        </div>
                        <span v-if="!wh.is_active" class="text-xs font-semibold bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full shrink-0">Inactivo</span>
                        <span v-else-if="wh.low_stock_count > 0" class="text-xs font-bold bg-red-100 text-red-600 px-2 py-0.5 rounded-full shrink-0 flex items-center gap-1">
                            <span class="w-1.5 h-1.5 rounded-full bg-red-500" />
                            {{ wh.low_stock_count }} bajo mínimo
                        </span>
                    </div>

                    <!-- Location -->
                    <div v-if="wh.location" class="flex items-center gap-1.5 text-xs text-gray-500 mt-2 mb-4">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                        </svg>
                        {{ wh.location }}
                    </div>
                    <div v-else class="mb-4" />

                    <!-- Stats grid -->
                    <div class="grid grid-cols-3 gap-2">
                        <div class="bg-gray-50 rounded-xl p-3 text-center">
                            <p class="text-lg font-bold text-gray-900">{{ wh.items_count }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">repuestos</p>
                        </div>
                        <div class="bg-gray-50 rounded-xl p-3 text-center">
                            <p class="text-sm font-bold text-gray-900 leading-tight">{{ formatCurrencyShort(wh.total_inventory_value) }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">valor est.</p>
                        </div>
                        <div class="rounded-xl p-3 text-center" :class="wh.low_stock_count > 0 ? 'bg-red-50' : 'bg-gray-50'">
                            <p class="text-lg font-bold" :class="wh.low_stock_count > 0 ? 'text-red-600' : 'text-gray-500'">{{ wh.low_stock_count }}</p>
                            <p class="text-xs mt-0.5" :class="wh.low_stock_count > 0 ? 'text-red-400' : 'text-gray-500'">bajo mín.</p>
                        </div>
                    </div>

                    <!-- Description -->
                    <p v-if="wh.description" class="text-xs text-gray-500 mt-3 leading-relaxed line-clamp-2">{{ wh.description }}</p>
                </div>
            </div>
        </div>

        <!-- Empty -->
        <EmptyState v-else icon="warehouse" title="Sin almacenes registrados" />

        <!-- ── Stock drawer ─────────────────────────────────────────────── -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="selectedWarehouse" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center" @click.self="closeDrawer">
                    <div class="absolute inset-0 bg-black/40" @click="closeDrawer" />

                    <div class="relative z-10 w-full sm:max-w-xl bg-white rounded-t-3xl sm:rounded-2xl shadow-2xl max-h-[85vh] flex flex-col">
                        <!-- Drawer handle -->
                        <div class="flex-none pt-3 pb-1 flex justify-center sm:hidden">
                            <div class="w-10 h-1 rounded-full bg-gray-200" />
                        </div>

                        <!-- Drawer header -->
                        <div class="flex-none px-5 py-4 border-b border-gray-100 flex items-start justify-between">
                            <div>
                                <p class="font-bold text-gray-900">{{ selectedWarehouse.name }}</p>
                                <p v-if="selectedWarehouse.location" class="text-xs text-gray-500 mt-0.5 flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                                    </svg>
                                    {{ selectedWarehouse.location }}
                                </p>
                            </div>
                            <button @click="closeDrawer" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 text-gray-500 transition-colors shrink-0">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <!-- Stock loading -->
                        <div v-if="stockLoading" class="flex-1 p-5 space-y-2 overflow-y-auto">
                            <div v-for="i in 6" :key="i" class="flex items-center gap-3 p-3 border border-gray-100 rounded-xl">
                                <div class="skeleton h-4 w-16 rounded" />
                                <div class="skeleton h-4 flex-1 rounded" />
                                <div class="skeleton h-4 w-12 rounded" />
                            </div>
                        </div>

                        <!-- Stock list -->
                        <div v-else class="flex-1 overflow-y-auto">
                            <div v-if="!drawerStock.length" class="p-8 text-center">
                                <p class="text-sm text-gray-500">Sin repuestos en este almacén</p>
                            </div>
                            <div v-else>
                                <!-- Search inside drawer -->
                                <div class="px-5 py-3 border-b border-gray-50">
                                    <input
                                        v-model="stockSearch"
                                        type="text"
                                        placeholder="Buscar repuesto..."
                                        class="w-full text-sm border border-gray-200 rounded-xl px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                    />
                                </div>
                                <div class="divide-y divide-gray-50">
                                    <div
                                        v-for="item in filteredStock"
                                        :key="item.id"
                                        class="flex items-center gap-3 px-5 py-3"
                                        :class="item.spare_part?.is_below_minimum ? 'bg-red-50/50' : ''"
                                    >
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-1.5 mb-0.5">
                                                <span class="font-mono text-xs text-gray-500">{{ item.spare_part?.code }}</span>
                                                <span v-if="item.spare_part?.is_below_minimum" class="text-xs font-semibold text-red-600 bg-red-100 px-1.5 py-0.5 rounded-full">Bajo mín.</span>
                                                <span v-if="item.bin_location" class="text-xs text-gray-500 bg-gray-100 px-1.5 py-0.5 rounded-full">{{ item.bin_location }}</span>
                                            </div>
                                            <p class="text-sm font-medium text-gray-900 truncate">{{ item.spare_part?.name }}</p>
                                        </div>
                                        <div class="text-right shrink-0">
                                            <p class="text-sm font-bold" :class="item.spare_part?.is_below_minimum ? 'text-red-600' : 'text-gray-900'">
                                                {{ item.current_stock.toFixed(0) }}
                                            </p>
                                            <p class="text-xs text-gray-500">{{ item.spare_part?.unit }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Drawer footer -->
                        <div class="flex-none px-5 py-4 border-t border-gray-100 flex items-center justify-between">
                            <p class="text-xs text-gray-500">{{ drawerStock.length }} repuestos</p>
                            <p class="text-xs font-semibold text-gray-700">
                                Valor: {{ formatCurrency(selectedWarehouse.total_inventory_value) }}
                            </p>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>

    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useApi } from '../composables/useApi.js'
import EmptyState from '../components/EmptyState.vue'

const api = useApi()
const warehouses = ref([])
const loading = ref(true)
const selectedWarehouse = ref(null)
const drawerStock = ref([])
const stockLoading = ref(false)
const stockSearch = ref('')

// ── Fleet aggregates ──────────────────────────────────────────────────────────

const totalItems = computed(() => warehouses.value.reduce((s, w) => s + w.items_count, 0))
const totalValue = computed(() => warehouses.value.reduce((s, w) => s + w.total_inventory_value, 0))
const totalLowStock = computed(() => warehouses.value.reduce((s, w) => s + w.low_stock_count, 0))

// ── Drawer stock filter ───────────────────────────────────────────────────────

const filteredStock = computed(() => {
    const q = stockSearch.value.trim().toLowerCase()
    if (!q) { return drawerStock.value }
    return drawerStock.value.filter(item =>
        item.spare_part?.code?.toLowerCase().includes(q) ||
        item.spare_part?.name?.toLowerCase().includes(q)
    )
})

// ── Helpers ───────────────────────────────────────────────────────────────────

function formatCurrency(amount) {
    return new Intl.NumberFormat('es', { style: 'currency', currency: 'MXN', maximumFractionDigits: 0 }).format(amount)
}

function formatCurrencyShort(amount) {
    if (amount >= 1_000_000) { return `$${(amount / 1_000_000).toFixed(1)}M` }
    if (amount >= 1_000) { return `$${(amount / 1_000).toFixed(0)}k` }
    return `$${amount.toFixed(0)}`
}

// ── Drawer ────────────────────────────────────────────────────────────────────

async function openDrawer(warehouse) {
    selectedWarehouse.value = warehouse
    stockSearch.value = ''
    drawerStock.value = []
    stockLoading.value = true
    try {
        const res = await api.get(`inventory/warehouses/${warehouse.id}`)
        drawerStock.value = res?.data?.stock ?? []
    } catch { /* silent */ } finally {
        stockLoading.value = false
    }
}

function closeDrawer() {
    selectedWarehouse.value = null
}

watch(selectedWarehouse, (wh) => {
    if (wh) { openDrawer(wh) }
})

// ── API ───────────────────────────────────────────────────────────────────────

async function load() {
    loading.value = true
    try {
        const res = await api.get('inventory/warehouses?per_page=100&is_active=true')
        warehouses.value = res?.data ?? []
    } catch { /* silent */ } finally {
        loading.value = false
    }
}

onMounted(load)
</script>
