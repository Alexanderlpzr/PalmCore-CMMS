<template>
    <div class="min-h-full bg-gray-50">

        <!-- Loading skeleton -->
        <div v-if="equipmentLoading">
            <div class="bg-white border-b border-gray-100 px-4 lg:px-8 py-5">
                <div class="max-w-5xl mx-auto">
                    <div class="skeleton h-3 w-40 rounded mb-4" />
                    <div class="flex gap-4 items-start">
                        <div class="skeleton w-20 h-20 rounded-2xl shrink-0" />
                        <div class="flex-1 space-y-2">
                            <div class="skeleton h-3 w-16 rounded" />
                            <div class="skeleton h-7 w-2/3 rounded" />
                            <div class="flex gap-2 mt-1">
                                <div class="skeleton h-5 w-16 rounded-full" />
                                <div class="skeleton h-5 w-24 rounded-full" />
                            </div>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 lg:grid-cols-5 gap-2 mt-5">
                        <div v-for="i in 5" :key="i" class="skeleton h-16 rounded-xl" />
                    </div>
                    <div class="skeleton h-10 rounded-lg mt-4" />
                </div>
            </div>
            <div class="max-w-5xl mx-auto px-4 lg:px-8 py-6 space-y-4">
                <div v-for="i in 4" :key="i" class="skeleton h-32 rounded-2xl" />
            </div>
        </div>

        <!-- Main content -->
        <template v-else-if="equipment">

            <!-- ── Sticky header ──────────────────────────────────────────────── -->
            <div class="bg-white border-b border-gray-100 sticky top-0 z-20 shadow-sm">
                <div class="max-w-5xl mx-auto px-4 lg:px-8 pt-3 pb-0">

                    <!-- Breadcrumbs -->
                    <div class="flex items-center gap-1 text-xs mb-3 flex-wrap">
                        <RouterLink :to="{ name: 'ops.equipos' }" class="text-gray-500 hover:text-gray-700 transition-colors shrink-0">
                            Equipos
                        </RouterLink>
                        <template v-for="anc in (equipment.ancestors ?? [])" :key="anc.id">
                            <svg class="w-3 h-3 text-gray-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                            <RouterLink :to="{ name: 'ops.equipos.show', params: { id: anc.id } }" class="text-indigo-400 hover:text-indigo-700 transition-colors shrink-0">
                                {{ anc.name }}
                            </RouterLink>
                        </template>
                        <svg class="w-3 h-3 text-gray-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                        <span class="text-gray-700 font-medium truncate">{{ equipment.name }}</span>
                    </div>

                    <!-- Identity row -->
                    <div class="flex items-start gap-3 mb-4">
                        <div class="w-14 h-14 lg:w-18 lg:h-18 rounded-2xl overflow-hidden bg-slate-100 shrink-0 border border-gray-200 cursor-pointer"
                            @click="equipment.primary_photo_url && (lightboxPhoto = { url: equipment.primary_photo_url, caption: equipment.name })">
                            <img v-if="equipment.primary_photo_url" :src="equipment.primary_photo_url" :alt="equipment.name" class="w-full h-full object-cover" />
                            <div v-else class="w-full h-full flex items-center justify-center text-slate-300">
                                <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l5.653-4.655m5.8-7.425c.316-.316.316-.828 0-1.143L9.79 2.94a.806.806 0 0 0-1.143 0L7.09 4.508a.806.806 0 0 0 0 1.143l5.4 5.4c.316.316.828.316 1.143 0l1.787-1.787z"/>
                                </svg>
                            </div>
                        </div>

                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-mono font-bold text-gray-500 uppercase tracking-widest leading-none">{{ equipment.code }}</p>
                            <h1 class="text-lg lg:text-2xl font-bold text-gray-900 mt-0.5 leading-tight">{{ equipment.name }}</h1>
                            <div class="flex items-center gap-1.5 mt-1.5 flex-wrap">
                                <Badge v-if="equipment.status" :tone="eqStatus(equipment.status).tone" :label="eqStatus(equipment.status).label" />
                                <Badge v-if="equipment.criticality" :tone="crit(equipment.criticality).tone" :label="crit(equipment.criticality).label" />
                                <span v-if="equipment.category" class="text-xs font-semibold px-2 py-0.5 rounded-full" :class="categoryBadgeClass(equipment.category?.color)">
                                    {{ equipment.category.name }}
                                </span>
                                <span class="text-xs text-gray-500">
                                    {{ equipment.plant?.name }}<span v-if="equipment.area"> · {{ equipment.area.name }}</span>
                                </span>
                            </div>
                        </div>

                        <div class="shrink-0 flex items-center gap-1.5">
                            <FavoriteStar type="equipment" :id="equipment.id" />
                            <button
                                @click="downloadPdf"
                                :disabled="downloadingPdf"
                                class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border border-gray-200 text-xs font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-50 transition-colors"
                            >
                                <AppIcon name="fileText" class="w-3.5 h-3.5" />
                                {{ downloadingPdf ? 'Generando…' : 'PDF' }}
                            </button>
                        </div>
                    </div>

                    <!-- KPI strip -->
                    <div v-if="equipment.kpi" class="grid grid-cols-3 lg:grid-cols-5 gap-2 mb-3">
                        <div class="rounded-xl p-2.5 bg-emerald-50">
                            <p class="text-xs font-bold uppercase tracking-wider text-emerald-600 leading-none mb-1">Disponibilidad</p>
                            <p class="text-lg font-bold text-gray-900 leading-none">{{ equipment.kpi.availability_percentage != null ? Number(equipment.kpi.availability_percentage).toFixed(1) + '%' : '—' }}</p>
                        </div>
                        <div class="rounded-xl p-2.5 bg-blue-50">
                            <p class="text-xs font-bold uppercase tracking-wider text-blue-600 leading-none mb-1">MTBF</p>
                            <p class="text-lg font-bold text-gray-900 leading-none">{{ equipment.kpi.mtbf_hours != null ? Number(equipment.kpi.mtbf_hours).toFixed(0) + 'h' : '—' }}</p>
                        </div>
                        <div class="rounded-xl p-2.5 bg-amber-50">
                            <p class="text-xs font-bold uppercase tracking-wider text-amber-600 leading-none mb-1">MTTR</p>
                            <p class="text-lg font-bold text-gray-900 leading-none">{{ equipment.kpi.mttr_hours != null ? Number(equipment.kpi.mttr_hours).toFixed(0) + 'h' : '—' }}</p>
                        </div>
                        <div class="rounded-xl p-2.5 bg-red-50">
                            <p class="text-xs font-bold uppercase tracking-wider text-red-600 leading-none mb-1">Fallas</p>
                            <p class="text-lg font-bold text-gray-900 leading-none">{{ equipment.kpi.failure_count ?? '—' }}</p>
                        </div>
                        <div class="rounded-xl p-2.5 bg-slate-100 col-span-3 lg:col-span-1">
                            <p class="text-xs font-bold uppercase tracking-wider text-slate-500 leading-none mb-1">Downtime</p>
                            <p class="text-lg font-bold text-gray-900 leading-none">{{ equipment.kpi.downtime_hours != null ? Number(equipment.kpi.downtime_hours).toFixed(0) + 'h' : '—' }}</p>
                        </div>
                    </div>
                    <p v-else class="text-[11px] text-gray-400 text-center mb-3 italic">Sin KPIs calculados</p>

                    <!-- Primary action bar -->
                    <div class="flex items-center gap-2 py-3 border-t border-gray-50" v-if="equipment.status !== 'retired'">
                        <!-- Crear OT dropdown -->
                        <div class="relative" ref="woDropdownRef">
                            <div class="flex rounded-xl overflow-hidden border border-indigo-200">
                                <button
                                    @click="openWoPanel('corrective')"
                                    class="flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold px-3 py-2 transition-colors"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                    Crear OT
                                </button>
                                <button
                                    @click="woDropdownOpen = !woDropdownOpen"
                                    class="bg-indigo-600 hover:bg-indigo-700 text-white px-2 py-2 border-l border-indigo-500 transition-colors"
                                    aria-label="Opciones de tipo de OT"
                                >
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                                </button>
                            </div>
                            <!-- Dropdown -->
                            <div v-if="woDropdownOpen" class="absolute left-0 top-full mt-1 z-30 bg-white rounded-xl border border-gray-100 shadow-lg py-1 min-w-[160px]">
                                <button v-for="t in woTypes" :key="t.value"
                                    @click="openWoPanel(t.value)"
                                    class="w-full text-left px-4 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                                    {{ t.label }}
                                </button>
                            </div>
                        </div>

                        <!-- Reportar problema -->
                        <button
                            @click="showReportPanel = true"
                            class="flex items-center gap-1.5 px-3 py-2 rounded-xl border border-gray-200 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition-colors"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                            Reportar problema
                        </button>

                        <!-- Registrar lectura -->
                        <a
                            :href="`/admin/${tenantSlug}/meter-readings/create?equipment_id=${equipment.id}`"
                            target="_blank"
                            rel="noopener"
                            class="flex items-center gap-1.5 px-3 py-2 rounded-xl border border-gray-200 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition-colors"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0H3"/></svg>
                            Registrar lectura
                        </a>
                    </div>

                    <!-- Desktop anchor nav -->
                    <div class="hidden lg:flex gap-0 overflow-x-auto border-t border-gray-100">
                        <button v-for="sec in desktopSections" :key="sec.id" @click="scrollToSection(sec.id)"
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
                        <button v-for="tab in mobileTabs" :key="tab.id" @click="mobileTab = tab.id"
                            class="shrink-0 px-4 py-3 text-sm font-medium transition-colors border-b-2 -mb-px"
                            :class="mobileTab === tab.id ? 'border-emerald-500 text-emerald-700' : 'border-transparent text-gray-500 hover:text-gray-800'">
                            {{ tab.label }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- ── Content sections ────────────────────────────────────────────── -->
            <div class="max-w-5xl mx-auto px-4 lg:px-8 py-6 space-y-8">

                <!-- ── OPERACIÓN ──────────────────────────────────────────────── -->
                <section id="operacion" class="scroll-mt-64" v-show="isDesktop || mobileTab === 'operacion'">
                    <SectionLabel label="Operación" />

                    <!-- Parent banner (componente de) -->
                    <div v-if="equipment.parent" class="bg-indigo-50 border border-indigo-100 rounded-2xl p-4 flex items-center justify-between gap-4 mb-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-indigo-400 mb-0.5">Componente de</p>
                            <p class="text-sm font-bold text-indigo-900">{{ equipment.parent.name }}</p>
                            <p class="text-xs font-mono text-indigo-400">{{ equipment.parent.code }}</p>
                        </div>
                        <RouterLink :to="{ name: 'ops.equipos.show', params: { id: equipment.parent.id } }"
                            class="shrink-0 flex items-center gap-1 text-xs font-semibold text-indigo-600 hover:text-indigo-800 transition-colors">
                            Ver perfil
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                        </RouterLink>
                    </div>

                    <!-- Active WOs (under_maintenance or open WOs) -->
                    <template v-if="!workOrdersLoading">
                        <div v-if="activeWorkOrders.length" class="space-y-3 mb-4">
                            <div v-for="wo in activeWorkOrders" :key="wo.id"
                                class="bg-amber-50 border border-amber-100 rounded-2xl p-4 flex items-start justify-between gap-4">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap mb-1">
                                        <span class="text-xs font-bold text-amber-700 uppercase tracking-wider">En intervención</span>
                                        <span class="text-xs font-mono text-amber-600">{{ wo.work_order_number }}</span>
                                        <span class="text-xs font-semibold px-1.5 py-0.5 rounded-full" :class="woStatusColors[wo.status] ?? 'bg-gray-100 text-gray-600'">
                                            {{ woStatusLabels[wo.status] ?? wo.status }}
                                        </span>
                                    </div>
                                    <p class="text-sm font-semibold text-gray-900 truncate">{{ wo.title }}</p>
                                    <p class="text-xs text-gray-500 mt-0.5">{{ formatDateTime(wo.created_at) }}</p>
                                </div>
                                <RouterLink :to="{ name: 'ops.ordenes.show', params: { id: wo.id }, query: { from: 'ops.equipos.show', fromId: equipment.id } }"
                                    class="shrink-0 flex items-center gap-1 text-xs font-semibold text-indigo-600 hover:text-indigo-800 transition-colors">
                                    Ver OT
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                                </RouterLink>
                            </div>

                            <!-- Link to all equipment WOs -->
                            <RouterLink :to="{ name: 'ops.ordenes', query: { equipment_id: equipment.id } }"
                                class="block text-center text-xs font-semibold text-gray-500 hover:text-gray-700 transition-colors py-1">
                                Ver todas las OTs de {{ equipment.name }} →
                            </RouterLink>
                        </div>

                        <div v-else class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 mb-4">
                            <p class="text-sm text-gray-500 text-center">Sin intervenciones activas</p>
                            <p v-if="lastInterventionRelative" class="text-xs text-gray-400 text-center mt-0.5">Última: {{ lastInterventionRelative }}</p>
                        </div>
                    </template>
                    <div v-else class="space-y-3 mb-4">
                        <div v-for="i in 2" :key="i" class="skeleton h-20 rounded-2xl" />
                    </div>

                    <!-- Sub-equipos with alerts -->
                    <template v-if="childrenWithAlerts.length">
                        <SectionLabel label="Sub-equipos con alertas" />
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                            <RouterLink v-for="child in childrenWithAlerts" :key="child.id"
                                :to="{ name: 'ops.equipos.show', params: { id: child.id } }"
                                class="bg-white rounded-2xl border border-amber-100 shadow-sm hover:border-amber-200 transition-all p-3 flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg overflow-hidden bg-amber-50 shrink-0 flex items-center justify-center">
                                    <svg class="w-4 h-4 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-mono font-bold text-gray-500">{{ child.code }}</p>
                                    <p class="text-sm font-semibold text-gray-800 truncate">{{ child.name }}</p>
                                    <p class="text-xs text-amber-600">{{ child.status === 'under_maintenance' ? 'En mantenimiento' : 'Prev. vencido' }}</p>
                                </div>
                                <svg class="w-4 h-4 text-gray-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                            </RouterLink>
                        </div>
                    </template>

                    <!-- Components tab (BOM) -->
                    <div class="mt-4">
                        <EquipmentComponentsTab :equipment-id="equipId" ref="componentsTabRef" />
                    </div>
                </section>

                <!-- ── MANTENIMIENTO ──────────────────────────────────────────── -->
                <section id="mantenimiento" class="scroll-mt-64" v-show="isDesktop || mobileTab === 'mantenimiento'">
                    <SectionLabel label="Mantenimiento" />

                    <!-- Accumulated cost + last intervention (moved from Estado section) -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3 mb-4">
                        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
                            <p class="text-xs font-bold uppercase tracking-widest text-indigo-600 mb-1">Costo acumulado</p>
                            <p class="text-2xl font-bold text-gray-900 leading-none">{{ accumulatedCost ?? '—' }}</p>
                        </div>
                        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
                            <p class="text-xs font-bold uppercase tracking-widest text-gray-500 mb-1">Última intervención</p>
                            <p class="text-lg font-bold text-gray-900 leading-tight">{{ lastInterventionDate ?? '—' }}</p>
                            <p v-if="lastInterventionRelative" class="text-xs text-gray-400 mt-0.5">{{ lastInterventionRelative }}</p>
                        </div>
                    </div>

                    <!-- Preventive plans -->
                    <template v-if="plans.length || plansLoading">
                        <h3 class="text-xs font-bold uppercase tracking-widest text-gray-500 mb-3">Planes preventivos</h3>
                        <div v-if="plansLoading" class="space-y-3 mb-6">
                            <div v-for="i in 2" :key="i" class="skeleton h-20 rounded-2xl" />
                        </div>
                        <div v-else class="space-y-3 mb-6">
                            <div v-for="plan in plans" :key="plan.id" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 flex-wrap mb-1">
                                            <p class="text-xs font-mono font-bold text-gray-500">{{ plan.plan_number }}</p>
                                            <span class="text-xs font-semibold px-1.5 py-0.5 rounded" :class="plan.trigger_source === 'time' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700'">
                                                {{ plan.trigger_source === 'time' ? 'Tiempo' : 'Medidor' }}
                                            </span>
                                            <span v-if="!plan.is_active" class="text-xs font-semibold px-1.5 py-0.5 rounded bg-gray-100 text-gray-500">Inactivo</span>
                                            <span v-if="plan.schedule?.is_overdue" class="text-xs font-semibold px-1.5 py-0.5 rounded bg-red-100 text-red-600">⚠ Vencido</span>
                                        </div>
                                        <p class="text-sm font-semibold text-gray-800 truncate">{{ plan.name }}</p>
                                        <p v-if="plan.frequency_label" class="text-xs text-gray-500 mt-0.5">{{ plan.frequency_label }}</p>
                                    </div>
                                </div>
                                <div v-if="plan.schedule" class="grid grid-cols-3 gap-3 mt-3 pt-3 border-t border-gray-50">
                                    <div>
                                        <p class="text-xs font-bold uppercase tracking-wider text-gray-500 mb-0.5">Próximo</p>
                                        <p class="text-xs font-semibold" :class="plan.schedule.is_overdue ? 'text-red-600' : 'text-gray-800'">
                                            {{ plan.schedule.is_overdue ? '⚠ VENCIDO' : (formatDate(plan.schedule.next_due_at) ?? '—') }}
                                        </p>
                                    </div>
                                    <div>
                                        <p class="text-xs font-bold uppercase tracking-wider text-gray-500 mb-0.5">Último</p>
                                        <p class="text-xs font-semibold text-gray-700">{{ formatDate(plan.schedule.last_completed_at) ?? '—' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs font-bold uppercase tracking-wider text-gray-500 mb-0.5">Ejecuciones</p>
                                        <p class="text-xs font-semibold text-gray-700">{{ plan.schedule.times_executed ?? 0 }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Recent work orders -->
                    <h3 class="text-xs font-bold uppercase tracking-widest text-gray-500 mb-3">Órdenes de trabajo recientes</h3>
                    <div v-if="workOrdersLoading" class="space-y-3">
                        <div v-for="i in 3" :key="i" class="skeleton h-20 rounded-2xl" />
                    </div>
                    <div v-else-if="workOrders.length" class="space-y-3">
                        <RouterLink v-for="wo in workOrders" :key="wo.id"
                            :to="{ name: 'ops.ordenes.show', params: { id: wo.id }, query: { from: 'ops.equipos.show', fromId: equipment.id } }"
                            class="bg-white rounded-2xl border border-gray-100 shadow-sm hover:border-gray-200 hover:shadow-md transition-all p-4 flex items-center gap-3">
                            <div class="w-2.5 h-2.5 rounded-full shrink-0" :class="woTypeDot[wo.work_order_type] ?? 'bg-gray-300'" />
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <p class="text-xs font-mono font-bold text-gray-500">{{ wo.work_order_number }}</p>
                                    <span class="text-xs font-semibold px-1.5 py-0.5 rounded-full" :class="woStatusColors[wo.status] ?? 'bg-gray-100 text-gray-600'">
                                        {{ woStatusLabels[wo.status] ?? wo.status }}
                                    </span>
                                    <span v-if="wo.priority !== 'p3_medium'" class="text-xs font-semibold px-1.5 py-0.5 rounded-full" :class="woPriorityColors[wo.priority] ?? 'bg-gray-100 text-gray-600'">
                                        {{ wo.priority?.toUpperCase() }}
                                    </span>
                                </div>
                                <p class="text-sm font-semibold text-gray-800 mt-0.5 truncate">{{ wo.title }}</p>
                                <p class="text-xs text-gray-500 mt-0.5">{{ formatDateTime(wo.created_at) }}</p>
                            </div>
                            <svg class="w-4 h-4 text-gray-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                        </RouterLink>

                        <RouterLink :to="{ name: 'ops.ordenes', query: { equipment_id: equipment.id } }"
                            class="block text-center text-xs font-semibold text-gray-500 hover:text-gray-700 transition-colors py-2">
                            Ver todas las OTs de {{ equipment.name }} →
                        </RouterLink>
                    </div>
                    <div v-else class="bg-white rounded-2xl border border-gray-100 shadow-sm py-6 text-center text-xs text-gray-500">
                        Sin órdenes de trabajo registradas
                        <div class="mt-3">
                            <button @click="openWoPanel('corrective')"
                                class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 transition-colors">
                                + Crear primera orden de trabajo
                            </button>
                        </div>
                    </div>

                    <!-- Spare parts used -->
                    <template v-if="recentParts.length">
                        <h3 class="text-xs font-bold uppercase tracking-widest text-gray-500 mb-3 mt-6">Repuestos utilizados</h3>
                        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm divide-y divide-gray-50">
                            <div v-for="event in recentParts" :key="event.id" class="px-4 py-3">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="flex-1 min-w-0">
                                        <RouterLink :to="{ name: 'ops.ordenes.show', params: { id: event.meta.ref_id } }"
                                            class="text-xs font-bold text-blue-600 hover:text-blue-800">
                                            OT #{{ event.meta.ref_number }}
                                        </RouterLink>
                                        <p class="text-xs text-gray-500">{{ formatDateTime(event.at) }}</p>
                                    </div>
                                </div>
                                <div class="mt-2 flex flex-wrap gap-1">
                                    <span v-for="p in event.meta.parts" :key="p.description"
                                        class="text-xs bg-orange-50 text-orange-700 border border-orange-100 px-2 py-0.5 rounded-full">
                                        {{ p.description }} × {{ p.quantity }} {{ p.unit }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </template>
                </section>

                <!-- ── ACTIVO ─────────────────────────────────────────────────── -->
                <section id="activo" class="scroll-mt-64" v-show="isDesktop || mobileTab === 'activo'">
                    <SectionLabel label="Activo" />

                    <!-- Sub-equipos (todos) -->
                    <template v-if="equipment.children?.length">
                        <h3 class="text-xs font-bold uppercase tracking-widest text-gray-500 mb-3">Sub-equipos ({{ equipment.children.length }})</h3>
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
                            <RouterLink
                                v-for="child in equipment.children"
                                :key="child.id"
                                :to="{ name: 'ops.equipos.show', params: { id: child.id } }"
                                class="bg-white rounded-2xl border border-gray-100 shadow-sm hover:border-indigo-200 hover:shadow-md transition-all p-4 flex gap-3"
                            >
                                <div class="w-12 h-12 rounded-xl overflow-hidden bg-slate-100 shrink-0 border border-gray-100">
                                    <img v-if="child.primary_photo_url" :src="child.primary_photo_url" :alt="child.name" class="w-full h-full object-cover" />
                                    <div v-else class="w-full h-full flex items-center justify-center text-slate-300">
                                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l5.653-4.655"/></svg>
                                    </div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0">
                                            <p class="text-xs font-mono font-bold text-gray-500 uppercase">{{ child.code }}</p>
                                            <p class="text-sm font-bold text-gray-900 leading-tight mt-0.5 truncate">{{ child.name }}</p>
                                            <p v-if="child.model" class="text-xs text-gray-500">{{ child.model }}</p>
                                        </div>
                                        <Badge :tone="eqStatus(child.status).tone" :label="eqStatus(child.status).label" class="shrink-0" />
                                    </div>
                                    <div class="flex items-center gap-1.5 mt-2 flex-wrap">
                                        <span v-if="child.category" class="text-xs font-semibold px-1.5 py-0.5 rounded" :class="categoryBadgeClass(child.category.color)">{{ child.category.name }}</span>
                                        <Badge v-if="child.criticality && child.criticality !== 'low'" :tone="crit(child.criticality).tone" :label="crit(child.criticality).label" />
                                    </div>
                                    <div class="flex items-center gap-3 mt-2 text-xs text-gray-500">
                                        <span v-if="child.kpi?.failure_count" class="flex items-center gap-0.5">
                                            <span class="w-1.5 h-1.5 rounded-full bg-red-400 shrink-0" />
                                            {{ child.kpi.failure_count }} falla{{ child.kpi.failure_count !== 1 ? 's' : '' }}
                                        </span>
                                        <span v-if="child.last_work_order_at" class="truncate">Última OT: {{ relativeTime(child.last_work_order_at) }}</span>
                                    </div>
                                    <div v-if="child.next_due_at" class="mt-1.5 flex items-center gap-1 text-xs"
                                        :class="isOverdue(child.next_due_at) ? 'text-red-500 font-semibold' : 'text-emerald-600'">
                                        <svg class="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/></svg>
                                        Próximo: {{ isOverdue(child.next_due_at) ? 'VENCIDO ' : '' }}{{ formatDate(child.next_due_at) }}
                                    </div>
                                </div>
                            </RouterLink>
                        </div>
                    </template>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <!-- Identification -->
                        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
                            <div class="px-4 py-3 border-b border-gray-50">
                                <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500">Identificación</h3>
                            </div>
                            <div class="px-4 divide-y divide-gray-50">
                                <InfoRow label="Código" :value="equipment.code" mono />
                                <InfoRow label="Modelo" :value="equipment.model" />
                                <InfoRow label="N° de serie" :value="equipment.serial_number" />
                                <InfoRow label="Asset Tag" :value="equipment.asset_tag" />
                                <InfoRow label="Prioridad" :value="equipment.priority?.toUpperCase()" />
                            </div>
                        </div>

                        <!-- Location + Parties -->
                        <div class="space-y-4">
                            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
                                <div class="px-4 py-3 border-b border-gray-50">
                                    <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500">Ubicación</h3>
                                </div>
                                <div class="px-4 divide-y divide-gray-50">
                                    <div v-if="equipment.plant" class="flex items-start justify-between py-2.5 gap-4">
                                        <span class="text-xs text-gray-500 shrink-0">Planta</span>
                                        <RouterLink
                                            :to="{ name: 'ops.plantes.show', params: { id: equipment.plant.id }, query: { from: 'ops.equipos.show', fromId: equipment.id } }"
                                            class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 transition-colors text-right">
                                            {{ equipment.plant.name }}
                                        </RouterLink>
                                    </div>
                                    <div v-if="equipment.area" class="flex items-start justify-between py-2.5 gap-4">
                                        <span class="text-xs text-gray-500 shrink-0">Área</span>
                                        <RouterLink
                                            :to="{ name: 'ops.areas.show', params: { id: equipment.area.id }, query: { from: 'ops.equipos.show', fromId: equipment.id } }"
                                            class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 transition-colors text-right">
                                            {{ equipment.area.name }}
                                        </RouterLink>
                                    </div>
                                    <InfoRow label="Notas" :value="equipment.location_notes" />
                                </div>
                            </div>
                            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
                                <div class="px-4 py-3 border-b border-gray-50">
                                    <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500">Fabricante & Proveedor</h3>
                                </div>
                                <div class="px-4 divide-y divide-gray-50">
                                    <InfoRow label="Fabricante" :value="equipment.manufacturer?.name" />
                                    <InfoRow label="Proveedor" :value="equipment.supplier?.name" />
                                </div>
                            </div>
                        </div>

                        <!-- Dates -->
                        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
                            <div class="px-4 py-3 border-b border-gray-50">
                                <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500">Fechas</h3>
                            </div>
                            <div class="px-4 divide-y divide-gray-50">
                                <InfoRow label="Compra" :value="formatDate(equipment.purchase_date)" />
                                <InfoRow label="Instalación" :value="formatDate(equipment.installation_date)" />
                                <InfoRow label="Puesta en marcha" :value="formatDate(equipment.commissioning_date)" />
                                <InfoRow label="Garantía expira" :value="formatDate(equipment.warranty_expiry_date)" />
                                <InfoRow v-if="equipment.retired_at" label="Retirado" :value="formatDate(equipment.retired_at)" />
                            </div>
                        </div>

                        <!-- Financial -->
                        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
                            <div class="px-4 py-3 border-b border-gray-50">
                                <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500">Financiero</h3>
                            </div>
                            <div class="px-4 divide-y divide-gray-50">
                                <InfoRow label="Precio de compra" :value="formatCurrency(equipment.purchase_price, equipment.currency_code)" />
                                <InfoRow label="Costo de reemplazo" :value="formatCurrency(equipment.replacement_cost, equipment.currency_code)" />
                                <InfoRow label="Vida útil" :value="equipment.useful_life_years ? equipment.useful_life_years + ' años' : null" />
                            </div>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div v-if="equipment.notes" class="bg-white rounded-2xl border border-gray-100 shadow-sm mt-4">
                        <div class="px-4 py-3 border-b border-gray-50">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500">Notas</h3>
                        </div>
                        <p class="px-4 py-3 text-sm text-gray-700 leading-relaxed">{{ equipment.notes }}</p>
                    </div>
                </section>

                <!-- ── DOCS & FOTOS ────────────────────────────────────────────── -->
                <section id="docs" class="scroll-mt-64" v-show="isDesktop || mobileTab === 'docs'">
                    <SectionLabel label="Docs & Fotos" />

                    <!-- Documents -->
                    <template v-if="equipment.documents?.length">
                        <h3 class="text-xs font-bold uppercase tracking-widest text-gray-500 mb-3">Documentos ({{ equipment.documents.length }})</h3>
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-3 mb-6">
                            <a v-for="doc in equipment.documents" :key="doc.id" :href="doc.url" target="_blank" rel="noopener"
                                class="bg-white rounded-2xl border border-gray-100 shadow-sm hover:border-gray-200 hover:shadow-md transition-all p-4 flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0" :class="docIconBg(doc.name)">
                                    <svg class="w-5 h-5" :class="docIconColor(doc.name)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                                    </svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-gray-800 truncate">{{ doc.title ?? doc.name }}</p>
                                    <p class="text-xs text-gray-500">{{ doc.name }}</p>
                                    <p v-if="doc.expires_at" class="text-xs text-amber-600 mt-0.5">Expira: {{ formatDate(doc.expires_at) }}</p>
                                </div>
                                <svg class="w-4 h-4 text-gray-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
                            </a>
                        </div>
                    </template>

                    <!-- Photos -->
                    <template v-if="equipment.photos?.length">
                        <h3 class="text-xs font-bold uppercase tracking-widest text-gray-500 mb-3">Fotos ({{ equipment.photos.length }})</h3>
                        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                            <div v-for="photo in equipment.photos" :key="photo.id"
                                @click="lightboxPhoto = photo"
                                class="aspect-square rounded-2xl overflow-hidden bg-slate-100 cursor-pointer hover:opacity-90 transition-opacity relative border border-gray-100">
                                <img :src="photo.url" :alt="photo.caption ?? ''" class="w-full h-full object-cover" />
                                <div v-if="photo.is_primary" class="absolute top-1.5 left-1.5 bg-emerald-500 text-white text-[8px] font-bold uppercase px-1.5 py-0.5 rounded">
                                    Principal
                                </div>
                                <p v-if="photo.caption" class="absolute bottom-0 left-0 right-0 bg-linear-to-t from-black/50 px-2 pb-2 pt-4 text-xs text-white leading-snug">
                                    {{ photo.caption }}
                                </p>
                            </div>
                        </div>
                    </template>

                    <!-- Empty docs + photos -->
                    <div v-if="!equipment.documents?.length && !equipment.photos?.length"
                        class="bg-white rounded-2xl border border-gray-100 shadow-sm py-8 text-center text-xs text-gray-500">
                        Sin documentación ni fotos registradas
                    </div>
                </section>

                <!-- ── HISTORIAL (Timeline) ───────────────────────────────────── -->
                <section id="historial" class="scroll-mt-64" v-show="isDesktop || mobileTab === 'historial'">
                    <SectionLabel label="Historial" />

                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
                        <!-- Filter tabs -->
                        <div class="flex items-center gap-0 px-2 pt-2 border-b border-gray-50 overflow-x-auto">
                            <button v-for="f in activityFilters" :key="f.value"
                                @click="activityFilter = f.value"
                                class="shrink-0 px-3 py-2 text-xs font-medium transition-colors border-b-2 -mb-px"
                                :class="activityFilter === f.value
                                    ? 'border-emerald-500 text-emerald-700 font-semibold'
                                    : 'border-transparent text-gray-500 hover:text-gray-700'">
                                {{ f.label }}
                                <span v-if="f.value === 'all' && activitiesMeta.total" class="ml-1 text-xs text-gray-400">({{ activitiesMeta.total }})</span>
                            </button>
                        </div>

                        <!-- Loading -->
                        <div v-if="activitiesLoading" class="px-5 py-6 space-y-4">
                            <div v-for="i in 5" :key="i" class="flex gap-3">
                                <div class="skeleton w-3 h-3 rounded-full mt-1 shrink-0" />
                                <div class="flex-1 space-y-1.5">
                                    <div class="skeleton h-3 w-3/4 rounded" />
                                    <div class="skeleton h-2.5 w-1/3 rounded" />
                                </div>
                            </div>
                        </div>

                        <!-- Events -->
                        <div v-else-if="filteredActivities.length" class="px-5 py-4">
                            <div class="relative">
                                <div class="absolute left-1.5 top-3 bottom-3 w-px bg-gray-100" />
                                <div class="space-y-5">
                                    <div v-for="event in filteredActivities" :key="event.id" class="flex gap-4 relative">
                                        <div class="w-3 h-3 rounded-full shrink-0 mt-1 ring-2 ring-white relative z-10" :class="activityDotBg[event.type] ?? 'bg-gray-300'" />
                                        <div class="flex-1 min-w-0 pb-1">
                                            <div class="flex items-start gap-2 flex-wrap">
                                                <span class="text-xs font-bold uppercase tracking-wider px-1.5 py-0.5 rounded" :class="activityBadgeClass[event.type] ?? 'bg-gray-100 text-gray-500'">
                                                    {{ activityTypeLabel[event.type] ?? event.type }}
                                                </span>
                                                <RouterLink v-if="event.meta?.ref_id && (event.type === 'work_order_created' || event.type === 'work_order_closed' || event.type === 'preventive_executed')"
                                                    :to="{ name: 'ops.ordenes.show', params: { id: event.meta.ref_id } }"
                                                    class="text-xs text-blue-600 hover:text-blue-800 font-medium">
                                                    OT #{{ event.meta.ref_number }}
                                                </RouterLink>
                                            </div>
                                            <p class="text-sm text-gray-800 mt-0.5 leading-snug">{{ event.title }}</p>
                                            <p v-if="event.meta?.description" class="text-xs text-gray-500 mt-0.5 leading-snug line-clamp-2">{{ event.meta.description }}</p>
                                            <p v-if="event.meta?.duration_minutes" class="text-xs text-gray-500 mt-0.5">Duración: {{ Math.round(event.meta.duration_minutes / 60 * 10) / 10 }}h</p>
                                            <div v-if="event.meta?.parts?.length" class="mt-1 flex flex-wrap gap-1">
                                                <span v-for="p in event.meta.parts.slice(0, 3)" :key="p.description" class="text-xs bg-orange-50 text-orange-700 px-1.5 py-0.5 rounded">
                                                    {{ p.description }} × {{ p.quantity }} {{ p.unit }}
                                                </span>
                                                <span v-if="event.meta.parts.length > 3" class="text-xs text-gray-500">+{{ event.meta.parts.length - 3 }} más</span>
                                            </div>
                                            <p class="text-xs text-gray-500 mt-1">{{ formatDateTime(event.at) }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Load more -->
                            <div v-if="activitiesMeta.has_more && activityFilter === 'all'" class="mt-4 pt-3 border-t border-gray-50 text-center">
                                <button @click="loadMoreActivities" :disabled="activitiesLoadingMore"
                                    class="text-xs font-semibold text-gray-500 hover:text-gray-800 transition-colors disabled:opacity-50">
                                    {{ activitiesLoadingMore ? 'Cargando…' : `Ver más (${activitiesMeta.total - activities.length} eventos)` }}
                                </button>
                            </div>
                        </div>

                        <!-- Empty -->
                        <div v-else class="px-5 py-10 text-center text-xs text-gray-500">
                            <template v-if="activityFilter !== 'all'">
                                Sin eventos de tipo "{{ activityFilters.find(f => f.value === activityFilter)?.label }}"
                            </template>
                            <template v-else>
                                Aún no hay actividad registrada para este equipo
                            </template>
                        </div>
                    </div>
                </section>

            </div>
        </template>

        <!-- Not found -->
        <EmptyState v-else icon="cube" title="Equipo no encontrado">
            <template #action>
                <RouterLink :to="{ name: 'ops.equipos' }" class="text-xs text-emerald-600 hover:text-emerald-800 transition-colors">← Volver a equipos</RouterLink>
            </template>
        </EmptyState>

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

        <!-- Quick create panels -->
        <QuickCreateWoPanel
            :equipment-id="equipId"
            :open="showWoPanel"
            :default-type="woPanelType"
            @close="showWoPanel = false; woDropdownOpen = false"
            @created="onWoCreated"
        />
        <QuickReportPanel
            :equipment-id="equipId"
            :open="showReportPanel"
            @close="showReportPanel = false"
            @created="onReportCreated"
        />

        <!-- WO dropdown outside click handler -->
        <div v-if="woDropdownOpen" class="fixed inset-0 z-20" @click="woDropdownOpen = false" />
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, defineComponent, h } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { useApi } from '../composables/useApi.js'
import { useAuthStore } from '../stores/auth.js'
import { describe, EQUIPMENT_STATUS, CRITICALITY } from '../../shared/design.js'
import Badge from '../components/Badge.vue'
import EmptyState from '../components/EmptyState.vue'
import AppIcon from '../components/AppIcon.vue'
import FavoriteStar from '../components/FavoriteStar.vue'
import EquipmentComponentsTab from '../components/EquipmentComponentsTab.vue'
import QuickCreateWoPanel from '../components/QuickCreateWoPanel.vue'
import QuickReportPanel from '../components/QuickReportPanel.vue'

// ── Inline sub-components ─────────────────────────────────────────────────────

const InfoRow = defineComponent({
    props: { label: String, value: [String, Number], mono: Boolean },
    setup(props) {
        return () => props.value != null && props.value !== ''
            ? h('div', { class: 'flex items-start justify-between py-2.5 gap-4' }, [
                h('span', { class: 'text-xs text-gray-500 shrink-0' }, props.label),
                h('span', { class: `text-xs font-semibold text-gray-800 text-right ${props.mono ? 'font-mono' : ''}` }, props.value),
            ])
            : null
    },
})

const SectionLabel = defineComponent({
    props: { label: String },
    setup(props) {
        return () => h('h2', { class: 'text-[11px] font-bold uppercase tracking-widest text-gray-400 pb-2 mb-4 border-b border-gray-100' }, props.label)
    },
})

// ── Route + API ───────────────────────────────────────────────────────────────

const route    = useRoute()
const api      = useApi()
const auth     = useAuthStore()
const equipId  = computed(() => route.params.id)
const tenantSlug = computed(() => auth.tenantSlug)

// ── State ─────────────────────────────────────────────────────────────────────

const equipmentLoading      = ref(true)
const activitiesLoading     = ref(true)
const activitiesLoadingMore = ref(false)
const workOrdersLoading     = ref(true)
const plansLoading          = ref(true)

const equipment  = ref(null)
const activities = ref([])
const activitiesMeta = ref({ has_more: false, total: 0, current_page: 1 })
const workOrders = ref([])
const plans      = ref([])

const lightboxPhoto  = ref(null)
const downloadingPdf = ref(false)
const activeSection  = ref('operacion')

// Quick action panels
const showWoPanel     = ref(false)
const showReportPanel = ref(false)
const woPanelType     = ref('corrective')
const woDropdownOpen  = ref(false)

// Timeline filter
const activityFilter = ref('all')

const activityFilters = [
    { label: 'Todos', value: 'all' },
    { label: 'OTs', value: 'work_orders' },
    { label: 'Preventivos', value: 'preventives' },
    { label: 'Paradas', value: 'downtime' },
    { label: 'Lecturas', value: 'readings' },
]

const activityFilterTypes = {
    work_orders: ['work_order_created', 'work_order_closed', 'failure_reported'],
    preventives: ['preventive_executed'],
    downtime: ['downtime'],
    readings: ['meter_reading'],
}

// WO type options for dropdown
const woTypes = [
    { value: 'corrective', label: 'Correctiva' },
    { value: 'preventive', label: 'Preventiva' },
    { value: 'predictive', label: 'Predictiva' },
    { value: 'inspection', label: 'Inspección' },
    { value: 'improvement', label: 'Mejora' },
    { value: 'emergency', label: 'Emergencia' },
]

// ── Actions ───────────────────────────────────────────────────────────────────

function openWoPanel(type) {
    woPanelType.value = type
    showWoPanel.value = true
    woDropdownOpen.value = false
}

function onWoCreated() {
    loadWorkOrders()
    loadActivities()
}

function onReportCreated() {
    loadActivities()
}

async function downloadPdf() {
    if (downloadingPdf.value || ! equipment.value) { return }
    downloadingPdf.value = true
    try {
        await api.download(`reports/equipment/${equipment.value.id}`, `${equipment.value.code}.pdf`)
    } catch { /* ignored */ } finally {
        downloadingPdf.value = false
    }
}

// ── Responsive ────────────────────────────────────────────────────────────────

const isDesktop = ref(typeof window !== 'undefined' && window.innerWidth >= 1024)
const mobileTab = ref('operacion')

function handleResize() {
    isDesktop.value = window.innerWidth >= 1024
}

// ── Computed ──────────────────────────────────────────────────────────────────

const activeWorkOrders = computed(() =>
    workOrders.value.filter(wo => ['draft', 'planned', 'in_progress', 'on_hold'].includes(wo.status))
)

const childrenWithAlerts = computed(() =>
    (equipment.value?.children ?? []).filter(c =>
        c.status === 'under_maintenance' || (c.next_due_at && new Date(c.next_due_at) < new Date())
    ).slice(0, 3)
)

const recentParts = computed(() =>
    activities.value.filter(e => e.type === 'parts_consumed').slice(0, 10)
)

const filteredActivities = computed(() => {
    if (activityFilter.value === 'all') { return activities.value }
    const types = activityFilterTypes[activityFilter.value] ?? []
    return activities.value.filter(e => types.includes(e.type))
})

const accumulatedCost = computed(() => {
    const kpi = equipment.value?.kpi
    const raw = kpi?.total_cost ?? kpi?.cost_accumulated ?? equipment.value?.cost_accumulated
    if (raw == null) { return null }
    return formatCurrency(raw, equipment.value?.currency_code)
})

const lastInterventionIso = computed(() =>
    equipment.value?.last_work_order_at ?? workOrders.value[0]?.created_at ?? null
)
const lastInterventionDate     = computed(() => formatDate(lastInterventionIso.value))
const lastInterventionRelative = computed(() => relativeTime(lastInterventionIso.value))

const componentsTabRef = ref(null)

const mobileTabs = [
    { id: 'operacion',    label: 'Operación' },
    { id: 'mantenimiento', label: 'Mantenimiento' },
    { id: 'activo',       label: 'Activo' },
    { id: 'docs',         label: 'Docs' },
    { id: 'historial',    label: 'Historial' },
]

const desktopSections = computed(() => {
    if (!equipment.value) { return [] }
    const docsCount = (equipment.value.documents?.length || 0) + (equipment.value.photos?.length || 0)
    return [
        { id: 'operacion',    label: 'Operación', count: activeWorkOrders.value.length || null },
        { id: 'mantenimiento', label: 'Mantenimiento', count: plans.value.length || null },
        { id: 'activo',       label: 'Activo' },
        { id: 'docs',         label: 'Docs & Fotos', count: docsCount || null },
        { id: 'historial',    label: 'Historial', count: activitiesMeta.value.total || null },
    ]
})

// ── Color maps ────────────────────────────────────────────────────────────────

const eqStatus = (s) => describe(EQUIPMENT_STATUS, s)
const crit     = (c) => describe(CRITICALITY, c)

const woStatusColors = {
    draft: 'bg-gray-100 text-gray-500', planned: 'bg-blue-100 text-blue-700',
    in_progress: 'bg-amber-100 text-amber-700', on_hold: 'bg-orange-100 text-orange-700',
    completed: 'bg-emerald-100 text-emerald-700', closed: 'bg-green-100 text-green-700',
    cancelled: 'bg-red-100 text-red-500', rejected: 'bg-red-100 text-red-600',
}
const woStatusLabels = {
    draft: 'Borrador', planned: 'Planificada', in_progress: 'En progreso', on_hold: 'En pausa',
    completed: 'Completada', closed: 'Cerrada', cancelled: 'Cancelada', rejected: 'Rechazada',
}
const woPriorityColors = {
    p1_critical: 'bg-red-100 text-red-700', p2_high: 'bg-orange-100 text-orange-700',
    p3_medium: 'bg-blue-100 text-blue-600', p4_low: 'bg-gray-100 text-gray-500',
    p5_planned: 'bg-gray-100 text-gray-400',
}
const woTypeDot = {
    corrective: 'bg-red-400', preventive: 'bg-blue-500',
    predictive: 'bg-purple-500', inspection: 'bg-emerald-500',
    improvement: 'bg-sky-500', emergency: 'bg-red-600',
}

const activityDotBg = {
    work_order_created: 'bg-blue-400', work_order_closed: 'bg-emerald-500',
    preventive_executed: 'bg-purple-500', downtime: 'bg-red-500',
    meter_reading: 'bg-amber-400', photo_added: 'bg-sky-400',
    document_added: 'bg-indigo-400', failure_reported: 'bg-red-600',
    parts_consumed: 'bg-orange-400',
}
const activityBadgeClass = {
    work_order_created: 'bg-blue-50 text-blue-700', work_order_closed: 'bg-emerald-50 text-emerald-700',
    preventive_executed: 'bg-purple-50 text-purple-700', downtime: 'bg-red-50 text-red-700',
    meter_reading: 'bg-amber-50 text-amber-700', photo_added: 'bg-sky-50 text-sky-700',
    document_added: 'bg-indigo-50 text-indigo-700', failure_reported: 'bg-red-100 text-red-700',
    parts_consumed: 'bg-orange-50 text-orange-700',
}
const activityTypeLabel = {
    work_order_created: 'OT creada', work_order_closed: 'OT cerrada',
    preventive_executed: 'Preventivo ejecutado', downtime: 'Fuera de servicio',
    meter_reading: 'Lectura', photo_added: 'Foto', document_added: 'Documento',
    failure_reported: 'Falla reportada', parts_consumed: 'Repuestos consumidos',
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function categoryBadgeClass(color) {
    const map = {
        emerald: 'bg-emerald-100 text-emerald-700', blue: 'bg-blue-100 text-blue-700',
        red: 'bg-red-100 text-red-700', amber: 'bg-amber-100 text-amber-700',
        purple: 'bg-purple-100 text-purple-700', indigo: 'bg-indigo-100 text-indigo-700',
        orange: 'bg-orange-100 text-orange-700', sky: 'bg-sky-100 text-sky-700',
        pink: 'bg-pink-100 text-pink-700', gray: 'bg-gray-100 text-gray-600',
    }
    return map[color] ?? 'bg-gray-100 text-gray-600'
}

function formatDate(iso) {
    if (!iso) { return null }
    return new Date(iso).toLocaleDateString('es', { day: '2-digit', month: 'short', year: 'numeric' })
}

function formatDateTime(iso) {
    if (!iso) { return '—' }
    return new Date(iso).toLocaleDateString('es', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function relativeTime(iso) {
    if (!iso) { return null }
    const diffMs  = Date.now() - new Date(iso).getTime()
    const diffDay = Math.round(Math.abs(diffMs) / 86400000)
    if (diffDay < 1) { return 'hoy' }
    return diffMs > 0 ? `hace ${diffDay} día${diffDay !== 1 ? 's' : ''}` : `en ${diffDay} día${diffDay !== 1 ? 's' : ''}`
}

function isOverdue(iso) {
    return iso && new Date(iso) < new Date()
}

function formatCurrency(amount, currency) {
    if (amount == null) { return null }
    return new Intl.NumberFormat('es', { style: 'currency', currency: currency ?? 'USD', minimumFractionDigits: 0 }).format(amount)
}

function scrollToSection(id) {
    document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
    activeSection.value = id
}

function docExt(name) {
    return (name ?? '').split('.').pop()?.toLowerCase() ?? ''
}
function docIconBg(name) {
    const ext = docExt(name)
    if (['pdf'].includes(ext)) { return 'bg-red-50' }
    if (['doc', 'docx'].includes(ext)) { return 'bg-blue-50' }
    if (['xls', 'xlsx'].includes(ext)) { return 'bg-emerald-50' }
    return 'bg-gray-100'
}
function docIconColor(name) {
    const ext = docExt(name)
    if (['pdf'].includes(ext)) { return 'text-red-500' }
    if (['doc', 'docx'].includes(ext)) { return 'text-blue-500' }
    if (['xls', 'xlsx'].includes(ext)) { return 'text-emerald-500' }
    return 'text-gray-500'
}

// ── Data loading ──────────────────────────────────────────────────────────────

async function loadEquipment() {
    try {
        const res = await api.get(`equipment/${equipId.value}`)
        equipment.value = res?.data ?? null
    } catch { /* silent */ } finally {
        equipmentLoading.value = false
    }
}

async function loadActivities() {
    activitiesLoading.value = true
    try {
        const res = await api.get(`equipment/${equipId.value}/activity?per_page=50`)
        activities.value     = res?.data ?? []
        activitiesMeta.value = res?.meta ?? {}
    } catch { /* silent */ } finally {
        activitiesLoading.value = false
    }
}

async function loadMoreActivities() {
    if (activitiesLoadingMore.value) { return }
    activitiesLoadingMore.value = true
    try {
        const nextPage = (activitiesMeta.value.current_page ?? 1) + 1
        const res = await api.get(`equipment/${equipId.value}/activity?per_page=50&page=${nextPage}`)
        activities.value     = [...activities.value, ...(res?.data ?? [])]
        activitiesMeta.value = res?.meta ?? {}
    } catch { /* silent */ } finally {
        activitiesLoadingMore.value = false
    }
}

async function loadWorkOrders() {
    workOrdersLoading.value = true
    try {
        const res = await api.get(`work-orders?equipment_id=${equipId.value}&per_page=6`)
        workOrders.value = res?.data ?? []
    } catch { /* silent */ } finally {
        workOrdersLoading.value = false
    }
}

async function loadPlans() {
    try {
        const res = await api.get(`maintenance-plans?equipment_id=${equipId.value}&per_page=6&is_active=true`)
        plans.value = res?.data ?? []
    } catch { /* silent */ } finally {
        plansLoading.value = false
    }
}

// ── Intersection Observer for desktop anchor nav ──────────────────────────────

let sectionObserver = null

function initSectionObserver() {
    if (sectionObserver) { sectionObserver.disconnect() }
    sectionObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                activeSection.value = entry.target.id
            }
        })
    }, { threshold: 0, rootMargin: '-40% 0px -55% 0px' })

    const sectionIds = ['operacion', 'mantenimiento', 'activo', 'docs', 'historial']
    sectionIds.forEach(id => {
        const el = document.getElementById(id)
        if (el) { sectionObserver.observe(el) }
    })
}

// ── Lifecycle ─────────────────────────────────────────────────────────────────

onMounted(() => {
    window.addEventListener('resize', handleResize)
    loadEquipment().then(() => {
        if (equipment.value) { initSectionObserver() }
    })
    loadActivities()
    loadWorkOrders()
    loadPlans()
})

onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
    if (sectionObserver) { sectionObserver.disconnect() }
})
</script>
