<template>
    <AppLayout title="Mis OTs">
        <!-- Status filter pills -->
        <div class="px-4 pt-4 pb-2 flex gap-2 overflow-x-auto scrollbar-none">
            <button
                v-for="f in filters"
                :key="f.value"
                @click="activeFilter = f.value"
                :class="[
                    'shrink-0 px-4 py-1.5 rounded-full text-sm font-medium transition',
                    activeFilter === f.value
                        ? 'bg-amber-500 text-zinc-950'
                        : 'bg-zinc-800 text-zinc-400 hover:bg-zinc-700'
                ]"
            >
                {{ f.label }}
            </button>
        </div>

        <!-- Loading skeleton -->
        <div v-if="store.loading" class="px-4 py-2 space-y-3">
            <div v-for="i in 4" :key="i" class="bg-zinc-900 rounded-2xl p-4 space-y-2 animate-pulse">
                <div class="h-3 bg-zinc-800 rounded w-1/3"></div>
                <div class="h-4 bg-zinc-800 rounded w-2/3"></div>
                <div class="h-3 bg-zinc-800 rounded w-1/4"></div>
            </div>
        </div>

        <!-- Error state -->
        <div v-else-if="store.error" class="px-4 py-8 text-center">
            <p class="text-red-400 text-sm">{{ store.error }}</p>
            <button @click="store.fetchMine()" class="mt-3 text-amber-400 text-sm underline">
                Reintentar
            </button>
        </div>

        <!-- Empty state -->
        <div v-else-if="filteredItems.length === 0" class="px-4 py-16 text-center">
            <div class="w-16 h-16 rounded-2xl bg-zinc-900 flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-zinc-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2"/>
                </svg>
            </div>
            <p class="text-zinc-500 text-sm">No hay órdenes de trabajo</p>
        </div>

        <!-- List -->
        <div v-else class="px-4 py-2 space-y-3">
            <WorkOrderCard
                v-for="wo in filteredItems"
                :key="wo.id"
                :work-order="wo"
                @click="router.push({ name: 'work-order-detail', params: { id: wo.id } })"
            />
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import AppLayout from '../components/AppLayout.vue'
import WorkOrderCard from '../components/WorkOrderCard.vue'
import { useWorkOrdersStore } from '../stores/workOrders.js'

const router = useRouter()
const store = useWorkOrdersStore()

const filters = [
    { label: 'Todas', value: '' },
    { label: 'En proceso', value: 'in_progress' },
    { label: 'Planificadas', value: 'planned' },
    { label: 'Completadas', value: 'completed' },
]
const activeFilter = ref('')

const filteredItems = computed(() =>
    activeFilter.value
        ? store.items.filter(wo => wo.status === activeFilter.value)
        : store.items
)

onMounted(() => store.fetchMine())
</script>
