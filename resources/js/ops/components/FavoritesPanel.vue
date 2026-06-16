<template>
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="flex items-center gap-2 px-5 py-4 border-b border-gray-100">
            <svg class="w-4 h-4 text-amber-500" fill="currentColor" viewBox="0 0 24 24">
                <path d="M11.48 3.5c.2-.48.84-.48 1.04 0l2.08 5.01 5.4.43c.52.04.73.69.34 1.03l-4.12 3.53 1.26 5.27c.12.5-.43.91-.87.63L12 16.69l-4.62 2.71c-.44.27-.99-.13-.87-.63l1.26-5.27-4.12-3.53c-.39-.34-.18-.99.34-1.03l5.4-.43L11.48 3.5z"/>
            </svg>
            <h2 class="text-sm font-semibold text-gray-800">Favoritos</h2>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="px-5 py-4 space-y-2">
            <div v-for="i in 3" :key="i" class="skeleton h-4 w-1/2 rounded" />
        </div>

        <!-- Empty -->
        <div v-else-if="isEmpty" class="py-10 px-5 text-center">
            <p class="text-sm text-gray-500">No hay favoritos todavía.</p>
            <p class="text-xs text-gray-400 mt-1">Marca equipos, OT, repuestos o preventivos con la estrella ☆</p>
        </div>

        <!-- Groups -->
        <div v-else class="divide-y divide-gray-50">
            <div v-for="group in nonEmptyGroups" :key="group.type" class="px-5 py-3">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-400 mb-1.5">{{ group.label }}</p>
                <div class="space-y-0.5">
                    <RouterLink
                        v-for="item in group.items"
                        :key="item.id"
                        :to="group.route(item.id)"
                        class="flex items-center gap-2 py-1.5 px-2 -mx-2 rounded-lg hover:bg-gray-50 transition-colors"
                    >
                        <span class="text-sm text-gray-900 truncate flex-1">{{ item.title }}</span>
                        <span v-if="item.subtitle" class="text-xs text-gray-400 font-mono shrink-0">{{ item.subtitle }}</span>
                    </RouterLink>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { useApi } from '../composables/useApi.js'
import { useFavorites } from '../composables/useFavorites.js'

const api = useApi()
const loading = ref(true)

const TYPES = [
    { type: 'equipment', label: 'Equipos', endpoint: (id) => `equipment/${id}`, map: (d) => ({ id: d.id, title: d.name, subtitle: d.code }), route: (id) => ({ name: 'ops.equipos.show', params: { id } }) },
    { type: 'workorders', label: 'Órdenes de trabajo', endpoint: (id) => `work-orders/${id}`, map: (d) => ({ id: d.id, title: d.title, subtitle: d.work_order_number }), route: (id) => ({ name: 'ops.ordenes.show', params: { id } }) },
    { type: 'spareparts', label: 'Repuestos', endpoint: (id) => `inventory/spare-parts/${id}`, map: (d) => ({ id: d.id, title: d.name, subtitle: d.code }), route: () => ({ name: 'ops.repuestos' }) },
    { type: 'preventives', label: 'Preventivos', endpoint: (id) => `maintenance-plans/${id}`, map: (d) => ({ id: d.id, title: d.name, subtitle: d.plan_number }), route: () => ({ name: 'ops.preventivos' }) },
]

const groups = ref(TYPES.map((t) => ({ type: t.type, label: t.label, route: t.route, items: [] })))

const nonEmptyGroups = computed(() => groups.value.filter((g) => g.items.length > 0))
const isEmpty = computed(() => nonEmptyGroups.value.length === 0)

async function resolveType(config, target) {
    const ids = useFavorites(config.type).items.value
    if (! ids.length) { return }

    // Resolve each favorited UUID to its record; ignore ones that no longer
    // resolve (deleted, or belong to a different tenant in this browser).
    const results = await Promise.allSettled(ids.map((id) => api.get(config.endpoint(id))))
    target.items = results
        .filter((r) => r.status === 'fulfilled' && r.value?.data)
        .map((r) => config.map(r.value.data))
}

onMounted(async () => {
    try {
        await Promise.all(TYPES.map((config, i) => resolveType(config, groups.value[i])))
    } catch { /* silent */ } finally {
        loading.value = false
    }
})
</script>
