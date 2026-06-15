<template>
    <AppLayout :title="store.currentItem?.code ?? 'Equipo'" show-back>
        <!-- Loading -->
        <div v-if="store.loading" class="px-4 py-6 space-y-4 animate-pulse">
            <div class="h-4 bg-zinc-800 rounded w-1/3"></div>
            <div class="h-6 bg-zinc-800 rounded w-2/3"></div>
            <div class="h-4 bg-zinc-800 rounded w-1/2"></div>
        </div>

        <!-- Error -->
        <div v-else-if="store.error" class="px-4 py-8 text-center">
            <p class="text-red-400 text-sm">{{ store.error }}</p>
        </div>

        <!-- Detail -->
        <div v-else-if="eq" class="pb-6">
            <!-- Header -->
            <div class="px-4 pt-5 pb-4 border-b border-zinc-800">
                <div class="flex gap-2 flex-wrap mb-3">
                    <EquipmentStatusBadge :status="eq.status" />
                    <CriticalityBadge v-if="eq.criticality" :criticality="eq.criticality" />
                </div>
                <h2 class="text-xl font-semibold text-zinc-100 leading-snug">{{ eq.name }}</h2>
                <p class="text-xs text-zinc-400 mt-1">{{ eq.code }}</p>
            </div>

            <!-- Info -->
            <div class="px-4 py-4 space-y-4">

                <!-- Location -->
                <div class="bg-zinc-900 rounded-2xl p-4 space-y-3">
                    <p class="text-xs font-medium text-zinc-400 uppercase tracking-wide">Ubicación</p>
                    <div v-if="eq.plant" class="flex justify-between text-sm">
                        <span class="text-zinc-400">Planta</span>
                        <span class="text-zinc-300">{{ eq.plant.name }}</span>
                    </div>
                    <div v-if="eq.area" class="flex justify-between text-sm">
                        <span class="text-zinc-400">Área</span>
                        <span class="text-zinc-300">{{ eq.area.name }}</span>
                    </div>
                    <div v-if="eq.category" class="flex justify-between text-sm">
                        <span class="text-zinc-400">Categoría</span>
                        <span class="text-zinc-300">{{ eq.category.name }}</span>
                    </div>
                </div>

                <!-- Technical info -->
                <div class="bg-zinc-900 rounded-2xl p-4 space-y-3">
                    <p class="text-xs font-medium text-zinc-400 uppercase tracking-wide">Datos técnicos</p>
                    <div v-if="eq.installation_date" class="flex justify-between text-sm">
                        <span class="text-zinc-400">Instalación</span>
                        <span class="text-zinc-300">{{ formatDate(eq.installation_date) }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-zinc-400">Estado activo</span>
                        <span :class="eq.is_active ? 'text-green-400' : 'text-red-400'">
                            {{ eq.is_active ? 'Sí' : 'No' }}
                        </span>
                    </div>
                </div>

                <!-- Notes -->
                <div v-if="eq.notes" class="bg-zinc-900 rounded-2xl p-4">
                    <p class="text-xs font-medium text-zinc-400 uppercase tracking-wide mb-2">Notas</p>
                    <p class="text-sm text-zinc-300 leading-relaxed">{{ eq.notes }}</p>
                </div>

            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import AppLayout from '../components/AppLayout.vue'
import EquipmentStatusBadge from '../components/EquipmentStatusBadge.vue'
import CriticalityBadge from '../components/CriticalityBadge.vue'
import { useEquipmentStore } from '../stores/equipment.js'

const route = useRoute()
const store = useEquipmentStore()

const eq = computed(() => store.currentItem)

function formatDate(iso) {
    if (!iso) return '—'
    return new Intl.DateTimeFormat('es', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(iso))
}

onMounted(() => store.fetchById(route.params.id))
</script>
