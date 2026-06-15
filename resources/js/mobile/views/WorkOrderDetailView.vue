<template>
    <AppLayout :title="store.currentItem?.work_order_number ?? 'Orden de trabajo'" show-back>
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
        <div v-else-if="wo" class="pb-6">
            <!-- Header card -->
            <div class="px-4 pt-5 pb-4 border-b border-zinc-800">
                <div class="flex gap-2 flex-wrap mb-3">
                    <StatusBadge :status="wo.status" />
                    <PriorityBadge :priority="wo.priority" />
                </div>
                <h2 class="text-xl font-semibold text-zinc-100 leading-snug">{{ wo.title }}</h2>
                <p class="text-xs text-zinc-400 mt-1">{{ wo.work_order_number }}</p>
            </div>

            <!-- Info section -->
            <div class="px-4 py-4 space-y-4">

                <!-- Equipment -->
                <div v-if="wo.equipment" class="bg-zinc-900 rounded-2xl p-4">
                    <p class="text-xs font-medium text-zinc-400 uppercase tracking-wide mb-2">Equipo</p>
                    <RouterLink
                        :to="{ name: 'equipment-detail', params: { id: wo.equipment.id } }"
                        class="flex items-center justify-between group"
                    >
                        <div>
                            <p class="font-semibold text-zinc-100">{{ wo.equipment.name }}</p>
                            <p class="text-sm text-zinc-400">{{ wo.equipment.code }}</p>
                        </div>
                        <svg class="w-4 h-4 text-zinc-400 group-hover:text-zinc-400 transition shrink-0"
                             fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                        </svg>
                    </RouterLink>
                </div>

                <!-- Description -->
                <div v-if="wo.description" class="bg-zinc-900 rounded-2xl p-4">
                    <p class="text-xs font-medium text-zinc-400 uppercase tracking-wide mb-2">Descripción</p>
                    <p class="text-sm text-zinc-300 leading-relaxed">{{ wo.description }}</p>
                </div>

                <!-- Dates -->
                <div class="bg-zinc-900 rounded-2xl p-4 space-y-3">
                    <p class="text-xs font-medium text-zinc-400 uppercase tracking-wide">Fechas</p>
                    <div v-if="wo.planned_start_at" class="flex justify-between text-sm">
                        <span class="text-zinc-400">Inicio planificado</span>
                        <span class="text-zinc-300">{{ formatDate(wo.planned_start_at) }}</span>
                    </div>
                    <div v-if="wo.actual_start_at" class="flex justify-between text-sm">
                        <span class="text-zinc-400">Inicio real</span>
                        <span class="text-zinc-300">{{ formatDate(wo.actual_start_at) }}</span>
                    </div>
                    <div v-if="wo.completed_at" class="flex justify-between text-sm">
                        <span class="text-zinc-400">Completada</span>
                        <span class="text-zinc-300">{{ formatDate(wo.completed_at) }}</span>
                    </div>
                </div>

                <!-- Actions -->
                <div class="bg-zinc-900 rounded-2xl overflow-hidden">
                    <p class="px-4 pt-4 pb-3 text-xs font-medium text-zinc-400 uppercase tracking-wide">
                        Acciones
                    </p>
                    <div class="divide-y divide-zinc-800">
                        <!-- Time entry -->
                        <button
                            @click="showTimeSheet = true"
                            class="w-full flex items-center gap-4 px-4 py-4 text-left hover:bg-zinc-800/60 transition active:bg-zinc-800"
                        >
                            <div class="w-10 h-10 rounded-xl bg-blue-500/10 flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="font-medium text-zinc-100 text-sm">Registrar tiempo</p>
                                <p class="text-xs text-zinc-400">Inicio y fin de trabajo</p>
                            </div>
                        </button>

                        <!-- Comment -->
                        <button
                            @click="showCommentSheet = true"
                            class="w-full flex items-center gap-4 px-4 py-4 text-left hover:bg-zinc-800/60 transition active:bg-zinc-800"
                        >
                            <div class="w-10 h-10 rounded-xl bg-green-500/10 flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 0 1 .865-.501 48.172 48.172 0 0 0 3.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="font-medium text-zinc-100 text-sm">Agregar comentario</p>
                                <p class="text-xs text-zinc-400">Nota pública o interna</p>
                            </div>
                        </button>

                        <!-- Photo -->
                        <button
                            @click="showMediaSheet = true"
                            class="w-full flex items-center gap-4 px-4 py-4 text-left hover:bg-zinc-800/60 transition active:bg-zinc-800"
                        >
                            <div class="w-10 h-10 rounded-xl bg-purple-500/10 flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="font-medium text-zinc-100 text-sm">Subir foto</p>
                                <p class="text-xs text-zinc-400">Antes, después o evidencia</p>
                            </div>
                        </button>

                        <!-- Signature -->
                        <button
                            @click="showSignatureSheet = true"
                            class="w-full flex items-center gap-4 px-4 py-4 text-left hover:bg-zinc-800/60 transition active:bg-zinc-800"
                        >
                            <div class="w-10 h-10 rounded-xl bg-amber-500/10 flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125"/>
                                </svg>
                            </div>
                            <div>
                                <p class="font-medium text-zinc-100 text-sm">Firmar OT</p>
                                <p class="text-xs text-zinc-400">Firma de completado técnico</p>
                            </div>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bottom sheets -->
        <BottomSheet v-model:open="showTimeSheet" title="Registrar tiempo">
            <WorkOrderTimeForm :work-order-id="workOrderId" @saved="showTimeSheet = false" />
        </BottomSheet>

        <BottomSheet v-model:open="showCommentSheet" title="Agregar comentario">
            <WorkOrderCommentsForm :work-order-id="workOrderId" @saved="showCommentSheet = false" />
        </BottomSheet>

        <BottomSheet v-model:open="showMediaSheet" title="Subir foto">
            <WorkOrderMediaUpload
                :work-order-id="workOrderId"
                :work-order-number="wo?.work_order_number ?? ''"
                @saved="showMediaSheet = false"
            />
        </BottomSheet>

        <BottomSheet v-model:open="showSignatureSheet" title="Firmar OT" tall>
            <SignatureCanvas
                :work-order-id="workOrderId"
                :work-order-number="wo?.work_order_number ?? ''"
                @saved="showSignatureSheet = false"
            />
        </BottomSheet>
    </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { useNetworkStore } from '../stores/networkStore.js'
import AppLayout from '../components/AppLayout.vue'
import StatusBadge from '../components/StatusBadge.vue'
import PriorityBadge from '../components/PriorityBadge.vue'
import BottomSheet from '../components/BottomSheet.vue'
import WorkOrderTimeForm from '../components/WorkOrderTimeForm.vue'
import WorkOrderCommentsForm from '../components/WorkOrderCommentsForm.vue'
import WorkOrderMediaUpload from '../components/WorkOrderMediaUpload.vue'
import SignatureCanvas from '../components/SignatureCanvas.vue'
import { useWorkOrdersStore } from '../stores/workOrders.js'

const route = useRoute()
const store = useWorkOrdersStore()
const network = useNetworkStore()

const wo = computed(() => store.currentItem)

// Refetch when a sync completes in case this WO's data changed server-side
watch(() => network.syncRevision, () => {
    if (route.params.id) store.fetchOne(route.params.id)
})
const workOrderId = computed(() => String(route.params.id))

const showTimeSheet = ref(false)
const showCommentSheet = ref(false)
const showMediaSheet = ref(false)
const showSignatureSheet = ref(false)

function formatDate(iso) {
    if (!iso) return '—'
    return new Intl.DateTimeFormat('es', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(iso))
}

onMounted(() => store.fetchOne(route.params.id))
</script>
