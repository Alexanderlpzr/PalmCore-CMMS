<template>
    <AppLayout :title="store.currentItem?.code ?? 'Equipo'" show-back>
        <!-- Skeleton -->
        <div v-if="store.loading" class="px-4 py-5 space-y-4 animate-pulse">
            <div class="h-5 bg-zinc-800 rounded w-2/5"></div>
            <div class="h-7 bg-zinc-800 rounded w-3/4"></div>
            <div class="h-4 bg-zinc-800 rounded w-1/2"></div>
            <div class="h-24 bg-zinc-800 rounded-2xl mt-4"></div>
            <div class="h-32 bg-zinc-800 rounded-2xl"></div>
        </div>

        <!-- Error -->
        <div v-else-if="store.error" class="px-4 py-12 text-center space-y-3">
            <p class="text-red-400 text-sm">{{ store.error }}</p>
            <button
                class="text-indigo-400 text-sm underline"
                @click="store.fetchById(route.params.id)"
            >
                Reintentar
            </button>
        </div>

        <!-- Content -->
        <div v-else-if="eq" class="pb-24">

            <!-- ── Header ─────────────────────────────────────────── -->
            <div class="px-4 pt-5 pb-4 border-b border-zinc-800">
                <div class="flex gap-2 flex-wrap mb-2">
                    <EquipmentStatusBadge :status="eq.status" />
                    <CriticalityBadge v-if="eq.criticality" :criticality="eq.criticality" />
                    <span
                        v-if="store.isFromCache"
                        class="inline-flex items-center gap-1 text-[10px] font-medium px-2 py-0.5 rounded-full bg-zinc-800 text-zinc-400"
                    >
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/>
                        </svg>
                        Caché
                    </span>
                </div>
                <h2 class="text-xl font-semibold text-zinc-100 leading-snug">{{ eq.name }}</h2>
                <p class="text-xs text-zinc-500 mt-1 font-mono">{{ eq.code }}</p>
                <p v-if="eq.plant || eq.area" class="text-xs text-zinc-400 mt-1.5">
                    {{ [eq.plant?.name, eq.area?.name].filter(Boolean).join(' · ') }}
                </p>
            </div>

            <!-- ── Alert: Active OT ───────────────────────────────── -->
            <div
                v-if="activeWos.length > 0"
                class="mx-4 mt-4 bg-amber-950/60 border border-amber-700/50 rounded-2xl px-4 py-3"
            >
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-amber-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-7.61m5.12-1.023a3.024 3.024 0 00.384-5.12l-5.12 5.12"/>
                    </svg>
                    <div class="min-w-0 flex-1">
                        <p class="text-amber-300 text-sm font-semibold">En intervención activa</p>
                        <p v-for="wo in activeWos.slice(0, 2)" :key="wo.id" class="text-amber-200/80 text-xs mt-0.5 truncate">
                            {{ wo.work_order_number }} · {{ wo.title }}
                        </p>
                        <p v-if="activeWos.length > 2" class="text-amber-400 text-xs mt-0.5">
                            +{{ activeWos.length - 2 }} más
                        </p>
                    </div>
                    <button
                        class="shrink-0 text-amber-400 text-xs font-medium underline"
                        @click="showActiveWos = true"
                    >
                        Ver
                    </button>
                </div>
            </div>

            <!-- ── Alert: Overdue Preventive ─────────────────────── -->
            <div
                v-if="eq.has_overdue_preventives"
                class="mx-4 mt-3 bg-orange-950/50 border border-orange-700/40 rounded-2xl px-4 py-3"
            >
                <div class="flex items-center gap-3">
                    <svg class="w-5 h-5 text-orange-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                    </svg>
                    <p class="text-orange-300 text-sm font-medium">Preventivo vencido pendiente</p>
                </div>
            </div>

            <!-- ── Acciones primarias ─────────────────────────────── -->
            <div class="px-4 mt-4 space-y-2.5">
                <!-- Crear OT -->
                <button
                    class="w-full flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-500 active:bg-indigo-700 text-white font-semibold py-3.5 rounded-2xl transition text-sm"
                    @click="showCreateWo = true"
                >
                    <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                    </svg>
                    Crear orden de trabajo
                </button>

                <div class="grid grid-cols-2 gap-2.5">
                    <!-- Reportar falla -->
                    <button
                        class="flex items-center justify-center gap-1.5 border border-zinc-700 text-zinc-200 font-medium py-3 rounded-2xl transition text-sm hover:border-zinc-600 active:bg-zinc-800"
                        @click="showReport = true"
                    >
                        <svg class="w-4 h-4 text-red-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
                        </svg>
                        Reportar falla
                    </button>

                    <!-- Registrar lectura -->
                    <button
                        class="flex items-center justify-center gap-1.5 border border-zinc-700 text-zinc-200 font-medium py-3 rounded-2xl transition text-sm hover:border-zinc-600 active:bg-zinc-800"
                        @click="showReading = true"
                    >
                        <svg class="w-4 h-4 text-cyan-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/>
                        </svg>
                        Registrar lectura
                    </button>
                </div>
            </div>

            <!-- ── Documentos técnicos ────────────────────────────── -->
            <div v-if="eq.documents?.length" class="px-4 mt-5">
                <p class="text-xs font-semibold text-zinc-400 uppercase tracking-wider mb-2.5">
                    Documentos técnicos ({{ eq.documents.length }})
                </p>
                <div class="space-y-2">
                    <a
                        v-for="doc in eq.documents"
                        :key="doc.id"
                        :href="doc.url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="flex items-center gap-3 bg-zinc-900 border border-zinc-800 rounded-2xl px-4 py-3 hover:border-zinc-700 active:bg-zinc-800 transition"
                    >
                        <span class="text-xl shrink-0">{{ docIcon(doc.type) }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm text-zinc-100 font-medium truncate">{{ doc.title || doc.name }}</p>
                            <p v-if="doc.type" class="text-xs text-zinc-500 mt-0.5 uppercase">{{ doc.type }}</p>
                        </div>
                        <svg class="w-4 h-4 text-zinc-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>
                        </svg>
                    </a>
                </div>
            </div>

            <!-- ── Última intervención ────────────────────────────── -->
            <div v-if="lastWo" class="px-4 mt-5">
                <p class="text-xs font-semibold text-zinc-400 uppercase tracking-wider mb-2.5">Última intervención</p>
                <div class="bg-zinc-900 border border-zinc-800 rounded-2xl px-4 py-3.5">
                    <p class="text-sm text-zinc-100 font-medium truncate">{{ lastWo.title }}</p>
                    <p class="text-xs text-zinc-400 mt-1 font-mono">{{ lastWo.work_order_number }}</p>
                    <div class="flex items-center gap-2 mt-2">
                        <StatusBadge :status="lastWo.status" />
                        <span class="text-xs text-zinc-500">{{ formatRelative(lastWo.completed_at || lastWo.updated_at) }}</span>
                    </div>
                </div>
            </div>

            <!-- ── Datos del equipo (colapsable) ─────────────────── -->
            <div class="px-4 mt-5">
                <button
                    class="w-full flex items-center justify-between text-xs font-semibold text-zinc-400 uppercase tracking-wider mb-2.5"
                    @click="equipDataOpen = !equipDataOpen"
                >
                    <span>Datos del equipo</span>
                    <svg
                        class="w-4 h-4 text-zinc-500 transition-transform"
                        :class="equipDataOpen ? 'rotate-180' : ''"
                        fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>

                <div v-if="equipDataOpen" class="bg-zinc-900 border border-zinc-800 rounded-2xl divide-y divide-zinc-800">
                    <div v-if="eq.model" class="flex justify-between items-center px-4 py-3 text-sm">
                        <span class="text-zinc-400">Modelo</span>
                        <span class="text-zinc-200 text-right ml-4">{{ eq.model }}</span>
                    </div>
                    <div v-if="eq.serial_number" class="flex justify-between items-center px-4 py-3 text-sm">
                        <span class="text-zinc-400">N° serie</span>
                        <span class="text-zinc-200 text-right ml-4 font-mono text-xs">{{ eq.serial_number }}</span>
                    </div>
                    <div v-if="eq.asset_tag" class="flex justify-between items-center px-4 py-3 text-sm">
                        <span class="text-zinc-400">Asset tag</span>
                        <span class="text-zinc-200 text-right ml-4 font-mono text-xs">{{ eq.asset_tag }}</span>
                    </div>
                    <div v-if="eq.manufacturer" class="flex justify-between items-center px-4 py-3 text-sm">
                        <span class="text-zinc-400">Fabricante</span>
                        <span class="text-zinc-200 text-right ml-4">{{ eq.manufacturer.name }}</span>
                    </div>
                    <div v-if="eq.installation_date" class="flex justify-between items-center px-4 py-3 text-sm">
                        <span class="text-zinc-400">Instalación</span>
                        <span class="text-zinc-200 text-right ml-4">{{ formatDate(eq.installation_date) }}</span>
                    </div>
                    <div v-if="eq.current_meter_reading != null" class="flex justify-between items-center px-4 py-3 text-sm">
                        <span class="text-zinc-400">Lectura actual</span>
                        <span class="text-zinc-200 text-right ml-4">{{ eq.current_meter_reading }} {{ eq.meter_unit }}</span>
                    </div>
                    <div v-if="eq.category" class="flex justify-between items-center px-4 py-3 text-sm">
                        <span class="text-zinc-400">Categoría</span>
                        <span class="text-zinc-200 text-right ml-4">{{ eq.category.name }}</span>
                    </div>
                    <div v-if="eq.notes" class="px-4 py-3 text-sm">
                        <span class="text-zinc-400 block mb-1">Notas</span>
                        <p class="text-zinc-300 leading-relaxed">{{ eq.notes }}</p>
                    </div>
                </div>
            </div>

        </div>

        <!-- ── Bottom Sheet: OTs activas ─────────────────────────── -->
        <BottomSheet v-model:open="showActiveWos" title="OTs activas" :tall="true">
            <div class="px-4 py-3 space-y-2">
                <div
                    v-for="wo in activeWos"
                    :key="wo.id"
                    class="bg-zinc-800 rounded-xl p-3"
                >
                    <p class="text-xs text-zinc-400 font-mono mb-0.5">{{ wo.work_order_number }}</p>
                    <p class="text-sm text-zinc-100 font-medium leading-snug">{{ wo.title }}</p>
                    <div class="flex gap-2 mt-2">
                        <StatusBadge :status="wo.status" />
                        <PriorityBadge :priority="wo.priority" />
                    </div>
                </div>
            </div>
        </BottomSheet>

        <!-- ── Bottom Sheet: Crear OT ─────────────────────────────── -->
        <BottomSheet v-model:open="showCreateWo" title="Nueva orden de trabajo" :tall="true">
            <form class="px-4 py-4 space-y-4 pb-8" @submit.prevent="submitCreateWo">
                <div>
                    <label for="m-wo-type" class="block text-xs font-semibold text-zinc-400 mb-1.5">Tipo</label>
                    <select
                        id="m-wo-type"
                        v-model="createWoForm.work_order_type"
                        class="w-full bg-zinc-800 border border-zinc-700 rounded-xl px-3 py-2.5 text-sm text-zinc-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                        <option value="corrective">Correctiva</option>
                        <option value="preventive">Preventiva</option>
                        <option value="predictive">Predictiva</option>
                        <option value="improvement">Mejora</option>
                        <option value="inspection">Inspección</option>
                    </select>
                </div>
                <div>
                    <label for="m-wo-priority" class="block text-xs font-semibold text-zinc-400 mb-1.5">Prioridad</label>
                    <select
                        id="m-wo-priority"
                        v-model="createWoForm.priority"
                        class="w-full bg-zinc-800 border border-zinc-700 rounded-xl px-3 py-2.5 text-sm text-zinc-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                        <option value="p1_critical">P1 Crítica</option>
                        <option value="p2_high">P2 Alta</option>
                        <option value="p3_medium">P3 Media</option>
                        <option value="p4_low">P4 Baja</option>
                    </select>
                </div>
                <div>
                    <label for="m-wo-title" class="block text-xs font-semibold text-zinc-400 mb-1.5">Título</label>
                    <input
                        id="m-wo-title"
                        v-model="createWoForm.title"
                        type="text"
                        required
                        maxlength="255"
                        placeholder="Descripción breve de la tarea"
                        class="w-full bg-zinc-800 border border-zinc-700 rounded-xl px-3 py-2.5 text-sm text-zinc-100 placeholder-zinc-600 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                </div>
                <div>
                    <label for="m-wo-description" class="block text-xs font-semibold text-zinc-400 mb-1.5">Descripción (opcional)</label>
                    <textarea
                        id="m-wo-description"
                        v-model="createWoForm.description"
                        rows="3"
                        placeholder="Detalles adicionales..."
                        class="w-full bg-zinc-800 border border-zinc-700 rounded-xl px-3 py-2.5 text-sm text-zinc-100 placeholder-zinc-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"
                    />
                </div>
                <button
                    type="submit"
                    :disabled="createWoLoading || !createWoForm.title"
                    class="w-full bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white font-semibold py-3.5 rounded-2xl transition text-sm"
                >
                    {{ createWoLoading ? 'Creando...' : 'Crear orden de trabajo' }}
                </button>
            </form>
        </BottomSheet>

        <!-- ── Bottom Sheet: Reportar falla ─────────────────────── -->
        <BottomSheet v-model:open="showReport" title="Reportar falla" :tall="true">
            <form class="px-4 py-4 space-y-4 pb-8" @submit.prevent="submitReport">
                <div>
                    <label for="m-req-type" class="block text-xs font-semibold text-zinc-400 mb-1.5">Tipo de solicitud</label>
                    <select
                        id="m-req-type"
                        v-model="reportForm.request_type"
                        class="w-full bg-zinc-800 border border-zinc-700 rounded-xl px-3 py-2.5 text-sm text-zinc-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                        <option value="corrective">Correctiva</option>
                        <option value="emergency">Emergencia</option>
                        <option value="predictive">Predictiva</option>
                        <option value="improvement">Mejora</option>
                    </select>
                </div>
                <div>
                    <label for="m-req-priority" class="block text-xs font-semibold text-zinc-400 mb-1.5">Prioridad</label>
                    <select
                        id="m-req-priority"
                        v-model="reportForm.priority"
                        class="w-full bg-zinc-800 border border-zinc-700 rounded-xl px-3 py-2.5 text-sm text-zinc-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                        <option value="p1_critical">P1 Crítica</option>
                        <option value="p2_high">P2 Alta</option>
                        <option value="p3_medium">P3 Media</option>
                        <option value="p4_low">P4 Baja</option>
                    </select>
                </div>
                <div>
                    <label for="m-req-title" class="block text-xs font-semibold text-zinc-400 mb-1.5">Título</label>
                    <input
                        id="m-req-title"
                        v-model="reportForm.title"
                        type="text"
                        required
                        maxlength="255"
                        placeholder="Ej: Ruido inusual en rodamiento"
                        class="w-full bg-zinc-800 border border-zinc-700 rounded-xl px-3 py-2.5 text-sm text-zinc-100 placeholder-zinc-600 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                </div>
                <div>
                    <label for="m-req-description" class="block text-xs font-semibold text-zinc-400 mb-1.5">Descripción</label>
                    <textarea
                        id="m-req-description"
                        v-model="reportForm.description"
                        rows="4"
                        required
                        placeholder="Qué observaste, cuándo comenzó, si afecta la operación..."
                        class="w-full bg-zinc-800 border border-zinc-700 rounded-xl px-3 py-2.5 text-sm text-zinc-100 placeholder-zinc-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"
                    />
                </div>
                <button
                    type="submit"
                    :disabled="reportLoading || !reportForm.title || !reportForm.description"
                    class="w-full bg-red-700 hover:bg-red-600 disabled:opacity-50 text-white font-semibold py-3.5 rounded-2xl transition text-sm"
                >
                    {{ reportLoading ? 'Enviando...' : 'Reportar falla' }}
                </button>
            </form>
        </BottomSheet>

        <!-- ── Bottom Sheet: Registrar lectura ───────────────────── -->
        <BottomSheet v-model:open="showReading" title="Registrar lectura">
            <form class="px-4 py-4 space-y-4 pb-8" @submit.prevent="submitReading">
                <div>
                    <label for="m-reading-value" class="block text-xs font-semibold text-zinc-400 mb-1.5">
                        Lectura <span v-if="eq?.meter_unit" class="text-zinc-500 font-normal">({{ eq.meter_unit }})</span>
                    </label>
                    <input
                        id="m-reading-value"
                        v-model="readingForm.value"
                        type="number"
                        step="any"
                        required
                        placeholder="0"
                        class="w-full bg-zinc-800 border border-zinc-700 rounded-xl px-3 py-2.5 text-sm text-zinc-100 placeholder-zinc-600 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                    <p v-if="eq?.current_meter_reading != null" class="text-xs text-zinc-500 mt-1.5">
                        Lectura actual: {{ eq.current_meter_reading }} {{ eq.meter_unit }}
                    </p>
                </div>
                <div>
                    <label for="m-reading-notes" class="block text-xs font-semibold text-zinc-400 mb-1.5">Notas (opcional)</label>
                    <input
                        id="m-reading-notes"
                        v-model="readingForm.notes"
                        type="text"
                        maxlength="255"
                        placeholder="Observaciones..."
                        class="w-full bg-zinc-800 border border-zinc-700 rounded-xl px-3 py-2.5 text-sm text-zinc-100 placeholder-zinc-600 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                </div>
                <button
                    type="submit"
                    :disabled="readingLoading || readingForm.value === ''"
                    class="w-full bg-cyan-700 hover:bg-cyan-600 disabled:opacity-50 text-white font-semibold py-3.5 rounded-2xl transition text-sm"
                >
                    {{ readingLoading ? 'Guardando...' : 'Guardar lectura' }}
                </button>
            </form>
        </BottomSheet>

        <!-- ── Toast ─────────────────────────────────────────────── -->
        <Transition name="toast">
            <div
                v-if="toast.visible"
                class="fixed bottom-24 inset-x-4 z-50 flex items-center gap-3 px-4 py-3 rounded-2xl shadow-lg text-sm font-medium"
                :class="toast.isError ? 'bg-red-900 text-red-100 border border-red-700' : 'bg-green-900 text-green-100 border border-green-700'"
            >
                <svg v-if="toast.isError" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
                <svg v-else class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                </svg>
                {{ toast.message }}
            </div>
        </Transition>

    </AppLayout>
</template>

<script setup>
import { computed, onMounted, ref, reactive } from 'vue'
import { useRoute } from 'vue-router'
import AppLayout from '../components/AppLayout.vue'
import BottomSheet from '../components/BottomSheet.vue'
import EquipmentStatusBadge from '../components/EquipmentStatusBadge.vue'
import CriticalityBadge from '../components/CriticalityBadge.vue'
import StatusBadge from '../components/StatusBadge.vue'
import PriorityBadge from '../components/PriorityBadge.vue'
import { useEquipmentStore } from '../stores/equipment.js'
import { useApi } from '../composables/useApi.js'

const route = useRoute()
const store = useEquipmentStore()
const api = useApi()

const eq = computed(() => store.currentItem)

// UI state
const equipDataOpen = ref(false)
const showActiveWos = ref(false)
const showCreateWo = ref(false)
const showReport = ref(false)
const showReading = ref(false)

// Active work orders (fetched separately)
const activeWos = ref([])
const lastWo = ref(null)

// Form state
const createWoLoading = ref(false)
const createWoForm = reactive({
    work_order_type: 'corrective',
    priority: 'p3_medium',
    title: '',
    description: '',
})

const reportLoading = ref(false)
const reportForm = reactive({
    request_type: 'corrective',
    priority: 'p3_medium',
    title: '',
    description: '',
})

const readingLoading = ref(false)
const readingForm = reactive({ value: '', notes: '' })

// Toast
const toast = reactive({ visible: false, message: '', isError: false })

function showToast(message, isError = false) {
    toast.message = message
    toast.isError = isError
    toast.visible = true
    setTimeout(() => { toast.visible = false }, 3000)
}

// Helpers
function formatDate(iso) {
    if (!iso) return '—'
    return new Intl.DateTimeFormat('es', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(iso))
}

function formatRelative(iso) {
    if (!iso) return ''
    const diff = Date.now() - new Date(iso).getTime()
    const days = Math.floor(diff / 86400000)
    if (days === 0) return 'hoy'
    if (days === 1) return 'hace 1 día'
    if (days < 30) return `hace ${days} días`
    if (days < 365) return `hace ${Math.floor(days / 30)} meses`
    return `hace ${Math.floor(days / 365)} años`
}

const DOC_ICONS = {
    manual: '📖',
    schematic: '📐',
    procedure: '📋',
    certificate: '📜',
    datasheet: '📄',
}

function docIcon(type) {
    return DOC_ICONS[type] ?? '📄'
}

async function loadActiveWos(equipmentId) {
    try {
        const activeStatuses = ['draft', 'planned', 'in_progress', 'on_hold']
        const params = new URLSearchParams({ equipment_id: equipmentId, per_page: '10' })
        activeStatuses.forEach(s => params.append('status[]', s))
        const data = await api.get(`work-orders?${params}`)
        activeWos.value = data.data ?? []
    } catch {
        // non-critical, silently ignore
    }
}

async function loadLastWo(equipmentId) {
    try {
        const params = new URLSearchParams({
            equipment_id: equipmentId,
            per_page: '1',
            sort: '-updated_at',
        })
        const data = await api.get(`work-orders?${params}`)
        const all = data.data ?? []
        const closed = all.filter(wo => ['completed', 'cancelled'].includes(wo.status))
        lastWo.value = closed[0] ?? all[0] ?? null
    } catch {
        // non-critical
    }
}

function resetCreateWoForm() {
    createWoForm.work_order_type = 'corrective'
    createWoForm.priority = 'p3_medium'
    createWoForm.title = ''
    createWoForm.description = ''
}

function resetReportForm() {
    reportForm.request_type = 'corrective'
    reportForm.priority = 'p3_medium'
    reportForm.title = ''
    reportForm.description = ''
}

async function submitCreateWo() {
    createWoLoading.value = true
    try {
        await api.post('work-orders', {
            ...createWoForm,
            equipment_id: eq.value.id,
        })
        showCreateWo.value = false
        resetCreateWoForm()
        showToast('Orden de trabajo creada')
        await loadActiveWos(eq.value.id)
    } catch (err) {
        showToast(err.message, true)
    } finally {
        createWoLoading.value = false
    }
}

async function submitReport() {
    reportLoading.value = true
    try {
        await api.post('maintenance-requests', {
            ...reportForm,
            equipment_id: eq.value.id,
        })
        showReport.value = false
        resetReportForm()
        showToast('Falla reportada')
    } catch (err) {
        showToast(err.message, true)
    } finally {
        reportLoading.value = false
    }
}

async function submitReading() {
    readingLoading.value = true
    try {
        await api.post(`equipment/${eq.value.id}/readings`, {
            value: Number(readingForm.value),
            notes: readingForm.notes || null,
        })
        showReading.value = false
        readingForm.value = ''
        readingForm.notes = ''
        showToast('Lectura registrada')
        await store.fetchById(eq.value.id)
    } catch (err) {
        showToast(err.message, true)
    } finally {
        readingLoading.value = false
    }
}

onMounted(async () => {
    await store.fetchById(route.params.id)
    if (eq.value?.id) {
        loadActiveWos(eq.value.id)
        loadLastWo(eq.value.id)
    }
})
</script>

<style scoped>
.toast-enter-active, .toast-leave-active { transition: all 250ms ease; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateY(8px); }
</style>
