<template>
    <div class="min-h-full bg-gray-50">

        <!-- Loading skeleton -->
        <div v-if="loading">
            <div class="bg-white border-b border-gray-100 px-4 lg:px-8 py-5">
                <div class="max-w-5xl mx-auto">
                    <div class="skeleton h-3 w-40 rounded mb-4" />
                    <div class="flex gap-4 items-start">
                        <div class="flex-1 space-y-2">
                            <div class="skeleton h-3 w-24 rounded" />
                            <div class="skeleton h-7 w-2/3 rounded" />
                            <div class="flex gap-2 mt-1">
                                <div class="skeleton h-5 w-16 rounded-full" />
                                <div class="skeleton h-5 w-24 rounded-full" />
                            </div>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 lg:grid-cols-5 gap-2 mt-5">
                        <div v-for="i in 5" :key="i" class="skeleton h-16 rounded-xl" />
                    </div>
                </div>
            </div>
            <div class="max-w-5xl mx-auto px-4 lg:px-8 py-6 space-y-4">
                <div v-for="i in 4" :key="i" class="skeleton h-32 rounded-2xl" />
            </div>
        </div>

        <!-- Error -->
        <div v-else-if="error" class="max-w-5xl mx-auto px-4 py-20 text-center">
            <p class="text-sm text-red-600">{{ error }}</p>
            <button @click="load" class="mt-3 text-xs text-gray-500 underline">Reintentar</button>
        </div>

        <!-- Main content -->
        <template v-else-if="wo">

            <!-- ── Sticky header ──────────────────────────────────────────────── -->
            <div class="bg-white border-b border-gray-100 sticky top-0 z-20 shadow-sm">
                <div class="max-w-5xl mx-auto px-4 lg:px-8 pt-3 pb-0">

                    <!-- Breadcrumbs -->
                    <nav class="flex items-center gap-1 text-xs mb-3 flex-wrap">
                        <button @click="goBack" class="flex items-center gap-1 text-gray-500 hover:text-gray-700 transition-colors shrink-0">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
                            {{ backLabel }}
                        </button>
                        <svg class="w-3 h-3 text-gray-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                        <span class="text-gray-700 font-medium font-mono truncate">{{ wo.work_order_number }}</span>
                    </nav>

                    <!-- Identity row -->
                    <div class="flex items-start gap-3 mb-4">
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-mono font-bold text-gray-500 uppercase tracking-widest leading-none">{{ wo.work_order_number }}</p>
                            <h1 class="text-lg lg:text-2xl font-bold text-gray-900 mt-0.5 leading-tight">{{ wo.title }}</h1>
                            <div class="flex items-center gap-1.5 mt-1.5 flex-wrap">
                                <Badge :tone="status(wo.status).tone" :label="status(wo.status).label" />
                                <Badge :tone="priority(wo.priority).tone" :label="priority(wo.priority).label" />
                                <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">
                                    {{ typeLabel[wo.work_order_type] ?? wo.work_order_type }}
                                </span>
                            </div>
                        </div>

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

                    <!-- KPI strip -->
                    <div class="grid grid-cols-2 lg:grid-cols-5 gap-2 mb-4">
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
                        <div class="rounded-xl p-2.5 bg-sky-50">
                            <p class="text-xs font-bold uppercase tracking-wider text-sky-600 leading-none mb-1">Evidencias</p>
                            <p class="text-lg font-bold text-gray-900 leading-none">{{ tabs.evidencias.loaded ? media.length : '—' }}</p>
                        </div>
                        <div class="rounded-xl p-2.5 bg-slate-100 col-span-2 lg:col-span-1">
                            <p class="text-xs font-bold uppercase tracking-wider text-slate-500 leading-none mb-1">Firmas</p>
                            <p class="text-lg font-bold text-gray-900 leading-none">{{ tabs.firmas.loaded ? signatures.length : '—' }}</p>
                        </div>
                    </div>

                    <!-- Status transition actions -->
                    <div v-if="primaryTransition || secondaryTransitions.length" class="flex gap-2 flex-wrap mb-4">
                        <button
                            v-if="primaryTransition"
                            @click="transition(primaryTransition.status)"
                            :disabled="transitioning"
                            class="flex-1 lg:flex-none flex items-center justify-center gap-1.5 px-5 py-2.5 rounded-xl text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60 transition-colors"
                        >
                            {{ transitioning ? '…' : primaryTransition.label }}
                        </button>
                        <button
                            v-for="t in secondaryTransitions"
                            :key="t.status"
                            @click="transition(t.status)"
                            :disabled="transitioning"
                            class="px-4 py-2.5 rounded-xl text-sm font-semibold text-gray-700 bg-white border border-gray-200 hover:border-gray-300 disabled:opacity-60 transition-colors"
                        >
                            {{ t.label }}
                        </button>
                        <p v-if="transitionError" class="w-full text-xs text-red-600">{{ transitionError }}</p>
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

            <!-- ── Content sections ────────────────────────────────────────────── -->
            <div class="max-w-5xl mx-auto px-4 lg:px-8 py-6 space-y-8">

                <!-- ── RESUMEN ─────────────────────────────────────────────────── -->
                <section id="resumen" class="scroll-mt-72" v-show="isDesktop || mobileTab === 'resumen'">
                    <SectionLabel label="Resumen" />

                    <!-- Equipment context card -->
                    <div v-if="wo.equipment" class="bg-indigo-50 border border-indigo-100 rounded-2xl p-4 flex items-center justify-between gap-4 mb-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-indigo-400 mb-0.5">Equipo asociado</p>
                            <p class="text-sm font-bold text-indigo-900">{{ wo.equipment.name }}</p>
                            <div class="flex items-center gap-2 mt-0.5 flex-wrap">
                                <span class="text-xs font-mono text-indigo-400">{{ wo.equipment.code }}</span>
                                <span v-if="wo.equipment.area?.name" class="text-xs text-indigo-500">{{ wo.equipment.area.name }}</span>
                                <span v-if="wo.equipment.status" class="text-xs font-semibold px-1.5 py-0.5 rounded-full bg-indigo-100 text-indigo-700">
                                    {{ wo.equipment.status }}
                                </span>
                            </div>
                        </div>
                        <RouterLink :to="{ name: 'ops.equipos.show', params: { id: wo.equipment.id } }"
                            class="shrink-0 flex items-center gap-1 text-xs font-semibold text-indigo-600 hover:text-indigo-800 transition-colors">
                            Ver equipo
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                        </RouterLink>
                    </div>

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

                    <!-- Description -->
                    <div v-if="wo.description" class="bg-white rounded-2xl border border-gray-100 shadow-sm mt-4 p-4">
                        <p class="text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Descripción</p>
                        <p class="text-sm text-gray-700 whitespace-pre-line leading-relaxed">{{ wo.description }}</p>
                    </div>

                    <!-- Instructions -->
                    <div v-if="wo.instructions" class="bg-white rounded-2xl border border-gray-100 shadow-sm mt-4 p-4">
                        <p class="text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Instrucciones</p>
                        <p class="text-sm text-gray-700 whitespace-pre-line leading-relaxed">{{ wo.instructions }}</p>
                    </div>

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

                <!-- ── EVIDENCIAS ──────────────────────────────────────────────── -->
                <section id="evidencias" class="scroll-mt-72" v-show="isDesktop || mobileTab === 'evidencias'">
                    <SectionLabel label="Evidencias" />

                    <div v-if="tabs.evidencias.loading" class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                        <div v-for="i in 4" :key="i" class="skeleton aspect-square rounded-2xl" />
                    </div>
                    <template v-else-if="media.length">
                        <div v-for="group in mediaGroups" :key="group.key" class="mb-5 last:mb-0">
                            <p class="text-xs font-semibold text-gray-500 mb-2">{{ group.label }} <span class="text-gray-400">({{ group.items.length }})</span></p>
                            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                                <template v-for="m in group.items" :key="m.id">
                                    <!-- Image thumbnail -->
                                    <div v-if="isImage(m)" @click="lightboxPhoto = { url: m.url, caption: m.caption }"
                                        class="aspect-square rounded-2xl overflow-hidden bg-slate-100 cursor-pointer hover:opacity-90 transition-opacity relative border border-gray-100">
                                        <img :src="m.url" :alt="m.caption ?? m.file_name" class="w-full h-full object-cover" />
                                        <p v-if="m.caption" class="absolute bottom-0 left-0 right-0 bg-linear-to-t from-black/50 px-2 pb-2 pt-4 text-xs text-white leading-snug line-clamp-2">
                                            {{ m.caption }}
                                        </p>
                                    </div>
                                    <!-- File card -->
                                    <a v-else :href="m.url" target="_blank" rel="noopener"
                                        class="aspect-square rounded-2xl bg-white border border-gray-100 shadow-sm hover:border-gray-200 hover:shadow-md transition-all p-3 flex flex-col items-center justify-center text-center gap-2">
                                        <div class="w-10 h-10 rounded-xl bg-gray-100 flex items-center justify-center shrink-0">
                                            <svg class="w-5 h-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                                            </svg>
                                        </div>
                                        <p class="text-xs font-medium text-gray-700 truncate w-full">{{ m.file_name }}</p>
                                        <p v-if="m.file_size" class="text-xs text-gray-400">{{ formatFileSize(m.file_size) }}</p>
                                    </a>
                                </template>
                            </div>
                        </div>
                    </template>
                    <div v-else class="bg-white rounded-2xl border border-gray-100 shadow-sm py-10 text-center text-xs text-gray-500">
                        Sin evidencias adjuntas
                    </div>
                </section>

                <!-- ── FIRMAS ──────────────────────────────────────────────────── -->
                <section id="firmas" class="scroll-mt-72" v-show="isDesktop || mobileTab === 'firmas'">
                    <SectionLabel label="Firmas" />

                    <div v-if="tabs.firmas.loading" class="space-y-3">
                        <div v-for="i in 2" :key="i" class="skeleton h-20 rounded-2xl" />
                    </div>
                    <div v-else-if="signatures.length" class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                        <div v-for="s in signatures" :key="s.id" class="bg-white rounded-2xl border border-emerald-100 shadow-sm p-4 flex items-start gap-3">
                            <div class="w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <span class="text-xs font-bold uppercase tracking-wider px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700">
                                    {{ signatureTypeLabel[s.signature_type] ?? s.signature_type }}
                                </span>
                                <p class="text-sm font-semibold text-gray-900 mt-1.5">{{ s.user?.name ?? 'Usuario' }}</p>
                                <p class="text-xs text-gray-500">{{ formatDateTime(s.signed_at ?? s.created_at) }}</p>
                                <p v-if="s.notes" class="text-xs text-gray-600 mt-1 whitespace-pre-line">{{ s.notes }}</p>
                            </div>
                        </div>
                    </div>
                    <div v-else class="bg-white rounded-2xl border border-gray-100 shadow-sm py-10 text-center text-xs text-gray-500">
                        Sin firmas registradas
                    </div>
                </section>

                <!-- ── COMENTARIOS ─────────────────────────────────────────────── -->
                <section id="comentarios" class="scroll-mt-72 space-y-3" v-show="isDesktop || mobileTab === 'comentarios'">
                    <SectionLabel label="Comentarios" />

                    <div v-if="wo.comments?.length" class="space-y-2">
                        <div v-for="c in wo.comments" :key="c.id" class="bg-white rounded-2xl border p-4"
                            :class="c.is_internal ? 'border-amber-200 bg-amber-50/40' : 'border-gray-100 shadow-sm'">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-sm font-semibold text-gray-900">{{ c.user?.name ?? 'Usuario' }}</p>
                                <div class="flex items-center gap-1.5">
                                    <span v-if="c.is_internal" class="text-xs border border-amber-300 text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded-full font-semibold">Interno</span>
                                    <span class="text-xs text-gray-500">{{ relativeTime(c.created_at) }}</span>
                                </div>
                            </div>
                            <p class="text-sm text-gray-700 whitespace-pre-line">{{ c.body }}</p>
                        </div>
                    </div>
                    <div v-else class="bg-white rounded-2xl border border-gray-100 shadow-sm py-8 text-center text-xs text-gray-500">
                        Sin comentarios aún
                    </div>

                    <!-- Compose -->
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
                        <p class="text-xs font-bold text-gray-500 uppercase tracking-widest mb-3">Añadir comentario</p>
                        <textarea
                            v-model="newComment"
                            rows="3"
                            placeholder="Escribe un comentario..."
                            class="w-full text-sm border border-gray-200 rounded-xl px-3 py-2.5 resize-none focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent placeholder-gray-300"
                        />
                        <div class="flex items-center justify-between mt-2.5">
                            <label class="flex items-center gap-2 text-xs text-gray-500 cursor-pointer select-none">
                                <input type="checkbox" v-model="commentInternal" class="rounded border-gray-300" />
                                Nota interna
                            </label>
                            <button
                                @click="submitComment"
                                :disabled="!newComment.trim() || submittingComment"
                                class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl disabled:opacity-50 transition-colors"
                            >
                                {{ submittingComment ? '…' : 'Enviar' }}
                            </button>
                        </div>
                        <p v-if="commentError" class="mt-2 text-xs text-red-600">{{ commentError }}</p>
                    </div>
                </section>

            </div>
        </template>

        <!-- Photo lightbox -->
        <Teleport to="body">
            <div v-if="lightboxPhoto" class="fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-4" @click.self="lightboxPhoto = null">
                <div class="relative max-w-3xl w-full">
                    <img :src="lightboxPhoto.url" :alt="lightboxPhoto.caption ?? ''" class="w-full rounded-2xl object-contain max-h-[80vh]" />
                    <p v-if="lightboxPhoto.caption" class="text-white text-sm mt-3 text-center">{{ lightboxPhoto.caption }}</p>
                    <button @click="lightboxPhoto = null" class="absolute -top-3 -right-3 w-8 h-8 rounded-full bg-white text-gray-700 hover:text-gray-900 flex items-center justify-center shadow-lg">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
            </div>
        </Teleport>

    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, defineComponent, h } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { useApi } from '../composables/useApi.js'
import { useAuthStore } from '../stores/auth.js'
import { describe, WORK_ORDER_STATUS, PRIORITY } from '../../shared/design.js'
import Badge from '../components/Badge.vue'
import AppIcon from '../components/AppIcon.vue'
import FavoriteStar from '../components/FavoriteStar.vue'

const route = useRoute()
const router = useRouter()
const api = useApi()
const auth = useAuthStore()

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
const newComment = ref('')
const commentInternal = ref(false)
const submittingComment = ref(false)
const commentError = ref(null)
const lightboxPhoto = ref(null)

// ── Lazy tab data ──────────────────────────────────────────────────────────────

const media = ref([])
const signatures = ref([])
const timeEntries = ref([])
const components = ref([])

const tabs = reactive({
    componentes: { loaded: false, loading: false },
    tiempo: { loaded: false, loading: false },
    evidencias: { loaded: false, loading: false },
    firmas: { loaded: false, loading: false },
})

// ── Label maps ─────────────────────────────────────────────────────────────────

const status = (s) => describe(WORK_ORDER_STATUS, s)
const priority = (p) => describe(PRIORITY, p)

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
const signatureTypeLabel = {
    technician_completion: 'Técnico (finalización)',
    supervisor_verification: 'Supervisor (verificación)',
}
const mediaTypeLabel = {
    before_photo: 'Antes', after_photo: 'Después',
}

// ── Transition map ─────────────────────────────────────────────────────────────

const transitionMap = {
    draft: [{ status: 'planned', label: 'Planificar', primary: true }],
    planned: [
        { status: 'in_progress', label: 'Iniciar', primary: true },
        { status: 'cancelled', label: 'Cancelar', primary: false },
    ],
    in_progress: [
        { status: 'completed', label: 'Completar', primary: true },
        { status: 'on_hold', label: 'Pausar', primary: false },
    ],
    on_hold: [
        { status: 'in_progress', label: 'Reanudar', primary: true },
        { status: 'cancelled', label: 'Cancelar', primary: false },
    ],
    completed: [
        { status: 'verified', label: 'Verificar', primary: true },
        { status: 'in_progress', label: 'Reabrir', primary: false },
    ],
    verified: [{ status: 'closed', label: 'Cerrar', primary: true }],
}

const primaryTransition = computed(() => transitionMap[wo.value?.status]?.find(t => t.primary) ?? null)
const secondaryTransitions = computed(() => transitionMap[wo.value?.status]?.filter(t => !t.primary) ?? [])

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
    { id: 'historial', label: 'Historial', count: milestones.value.length || null },
    { id: 'componentes', label: 'Componentes', count: tabs.componentes.loaded ? (components.value.length || null) : null },
    { id: 'tiempo', label: 'Tiempo & Repuestos', count: wo.value?.parts?.length || null },
    { id: 'evidencias', label: 'Evidencias', count: tabs.evidencias.loaded ? (media.value.length || null) : null },
    { id: 'firmas', label: 'Firmas', count: tabs.firmas.loaded ? (signatures.value.length || null) : null },
    { id: 'comentarios', label: 'Comentarios', count: wo.value?.comments?.length || null },
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
        if (tabId === 'evidencias') {
            const res = await api.get(`work-orders/${wo.value.id}/media`)
            media.value = res?.data ?? []
        } else if (tabId === 'firmas') {
            const res = await api.get(`work-orders/${wo.value.id}/signatures`)
            signatures.value = res?.data ?? []
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

const mediaGroups = computed(() => {
    const groups = { before: [], after: [], other: [] }
    media.value.forEach(m => {
        if (m.attachment_type === 'before_photo') { groups.before.push(m) }
        else if (m.attachment_type === 'after_photo') { groups.after.push(m) }
        else { groups.other.push(m) }
    })
    return [
        { key: 'before', label: 'Antes', items: groups.before },
        { key: 'after', label: 'Después', items: groups.after },
        { key: 'other', label: 'Otros', items: groups.other },
    ].filter(g => g.items.length)
})

function isImage(m) {
    return (m.mime_type ?? '').startsWith('image/')
}

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

function formatFileSize(bytes) {
    if (bytes == null) { return '' }
    if (bytes < 1024) { return `${bytes} B` }
    if (bytes < 1048576) { return `${(bytes / 1024).toFixed(0)} KB` }
    return `${(bytes / 1048576).toFixed(1)} MB`
}

function relativeTime(iso) {
    if (!iso) { return '' }
    const diff = Date.now() - new Date(iso).getTime()
    const h = Math.floor(diff / 36e5)
    if (h < 1) { return 'hace menos de 1h' }
    if (h < 24) { return `hace ${h}h` }
    return `hace ${Math.floor(h / 24)}d`
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

async function load() {
    loading.value = true
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

async function downloadPdf() {
    if (downloadingPdf.value || !wo.value) { return }
    downloadingPdf.value = true
    try {
        await api.download(`reports/work-orders/${wo.value.id}`, `${wo.value.work_order_number}.pdf`)
    } catch { /* ignored */ } finally {
        downloadingPdf.value = false
    }
}

async function transition(newStatus) {
    transitioning.value = true
    transitionError.value = null
    try {
        const res = await api.patch(`work-orders/${wo.value.id}/status`, { status: newStatus })
        wo.value = res?.data ?? res
    } catch (err) {
        transitionError.value = err?.message ?? 'Error al cambiar el estado'
    } finally {
        transitioning.value = false
    }
}

async function submitComment() {
    if (!newComment.value.trim()) { return }
    submittingComment.value = true
    commentError.value = null
    try {
        const res = await api.post(`work-orders/${wo.value.id}/comments`, {
            body: newComment.value.trim(),
            is_internal: commentInternal.value,
        })
        const raw = res?.data ?? res
        wo.value.comments = [
            ...(wo.value.comments ?? []),
            { ...raw, user: { name: auth.userName ?? 'Tú' } },
        ]
        newComment.value = ''
        commentInternal.value = false
    } catch (err) {
        commentError.value = err?.message ?? 'Error al enviar el comentario'
    } finally {
        submittingComment.value = false
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
