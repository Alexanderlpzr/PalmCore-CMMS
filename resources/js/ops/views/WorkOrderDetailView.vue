<template>
    <div class="min-h-full bg-gray-50">

        <!-- Loading skeleton — mirrors Mission Hero / Progress / Context so there's no layout jump on load -->
        <div v-if="loading">
            <div class="bg-white border-b border-gray-100 px-4 lg:px-8 py-5">
                <div class="max-w-5xl mx-auto">
                    <div class="skeleton h-3 w-40 rounded" />
                </div>
            </div>
            <div class="max-w-5xl mx-auto px-4 lg:px-8 py-6 space-y-4">
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 space-y-3">
                    <div class="skeleton h-3 w-32 rounded" />
                    <div class="skeleton h-7 w-2/3 rounded" />
                    <div class="flex gap-2"><div class="skeleton h-5 w-16 rounded-full" /><div class="skeleton h-5 w-20 rounded-full" /></div>
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 pt-3">
                        <div v-for="i in 4" :key="i" class="skeleton h-10 rounded-lg" />
                    </div>
                </div>
                <div class="skeleton h-24 rounded-2xl" />
                <div v-for="i in 2" :key="i" class="skeleton h-32 rounded-2xl" />
            </div>
        </div>

        <!-- Error -->
        <div v-else-if="error" class="max-w-5xl mx-auto px-4 py-20 text-center">
            <p class="text-sm text-red-600">{{ error }}</p>
            <button @click="load" class="mt-3 text-xs text-gray-500 underline">Reintentar</button>
        </div>

        <!-- Main content -->
        <template v-else-if="wo">

            <!-- ── Sticky header (slim) ─────────────────────────────────────────── -->
            <div class="bg-white border-b border-gray-100 sticky top-0 z-20 shadow-sm">
                <div class="max-w-5xl mx-auto px-4 lg:px-8 pt-3 pb-0">

                    <!-- Breadcrumbs + utility actions -->
                    <div class="flex items-center justify-between gap-3 mb-3">
                        <nav class="flex items-center gap-1 text-xs flex-wrap min-w-0">
                            <button @click="goBack" class="flex items-center gap-1 text-gray-500 hover:text-gray-700 transition-colors shrink-0">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
                                {{ backLabel }}
                            </button>
                            <svg class="w-3 h-3 text-gray-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                            <span class="text-gray-700 font-medium font-mono truncate">{{ wo.work_order_number }}</span>
                        </nav>
                        <div class="shrink-0 flex items-center gap-1.5">
                            <FavoriteStar type="workorders" :id="wo.id" />
                            <button
                                @click="downloadPdf"
                                :disabled="downloadingPdf"
                                class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border border-gray-200 text-xs font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-50 transition-colors"
                            >
                                <AppIcon name="fileText" class="w-3.5 h-3.5" />
                                {{ downloadingPdf ? '…' : 'PDF' }}
                            </button>
                        </div>
                    </div>

                    <!-- Desktop anchor nav -->
                    <div class="hidden lg:flex gap-0 overflow-x-auto border-t border-gray-100">
                        <button v-for="sec in tabList" :key="sec.id" @click="onTabClick(sec.id)"
                            class="shrink-0 px-4 py-3 text-sm font-medium transition-colors border-b-2 -mb-px"
                            :class="activeSection === sec.id
                                ? 'border-emerald-500 text-emerald-700 font-semibold'
                                : 'border-transparent text-gray-500 hover:text-gray-800 hover:border-gray-300'">
                            {{ sec.label }}
                            <span v-if="sec.count" class="ml-1 text-xs font-bold bg-gray-100 text-gray-500 rounded-full px-1.5 py-0.5">{{ sec.count }}</span>
                        </button>
                    </div>

                    <!-- Mobile tab bar -->
                    <div class="flex lg:hidden gap-0 overflow-x-auto border-t border-gray-100">
                        <button v-for="tab in tabList" :key="tab.id" @click="onMobileTab(tab.id)"
                            class="shrink-0 px-4 py-3 text-sm font-medium transition-colors border-b-2 -mb-px"
                            :class="mobileTab === tab.id ? 'border-emerald-500 text-emerald-700' : 'border-transparent text-gray-500 hover:text-gray-800'">
                            {{ tab.label }}
                            <span v-if="tab.count" class="ml-1 text-xs font-bold bg-gray-100 text-gray-500 rounded-full px-1.5 py-0.5">{{ tab.count }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- ── Mission Workspace: Hero / Progress / Context ─────────────────── -->
            <div class="max-w-5xl mx-auto px-4 lg:px-8 pt-6 space-y-4">
                <MissionHero :work-order="wo" :expected-outcome="wo.mission?.expected_outcome" />

                <QuickDecisions
                    :work-order="wo"
                    :transitioning="transitioning"
                    :transition-error="transitionError"
                    @transition="transition"
                    @open-completion="completionOpen = true"
                    @open-evidence="evidenceZoneRef?.openUpload()"
                    @open-support="evidenceZoneRef?.openSupport()"
                    @download-pdf="downloadPdf"
                />

                <MissionProgress v-if="wo.mission?.progress" :progress="wo.mission.progress" />

                <MissionContext
                    :work-order="wo"
                    :origin="wo.mission?.origin"
                    :previous-intervention="wo.mission?.previous_intervention"
                />

                <!-- KPI strip -->
                <div class="grid grid-cols-3 gap-2">
                    <div class="rounded-xl p-2.5 bg-emerald-50">
                        <p class="text-xs font-bold uppercase tracking-wider text-emerald-600 leading-none mb-1">Tiempo real</p>
                        <p class="text-lg font-bold text-gray-900 leading-none">{{ wo.actual_labor_hours != null ? Number(wo.actual_labor_hours).toFixed(1) + 'h' : '—' }}</p>
                    </div>
                    <div class="rounded-xl p-2.5 bg-red-50">
                        <p class="text-xs font-bold uppercase tracking-wider text-red-600 leading-none mb-1">Paro</p>
                        <p class="text-lg font-bold text-gray-900 leading-none">{{ wo.downtime_minutes != null ? (wo.downtime_minutes / 60).toFixed(1) + 'h' : '—' }}</p>
                    </div>
                    <div class="rounded-xl p-2.5 bg-blue-50">
                        <p class="text-xs font-bold uppercase tracking-wider text-blue-600 leading-none mb-1">Costo total</p>
                        <p class="text-lg font-bold text-gray-900 leading-none truncate">{{ wo.actual_cost_total != null ? formatCurrency(wo.actual_cost_total, wo.currency_code) : '—' }}</p>
                    </div>
                </div>
            </div>

            <!-- ── Content sections ────────────────────────────────────────────── -->
            <div class="max-w-5xl mx-auto px-4 lg:px-8 py-6 space-y-8">

                <!-- ── RESUMEN ─────────────────────────────────────────────────── -->
                <section id="resumen" class="scroll-mt-72" v-show="isDesktop || mobileTab === 'resumen'">
                    <SectionLabel label="Resumen" />

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <!-- Details -->
                        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
                            <div class="px-4 py-3 border-b border-gray-50">
                                <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500">Detalles</h3>
                            </div>
                            <div class="px-4 divide-y divide-gray-50">
                                <InfoRow label="Tipo" :value="typeLabel[wo.work_order_type] ?? wo.work_order_type" />
                                <InfoRow label="Planta" :value="wo.plant?.name" />
                                <InfoRow label="Área" :value="wo.area?.name" />
                                <InfoRow label="Equipo detenido" :value="wo.equipment_stopped === true ? 'Sí' : wo.equipment_stopped === false ? 'No' : null" />
                                <InfoRow label="Tiempo de paro" :value="wo.downtime_minutes != null ? `${wo.downtime_minutes} min` : null" />
                            </div>
                        </div>

                        <!-- Dates -->
                        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
                            <div class="px-4 py-3 border-b border-gray-50">
                                <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500">Fechas</h3>
                            </div>
                            <div class="px-4 divide-y divide-gray-50">
                                <InfoRow label="Inicio planificado" :value="formatDateTime(wo.planned_start_at)" />
                                <InfoRow label="Fin planificado" :value="formatDateTime(wo.planned_end_at)" />
                                <InfoRow label="Inicio real" :value="formatDateTime(wo.started_at ?? wo.actual_start_at)" />
                                <InfoRow label="Completado" :value="formatDateTime(wo.completed_at)" />
                                <InfoRow label="Creado" :value="formatDateTime(wo.created_at)" />
                            </div>
                        </div>

                        <!-- Horas -->
                        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
                            <div class="px-4 py-3 border-b border-gray-50">
                                <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500">Horas</h3>
                            </div>
                            <div class="px-4 divide-y divide-gray-50">
                                <InfoRow label="Horas planif." :value="wo.planned_labor_hours != null ? `${wo.planned_labor_hours} h` : null" />
                                <InfoRow label="Horas reales" :value="wo.actual_labor_hours != null ? `${wo.actual_labor_hours} h` : null" />
                            </div>
                        </div>

                        <!-- Costos -->
                        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
                            <div class="px-4 py-3 border-b border-gray-50">
                                <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500">Costos</h3>
                            </div>
                            <div class="px-4 divide-y divide-gray-50">
                                <InfoRow label="Mano de obra" :value="wo.actual_cost_labor != null ? formatCurrency(wo.actual_cost_labor, wo.currency_code) : null" />
                                <InfoRow label="Partes" :value="wo.actual_cost_parts != null ? formatCurrency(wo.actual_cost_parts, wo.currency_code) : null" />
                                <InfoRow label="Total" :value="wo.actual_cost_total != null ? formatCurrency(wo.actual_cost_total, wo.currency_code) : null" />
                            </div>
                        </div>
                    </div>

                    <!-- Descripción e Instrucciones ahora viven en Mission Context, arriba —
                         evita mostrar el mismo texto dos veces en la misma pantalla. -->

                    <!-- Resultado -->
                    <div v-if="wo.work_performed || wo.failure_cause || wo.root_cause" class="bg-white rounded-2xl border border-gray-100 shadow-sm mt-4 p-4 space-y-4">
                        <p class="text-xs font-bold text-gray-500 uppercase tracking-widest">Resultado</p>
                        <div v-if="wo.work_performed">
                            <p class="text-xs text-gray-500 mb-1">Trabajo realizado</p>
                            <p class="text-sm text-gray-700 whitespace-pre-line">{{ wo.work_performed }}</p>
                        </div>
                        <div v-if="wo.failure_cause">
                            <p class="text-xs text-gray-500 mb-1">Causa de falla</p>
                            <p class="text-sm text-gray-700 whitespace-pre-line">{{ wo.failure_cause }}</p>
                        </div>
                        <div v-if="wo.root_cause">
                            <p class="text-xs text-gray-500 mb-1">Causa raíz</p>
                            <p class="text-sm text-gray-700 whitespace-pre-line">{{ wo.root_cause }}</p>
                        </div>
                    </div>

                    <!-- Technicians -->
                    <div class="mt-4">
                        <p class="text-xs font-bold text-gray-500 uppercase tracking-widest mb-3">Técnicos asignados</p>
                        <div v-if="!wo.technicians?.length" class="bg-white rounded-2xl border border-gray-100 shadow-sm py-8 text-center text-xs text-gray-500">
                            Sin técnicos asignados
                        </div>
                        <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                            <div v-for="t in wo.technicians" :key="t.id" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-indigo-50 flex items-center justify-center shrink-0">
                                    <span class="text-sm font-bold text-indigo-600">{{ initials(t.user?.name) }}</span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-gray-900">{{ t.user?.name ?? 'Sin nombre' }}</p>
                                    <p class="text-xs text-gray-500">{{ roleLabel[t.role] ?? t.role }}</p>
                                </div>
                                <div v-if="t.planned_hours != null" class="text-right shrink-0">
                                    <p class="text-xs font-semibold text-gray-700">{{ t.planned_hours }} h</p>
                                    <p class="text-xs text-gray-500">planif.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ── CHECKLIST (lo que el técnico realmente midió) ───────────── -->
                <section id="checklist" class="scroll-mt-72" v-show="isDesktop || mobileTab === 'checklist'">
                    <SectionLabel label="Trabajo ejecutado" />

                    <div v-if="tabs.checklist.loading" class="space-y-3">
                        <div v-for="i in 2" :key="i" class="skeleton h-28 rounded-2xl" />
                    </div>

                    <div v-else-if="checklistTasks.length" class="space-y-3">
                        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Avance</p>
                                <p class="text-xs font-bold text-gray-900">
                                    {{ checklistProgress.resolved }} / {{ checklistProgress.total }}
                                </p>
                            </div>
                            <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                <div
                                    class="h-full rounded-full bg-emerald-500"
                                    :style="{ width: checklistPercent + '%' }"
                                />
                            </div>
                            <p v-if="checklistMissing > 0" class="text-xs text-amber-700 mt-2">
                                Faltan {{ checklistMissing }} medición(es) obligatoria(s): la OT no puede completarse.
                            </p>
                        </div>

                        <div
                            v-for="task in checklistTasks"
                            :key="task.id"
                            class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden"
                        >
                            <div class="px-5 py-4 border-b border-gray-100 flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-900">{{ task.title }}</p>
                                    <p v-if="task.skipped_reason" class="text-xs text-gray-500 italic mt-0.5">
                                        Omitida: {{ task.skipped_reason }}
                                    </p>
                                </div>
                                <span
                                    class="shrink-0 text-[11px] font-bold uppercase tracking-wider px-2 py-0.5 rounded"
                                    :class="taskBadge[task.status]"
                                >{{ task.status_label }}</span>
                            </div>

                            <div v-if="task.checklist.length" class="divide-y divide-gray-50">
                                <div
                                    v-for="item in task.checklist"
                                    :key="item.id"
                                    class="px-5 py-3 flex items-center justify-between gap-4"
                                >
                                    <div class="min-w-0">
                                        <p class="text-sm text-gray-800">{{ item.label }}</p>
                                        <p v-if="item.expected_range_label" class="text-xs text-gray-500">
                                            Esperado: {{ item.expected_range_label }}
                                        </p>
                                    </div>
                                    <div class="shrink-0 text-right">
                                        <p
                                            class="text-sm font-semibold"
                                            :class="item.is_out_of_range ? 'text-red-600' : 'text-gray-900'"
                                        >
                                            {{ item.display_value ?? 'Sin registrar' }}
                                        </p>
                                        <p v-if="item.is_out_of_range" class="text-[11px] font-semibold text-red-600">
                                            Fuera de rango
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-10 text-center text-xs text-gray-500">
                        Esta orden no tiene tareas de checklist.
                    </div>
                </section>

                <!-- ── HISTORIAL (status timeline) ─────────────────────────────── -->
                <section id="historial" class="scroll-mt-72" v-show="isDesktop || mobileTab === 'historial'">
                    <SectionLabel label="Historial de estados" />

                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
                        <div v-if="milestones.length" class="px-5 py-5">
                            <div class="relative">
                                <div class="absolute left-1.5 top-3 bottom-3 w-px bg-gray-100" />
                                <div class="space-y-5">
                                    <div v-for="m in milestones" :key="m.key" class="flex gap-4 relative">
                                        <div class="w-3 h-3 rounded-full shrink-0 mt-1 ring-2 ring-white relative z-10" :class="m.dot" />
                                        <div class="flex-1 min-w-0 pb-1">
                                            <span class="text-xs font-bold uppercase tracking-wider px-1.5 py-0.5 rounded" :class="m.badge">
                                                {{ m.label }}
                                            </span>
                                            <p class="text-xs text-gray-500 mt-1">{{ formatDateTime(m.at) }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="px-5 py-10 text-center text-xs text-gray-500">
                            Sin hitos de estado registrados
                        </div>
                    </div>
                </section>

                <!-- ── COMPONENTES ─────────────────────────────────────────────── -->
                <section id="componentes" class="scroll-mt-72" v-show="isDesktop || mobileTab === 'componentes'">
                    <SectionLabel label="Componentes intervenidos" />

                    <div v-if="tabs.componentes.loading" class="space-y-3">
                        <div v-for="i in 3" :key="i" class="skeleton h-20 rounded-2xl" />
                    </div>
                    <div v-else-if="components.length" class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                        <div v-for="c in components" :key="c.id" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p v-if="c.code" class="text-xs font-mono font-bold text-gray-500 uppercase">{{ c.code }}</p>
                                    <p class="text-sm font-bold text-gray-900 leading-tight mt-0.5">{{ c.name ?? c.description ?? 'Componente' }}</p>
                                    <p v-if="c.description && c.name" class="text-xs text-gray-500 mt-0.5 line-clamp-2">{{ c.description }}</p>
                                </div>
                                <span v-if="c.status" class="text-xs font-semibold px-1.5 py-0.5 rounded-full bg-gray-100 text-gray-600 shrink-0">{{ c.status }}</span>
                            </div>
                            <div v-if="c.quantity != null" class="mt-2 text-xs text-gray-500">
                                Cantidad: <span class="font-semibold text-gray-700">{{ c.quantity }}</span>
                            </div>
                        </div>
                    </div>
                    <div v-else class="bg-white rounded-2xl border border-gray-100 shadow-sm py-10 text-center text-xs text-gray-500">
                        {{ wo.equipment ? 'Sin componentes registrados para el equipo' : 'Esta orden no tiene equipo asociado' }}
                    </div>
                </section>

                <!-- ── TIEMPO & REPUESTOS ──────────────────────────────────────── -->
                <section id="tiempo" class="scroll-mt-72" v-show="isDesktop || mobileTab === 'tiempo'">
                    <SectionLabel label="Tiempo real" />

                    <div v-if="tabs.tiempo.loading" class="space-y-3">
                        <div v-for="i in 3" :key="i" class="skeleton h-16 rounded-2xl" />
                    </div>
                    <div v-else-if="timeEntries.length" class="bg-white rounded-2xl border border-gray-100 shadow-sm divide-y divide-gray-50">
                        <div v-for="e in timeEntries" :key="e.id" class="px-4 py-3 flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-emerald-50 flex items-center justify-center shrink-0">
                                <span class="text-xs font-bold text-emerald-600">{{ initials(e.user?.name) }}</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-gray-900">{{ e.user?.name ?? 'Usuario' }}</p>
                                <p class="text-xs text-gray-500">{{ formatDateTime(e.started_at) }}<span v-if="e.ended_at"> → {{ formatDateTime(e.ended_at) }}</span></p>
                                <p v-if="e.description" class="text-xs text-gray-500 mt-0.5 line-clamp-1">{{ e.description }}</p>
                            </div>
                            <div v-if="e.hours != null" class="text-right shrink-0">
                                <p class="text-sm font-bold text-gray-900">{{ Number(e.hours).toFixed(1) }} h</p>
                            </div>
                        </div>
                        <div class="px-4 py-3 flex justify-between items-center bg-gray-50">
                            <span class="text-sm font-semibold text-gray-700">Total horas</span>
                            <span class="text-sm font-bold text-gray-900">{{ totalTimeHours.toFixed(1) }} h</span>
                        </div>
                    </div>
                    <div v-else class="bg-white rounded-2xl border border-gray-100 shadow-sm py-10 text-center text-xs text-gray-500">
                        Sin registros de tiempo
                    </div>

                    <SectionLabel label="Repuestos" class="mt-6" />
                    <div v-if="!wo.parts?.length" class="bg-white rounded-2xl border border-gray-100 shadow-sm py-10 text-center text-xs text-gray-500">
                        Sin partes registradas
                    </div>
                    <div v-else class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                        <div v-for="p in wo.parts" :key="p.id" class="p-4 flex items-start gap-3 border-b border-gray-50 last:border-0">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <span v-if="p.part_code" class="font-mono text-xs bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded">{{ p.part_code }}</span>
                                    <span class="text-xs font-semibold px-1.5 py-0.5 rounded-full" :class="partStatusBadge[p.status]">{{ partStatusLabel[p.status] ?? p.status }}</span>
                                </div>
                                <p class="text-sm font-medium text-gray-900">{{ p.description }}</p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-sm font-semibold text-gray-900">{{ p.quantity }} {{ p.unit }}</p>
                                <p v-if="p.total_cost != null" class="text-xs text-gray-500">${{ p.total_cost.toFixed(2) }}</p>
                            </div>
                        </div>
                        <div v-if="wo.actual_cost_parts != null" class="p-4 flex justify-between items-center bg-gray-50">
                            <span class="text-sm font-semibold text-gray-700">Total partes</span>
                            <span class="text-sm font-bold text-gray-900">${{ wo.actual_cost_parts.toFixed(2) }}</span>
                        </div>
                    </div>
                </section>

                <!-- ── EVIDENCIA ───────────────────────────────────────────────── -->
                <!-- Un único espacio: fotos, checklist, notas y firmas, no repartidos
                     en pestañas separadas — así lo pide WXA-2B. -->
                <section id="evidencia" class="scroll-mt-72" v-show="isDesktop || mobileTab === 'evidencia'">
                    <SectionLabel label="Evidencia y cierre" />
                    <EvidenceZone ref="evidenceZoneRef" :work-order="wo" @refresh="refreshWorkOrder" />
                </section>

            </div>
        </template>

        <CompletionExperience
            :open="completionOpen"
            :work-order="wo ?? {}"
            :submitting="transitioning"
            :error="transitionError"
            @close="completionOpen = false"
            @completed="submitCompletion"
        />

    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, defineComponent, h } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useApi } from '../composables/useApi.js'
import AppIcon from '../components/AppIcon.vue'
import FavoriteStar from '../components/FavoriteStar.vue'
import MissionHero from '../components/mission/MissionHero.vue'
import MissionProgress from '../components/mission/MissionProgress.vue'
import MissionContext from '../components/mission/MissionContext.vue'
import QuickDecisions from '../components/mission/QuickDecisions.vue'
import EvidenceZone from '../components/mission/EvidenceZone.vue'
import CompletionExperience from '../components/mission/CompletionExperience.vue'

const route = useRoute()
const router = useRouter()
const api = useApi()

// ── Inline sub-components ──────────────────────────────────────────────────────

const InfoRow = defineComponent({
    props: { label: String, value: [String, Number] },
    setup(props) {
        return () => props.value != null && props.value !== ''
            ? h('div', { class: 'flex items-start justify-between py-2.5 gap-4' }, [
                h('span', { class: 'text-xs text-gray-500 shrink-0' }, props.label),
                h('span', { class: 'text-xs font-semibold text-gray-800 text-right break-words max-w-[60%]' }, String(props.value)),
            ])
            : null
    },
})

const SectionLabel = defineComponent({
    props: { label: String },
    setup(props) {
        return () => h('h2', { class: 'text-xs font-bold uppercase tracking-widest text-gray-500 mb-3' }, props.label)
    },
})

// ── Back navigation ────────────────────────────────────────────────────────────

const backLabel = computed(() => {
    const from = route.query.from
    if (from === 'ops.equipos.show') { return 'Equipo' }
    if (from === 'ops.solicitudes.show') { return 'Solicitud' }
    if (from === 'ops.preventivos') { return 'Preventivos' }
    return 'Órdenes de trabajo'
})

function goBack() {
    const from = route.query.from
    const fromId = route.query.fromId
    if (from && fromId && ['ops.equipos.show', 'ops.solicitudes.show'].includes(from)) {
        router.push({ name: from, params: { id: fromId } })
    } else if (from && ['ops.preventivos'].includes(from)) {
        router.push({ name: from })
    } else {
        router.push({ name: 'ops.ordenes' })
    }
}

// ── Core state ─────────────────────────────────────────────────────────────────

const wo = ref(null)
const loading = ref(true)
const error = ref(null)
const downloadingPdf = ref(false)
const transitioning = ref(false)
const transitionError = ref(null)
const completionOpen = ref(false)
const evidenceZoneRef = ref(null)

// ── Lazy tab data ──────────────────────────────────────────────────────────────

const timeEntries = ref([])
const components = ref([])

const tabs = reactive({
    checklist: { loaded: false, loading: false },
    componentes: { loaded: false, loading: false },
    tiempo: { loaded: false, loading: false },
})

const checklistTasks = ref([])
const checklistProgress = ref({ resolved: 0, total: 0 })
const checklistMissing = ref(0)

const checklistPercent = computed(() => checklistProgress.value.total
    ? Math.round((checklistProgress.value.resolved / checklistProgress.value.total) * 100)
    : 0)

const taskBadge = {
    pending: 'bg-gray-100 text-gray-600',
    in_progress: 'bg-blue-50 text-blue-700',
    done: 'bg-emerald-50 text-emerald-700',
    skipped: 'bg-amber-50 text-amber-700',
}

// ── Label maps ─────────────────────────────────────────────────────────────────
// status()/priority() moved into MissionHero.vue, which now owns that badge row.

const typeLabel = {
    corrective: 'Correctivo', preventive: 'Preventivo', predictive: 'Predictivo',
    inspection: 'Inspección', emergency: 'Emergencia',
}
const roleLabel = {
    lead_technician: 'Técnico líder', technician: 'Técnico', helper: 'Ayudante', inspector: 'Inspector',
}
const partStatusBadge = {
    requested: 'bg-blue-100 text-blue-700',
    reserved: 'bg-amber-100 text-amber-700',
    issued: 'bg-indigo-100 text-indigo-700',
    used: 'bg-emerald-100 text-emerald-700',
    returned: 'bg-gray-100 text-gray-600',
}
const partStatusLabel = {
    requested: 'Solicitada', reserved: 'Reservada', issued: 'Emitida', used: 'Usada', returned: 'Devuelta',
}

// ── Status timeline (client-side from timestamps) ──────────────────────────────

const milestones = computed(() => {
    if (!wo.value) { return [] }
    const defs = [
        { key: 'created', at: wo.value.created_at, label: 'Creada', dot: 'bg-gray-400', badge: 'bg-gray-100 text-gray-600' },
        { key: 'planned', at: wo.value.planned_start_at, label: 'Planificada', dot: 'bg-blue-400', badge: 'bg-blue-50 text-blue-700' },
        { key: 'started', at: wo.value.started_at ?? wo.value.actual_start_at, label: 'Iniciada', dot: 'bg-amber-400', badge: 'bg-amber-50 text-amber-700' },
        { key: 'completed', at: wo.value.completed_at, label: 'Completada', dot: 'bg-emerald-500', badge: 'bg-emerald-50 text-emerald-700' },
        { key: 'verified', at: wo.value.verified_at, label: 'Verificada', dot: 'bg-indigo-500', badge: 'bg-indigo-50 text-indigo-700' },
        { key: 'closed', at: wo.value.closed_at, label: 'Cerrada', dot: 'bg-green-600', badge: 'bg-green-50 text-green-700' },
    ]
    return defs.filter(m => m.at != null)
})

// ── Tabs ───────────────────────────────────────────────────────────────────────

const tabList = computed(() => [
    { id: 'resumen', label: 'Resumen' },
    { id: 'checklist', label: 'Checklist', count: tabs.checklist.loaded ? (checklistTasks.value.length || null) : null },
    { id: 'historial', label: 'Historial', count: milestones.value.length || null },
    { id: 'componentes', label: 'Componentes', count: tabs.componentes.loaded ? (components.value.length || null) : null },
    { id: 'tiempo', label: 'Tiempo & Repuestos', count: wo.value?.parts?.length || null },
    {
        id: 'evidencia',
        label: 'Evidencia',
        count: (wo.value?.attachments?.length || 0) + (wo.value?.signatures?.length || 0) + (wo.value?.comments?.length || 0) || null,
    },
])

const activeSection = ref('resumen')

const isDesktop = ref(typeof window !== 'undefined' && window.innerWidth >= 1024)
const mobileTab = ref('resumen')

function handleResize() {
    isDesktop.value = window.innerWidth >= 1024
}

function onTabClick(id) {
    document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
    activeSection.value = id
    ensureLoaded(id)
}

function onMobileTab(id) {
    mobileTab.value = id
    ensureLoaded(id)
}

// ── Lazy loaders ───────────────────────────────────────────────────────────────

async function ensureLoaded(tabId) {
    const t = tabs[tabId]
    if (!t || t.loaded || t.loading) { return }
    t.loading = true
    try {
        if (tabId === 'checklist') {
            const res = await api.get(`work-orders/${wo.value.id}/tasks`)
            checklistTasks.value = res?.data ?? []
            checklistProgress.value = res?.meta?.progress ?? { resolved: 0, total: 0 }
            checklistMissing.value = res?.meta?.missing_required ?? 0
        } else if (tabId === 'tiempo') {
            const res = await api.get(`work-orders/${wo.value.id}/time-entries`)
            timeEntries.value = res?.data ?? []
        } else if (tabId === 'componentes') {
            if (wo.value.equipment?.id) {
                const res = await api.get(`equipment/${wo.value.equipment.id}/components`)
                components.value = res?.data ?? []
            }
        }
        t.loaded = true
    } catch { /* silent */ } finally {
        t.loading = false
    }
}

// ── Computed (lazy data) ───────────────────────────────────────────────────────

const totalTimeHours = computed(() =>
    timeEntries.value.reduce((sum, e) => sum + (Number(e.hours) || 0), 0)
)

// ── Helpers ────────────────────────────────────────────────────────────────────

function initials(name) {
    if (!name) { return '?' }
    return name.split(' ').slice(0, 2).map(n => n[0]).join('').toUpperCase()
}

function formatDateTime(iso) {
    if (!iso) { return null }
    return new Date(iso).toLocaleDateString('es', {
        day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
    })
}

function formatCurrency(amount, code) {
    return new Intl.NumberFormat('es', { style: 'currency', currency: code ?? 'MXN' }).format(amount)
}

// ── Intersection Observer (desktop) — triggers lazy load on reveal ────────────

let observer = null

function setupObserver() {
    observer?.disconnect()
    observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                activeSection.value = entry.target.id
                ensureLoaded(entry.target.id)
            }
        })
    }, { threshold: 0, rootMargin: '-40% 0px -55% 0px' })

    tabList.value.forEach(({ id }) => {
        const el = document.getElementById(id)
        if (el) { observer.observe(el) }
    })
}

// ── API ────────────────────────────────────────────────────────────────────────

async function load(silent = false) {
    if (!silent) { loading.value = true }
    error.value = null
    try {
        const res = await api.get(`work-orders/${route.params.id}`)
        wo.value = res?.data ?? res
    } catch (err) {
        error.value = err?.message ?? 'Error al cargar la orden de trabajo'
    } finally {
        loading.value = false
    }
}

// Silent reload after uploading evidence/signing/etc. — the technician stays
// on the same screen and just sees the new item appear, no full-page skeleton.
async function refreshWorkOrder() {
    await load(true)
}

async function downloadPdf() {
    if (downloadingPdf.value || !wo.value) { return }
    downloadingPdf.value = true
    try {
        await api.download(`reports/work-orders/${wo.value.id}`, `${wo.value.work_order_number}.pdf`)
    } catch { /* ignored */ } finally {
        downloadingPdf.value = false
    }
}

async function transition(newStatus, extra = {}) {
    transitioning.value = true
    transitionError.value = null
    try {
        const res = await api.patch(`work-orders/${wo.value.id}/status`, { status: newStatus, ...extra })
        wo.value = res?.data ?? res
    } catch (err) {
        transitionError.value = err?.message ?? 'Error al cambiar el estado'
        throw err
    } finally {
        transitioning.value = false
    }
}

async function submitCompletion(payload) {
    try {
        await transition('completed', payload)
        completionOpen.value = false
    } catch {
        // transitionError is already set by transition(); keep the panel open
        // so the technician sees the message and can retry without redoing the form.
    }
}

// ── Lifecycle ──────────────────────────────────────────────────────────────────

onMounted(async () => {
    window.addEventListener('resize', handleResize)
    await load()
    if (wo.value) {
        setupObserver()
    }
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
    observer?.disconnect()
})
</script>
