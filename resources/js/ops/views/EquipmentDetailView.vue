<template>
    <div class="min-h-full bg-gray-50">

        <!-- Loading skeleton -->
        <div v-if="loading">
            <div class="bg-white border-b border-gray-100 px-4 lg:px-8 py-5">
                <div class="max-w-4xl mx-auto">
                    <div class="skeleton h-4 w-16 rounded mb-5" />
                    <div class="flex gap-4 items-start">
                        <div class="skeleton w-16 h-16 rounded-2xl shrink-0" />
                        <div class="flex-1 space-y-2">
                            <div class="skeleton h-3 w-16 rounded" />
                            <div class="skeleton h-7 w-2/3 rounded" />
                            <div class="flex gap-2 mt-1">
                                <div class="skeleton h-5 w-16 rounded-full" />
                                <div class="skeleton h-5 w-24 rounded-full" />
                            </div>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mt-5">
                        <div v-for="i in 4" :key="i" class="skeleton h-16 rounded-xl" />
                    </div>
                </div>
            </div>
            <div class="max-w-4xl mx-auto px-4 lg:px-8 py-6 space-y-3">
                <div v-for="i in 5" :key="i" class="bg-white rounded-2xl border border-gray-100 p-4 space-y-2">
                    <div class="flex justify-between">
                        <div class="skeleton h-3 w-24 rounded" />
                        <div class="skeleton h-3 w-32 rounded" />
                    </div>
                    <div class="skeleton h-3 w-1/2 rounded" />
                </div>
            </div>
        </div>

        <!-- Main content -->
        <template v-else-if="equipment">

            <!-- Sticky header -->
            <div class="bg-white border-b border-gray-100">
                <div class="max-w-4xl mx-auto px-4 lg:px-8 pt-4 pb-0">

                    <!-- Back link -->
                    <RouterLink
                        :to="{ name: 'ops.equipos' }"
                        class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-800 transition-colors mb-4"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <polyline points="15 18 9 12 15 6"/>
                        </svg>
                        Equipos
                    </RouterLink>

                    <!-- Identity row -->
                    <div class="flex items-start gap-4 mb-5">
                        <div class="w-16 h-16 lg:w-20 lg:h-20 rounded-2xl overflow-hidden bg-slate-100 shrink-0 border border-gray-200">
                            <img
                                v-if="equipment.primary_photo_url"
                                :src="equipment.primary_photo_url"
                                :alt="equipment.name"
                                class="w-full h-full object-cover"
                            />
                            <div v-else class="w-full h-full flex items-center justify-center text-slate-400">
                                <svg class="w-8 h-8 lg:w-10 lg:h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l5.653-4.655m5.8-7.425c.316-.316.316-.828 0-1.143L9.79 2.94a.806.806 0 0 0-1.143 0L7.09 4.508a.806.806 0 0 0 0 1.143l5.4 5.4c.316.316.828.316 1.143 0l1.787-1.787z"/>
                                </svg>
                            </div>
                        </div>

                        <div class="flex-1 min-w-0">
                            <!-- Parent breadcrumb -->
                            <div v-if="equipment.parent" class="flex items-center gap-1 text-xs text-indigo-500 font-medium mb-1">
                                <RouterLink
                                    :to="{ name: 'ops.equipos.show', params: { id: equipment.parent.id } }"
                                    class="hover:text-indigo-700 transition-colors"
                                >
                                    {{ equipment.parent.name }}
                                </RouterLink>
                                <svg class="w-3 h-3 text-indigo-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <polyline points="9 18 15 12 9 6"/>
                                </svg>
                            </div>
                            <p class="text-xs font-mono font-bold text-gray-400 uppercase tracking-widest">{{ equipment.code }}</p>
                            <h1 class="text-xl lg:text-2xl font-bold text-gray-900 mt-0.5 leading-tight">{{ equipment.name }}</h1>
                            <div class="flex items-center gap-2 mt-2 flex-wrap">
                                <span
                                    v-if="equipment.status"
                                    class="text-xs font-semibold px-2.5 py-0.5 rounded-full"
                                    :class="statusColors[equipment.status] ?? 'bg-gray-100 text-gray-600'"
                                >
                                    {{ statusLabels[equipment.status] ?? equipment.status }}
                                </span>
                                <span
                                    v-if="equipment.criticality"
                                    class="text-xs font-semibold px-2.5 py-0.5 rounded-full"
                                    :class="criticalityColors[equipment.criticality] ?? 'bg-gray-100 text-gray-600'"
                                >
                                    {{ criticalityLabels[equipment.criticality] ?? equipment.criticality }}
                                </span>
                                <span class="text-sm text-gray-500">
                                    {{ equipment.plant?.name }}<span v-if="equipment.area"> · {{ equipment.area.name }}</span>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- KPI row -->
                    <div v-if="equipment.kpi" class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
                        <div class="rounded-xl p-3 bg-emerald-50">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-emerald-600 mb-1">Disponibilidad</p>
                            <p class="text-2xl font-bold text-gray-900 leading-none">
                                {{ equipment.kpi.availability_percentage != null ? `${Number(equipment.kpi.availability_percentage).toFixed(1)}%` : '—' }}
                            </p>
                        </div>
                        <div class="rounded-xl p-3 bg-blue-50">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-blue-600 mb-1">MTBF</p>
                            <p class="text-2xl font-bold text-gray-900 leading-none">
                                {{ equipment.kpi.mtbf_hours != null ? `${Number(equipment.kpi.mtbf_hours).toFixed(0)}h` : '—' }}
                            </p>
                        </div>
                        <div class="rounded-xl p-3 bg-amber-50">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-amber-600 mb-1">MTTR</p>
                            <p class="text-2xl font-bold text-gray-900 leading-none">
                                {{ equipment.kpi.mttr_hours != null ? `${Number(equipment.kpi.mttr_hours).toFixed(0)}h` : '—' }}
                            </p>
                        </div>
                        <div class="rounded-xl p-3 bg-red-50">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-red-600 mb-1">Fallos</p>
                            <p class="text-2xl font-bold text-gray-900 leading-none">{{ equipment.kpi.failure_count ?? '—' }}</p>
                        </div>
                    </div>
                    <div v-else class="mb-4 py-2 px-3 bg-gray-50 rounded-xl text-xs text-gray-400 text-center">
                        KPIs aún no calculados para este equipo
                    </div>

                    <!-- Tab bar -->
                    <div class="flex gap-0 overflow-x-auto border-t border-gray-100">
                        <button
                            v-for="tab in tabs"
                            :key="tab.id"
                            @click="activeTab = tab.id"
                            class="shrink-0 px-4 py-3 text-sm font-medium transition-colors border-b-2 -mb-px"
                            :class="activeTab === tab.id
                                ? 'border-emerald-500 text-emerald-700'
                                : 'border-transparent text-gray-500 hover:text-gray-800'"
                        >
                            {{ tab.label }}
                            <span
                                v-if="tab.count"
                                class="ml-1.5 text-[10px] font-bold bg-gray-100 text-gray-500 rounded-full px-1.5 py-0.5"
                            >
                                {{ tab.count }}
                            </span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Tab content -->
            <div class="max-w-4xl mx-auto px-4 lg:px-8 py-6">

                <!-- ── Información ─────────────────────────────────────────── -->
                <div v-if="activeTab === 'info'" class="space-y-4">

                    <!-- Parent equipment banner -->
                    <div
                        v-if="equipment.parent"
                        class="bg-indigo-50 border border-indigo-100 rounded-2xl p-4 flex items-center justify-between gap-4"
                    >
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-indigo-400 mb-0.5">Componente de</p>
                            <p class="text-sm font-bold text-indigo-900">{{ equipment.parent.name }}</p>
                            <p class="text-[10px] font-mono text-indigo-400">{{ equipment.parent.code }}</p>
                        </div>
                        <RouterLink
                            :to="{ name: 'ops.equipos.show', params: { id: equipment.parent.id } }"
                            class="shrink-0 flex items-center gap-1 text-xs font-semibold text-indigo-600 hover:text-indigo-800 transition-colors"
                        >
                            Ver perfil
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <polyline points="9 18 15 12 9 6"/>
                            </svg>
                        </RouterLink>
                    </div>

                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
                        <div class="px-4 py-3 border-b border-gray-50">
                            <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-400">Identificación</h3>
                        </div>
                        <div class="px-4 divide-y divide-gray-50">
                            <InfoRow label="Código" :value="equipment.code" />
                            <InfoRow label="Modelo" :value="equipment.model" />
                            <InfoRow label="N° de serie" :value="equipment.serial_number" />
                            <InfoRow label="Asset Tag" :value="equipment.asset_tag" />
                            <InfoRow label="Categoría" :value="equipment.category?.name" />
                            <InfoRow label="Prioridad" :value="equipment.priority?.toUpperCase()" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
                            <div class="px-4 py-3 border-b border-gray-50">
                                <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-400">Ubicación</h3>
                            </div>
                            <div class="px-4 divide-y divide-gray-50">
                                <InfoRow label="Planta" :value="equipment.plant?.name" />
                                <InfoRow label="Área" :value="equipment.area?.name" />
                                <InfoRow label="Notas de ubicación" :value="equipment.location_notes" />
                            </div>
                        </div>

                        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
                            <div class="px-4 py-3 border-b border-gray-50">
                                <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-400">Fabricante & Proveedor</h3>
                            </div>
                            <div class="px-4 divide-y divide-gray-50">
                                <InfoRow label="Fabricante" :value="equipment.manufacturer?.name" />
                                <InfoRow label="Proveedor" :value="equipment.supplier?.name" />
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
                        <div class="px-4 py-3 border-b border-gray-50">
                            <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-400">Fechas</h3>
                        </div>
                        <div class="px-4 divide-y divide-gray-50">
                            <InfoRow label="Compra" :value="formatDate(equipment.purchase_date)" />
                            <InfoRow label="Instalación" :value="formatDate(equipment.installation_date)" />
                            <InfoRow label="Puesta en marcha" :value="formatDate(equipment.commissioning_date)" />
                            <InfoRow label="Vence garantía" :value="formatDate(equipment.warranty_expiry_date)" />
                            <InfoRow label="Vida útil" :value="equipment.useful_life_years ? `${equipment.useful_life_years} años` : null" />
                            <InfoRow label="Última falla" :value="formatDate(equipment.last_failure_at)" />
                            <InfoRow v-if="equipment.retired_at" label="Dado de baja" :value="formatDate(equipment.retired_at)" />
                            <InfoRow v-if="equipment.retired_reason" label="Motivo de baja" :value="equipment.retired_reason" />
                        </div>
                    </div>

                    <div
                        v-if="equipment.purchase_price || equipment.replacement_cost"
                        class="bg-white rounded-2xl border border-gray-100 shadow-sm"
                    >
                        <div class="px-4 py-3 border-b border-gray-50">
                            <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-400">Financiero</h3>
                        </div>
                        <div class="px-4 divide-y divide-gray-50">
                            <InfoRow
                                v-if="equipment.purchase_price"
                                label="Precio de compra"
                                :value="formatCurrency(equipment.purchase_price, equipment.currency_code)"
                            />
                            <InfoRow
                                v-if="equipment.replacement_cost"
                                label="Costo de reemplazo"
                                :value="formatCurrency(equipment.replacement_cost, equipment.currency_code)"
                            />
                        </div>
                    </div>

                    <div v-if="equipment.current_meter_reading != null" class="bg-white rounded-2xl border border-gray-100 shadow-sm">
                        <div class="px-4 py-3 border-b border-gray-50">
                            <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-400">Medidor</h3>
                        </div>
                        <div class="px-4 divide-y divide-gray-50">
                            <InfoRow label="Lectura actual" :value="`${equipment.current_meter_reading} ${equipment.meter_unit ?? ''}`" />
                        </div>
                    </div>

                    <div
                        v-if="equipment.technical_specs && Object.keys(equipment.technical_specs).length"
                        class="bg-white rounded-2xl border border-gray-100 shadow-sm"
                    >
                        <div class="px-4 py-3 border-b border-gray-50">
                            <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-400">Especificaciones técnicas</h3>
                        </div>
                        <div class="px-4 divide-y divide-gray-50">
                            <InfoRow
                                v-for="(val, key) in equipment.technical_specs"
                                :key="key"
                                :label="String(key)"
                                :value="String(val)"
                            />
                        </div>
                    </div>

                    <div v-if="equipment.notes" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-2">Notas</h3>
                        <p class="text-sm text-gray-700 leading-relaxed">{{ equipment.notes }}</p>
                    </div>

                </div>

                <!-- ── Componentes ─────────────────────────────────────────── -->
                <div v-else-if="activeTab === 'components'">

                    <!-- Health strip -->
                    <div v-if="componentStatusStats.length" class="flex flex-wrap gap-2 mb-3">
                        <button
                            v-for="stat in componentStatusStats"
                            :key="stat.status"
                            @click="toggleStatusFilter(stat.status)"
                            class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold transition-colors border"
                            :class="componentStatusFilter === stat.status
                                ? stat.activeClass
                                : 'bg-white border-gray-200 text-gray-500 hover:border-gray-300'"
                        >
                            <span class="w-2 h-2 rounded-full shrink-0" :class="stat.dotClass" />
                            {{ stat.count }} {{ stat.label }}
                        </button>
                    </div>

                    <!-- Search + Agregar -->
                    <div class="flex items-center gap-2 mb-3">
                        <div class="relative flex-1">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                            </svg>
                            <input
                                v-model="componentSearch"
                                type="text"
                                placeholder="Buscar por nombre o código..."
                                class="w-full pl-9 pr-3 py-2 text-xs border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-500 bg-white"
                            />
                        </div>
                        <button
                            @click="openAddModal"
                            class="shrink-0 flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold px-3.5 py-2 rounded-xl transition-colors"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                            </svg>
                            Agregar
                        </button>
                    </div>

                    <!-- Category filter pills -->
                    <div v-if="componentCategories.length" class="flex gap-1.5 overflow-x-auto pb-0.5 mb-4">
                        <button
                            @click="componentFilter = null"
                            class="shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold transition-colors"
                            :class="componentFilter === null
                                ? 'bg-slate-900 text-white'
                                : 'bg-white border border-gray-200 text-gray-600 hover:border-gray-300'"
                        >
                            Todas las categorías
                        </button>
                        <button
                            v-for="cat in componentCategories"
                            :key="cat.id"
                            @click="componentFilter = cat.id"
                            class="shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold transition-colors"
                            :class="componentFilter === cat.id
                                ? 'bg-emerald-700 text-white'
                                : 'bg-white border border-gray-200 text-gray-600 hover:border-gray-300'"
                        >
                            {{ cat.name }}
                        </button>
                    </div>
                    <div v-else class="mb-4" />

                    <div v-if="!filteredChildren.length" class="flex flex-col items-center justify-center py-20 text-center">
                        <div class="w-14 h-14 rounded-2xl bg-gray-100 flex items-center justify-center mb-4">
                            <svg class="w-7 h-7 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.429 9.75 2.25 12l4.179 2.25m0-4.5 5.571 3 5.571-3m-11.142 0L2.25 7.5 12 2.25l9.75 5.25-4.179 2.25m0 0L21.75 12l-4.179 2.25m0 0 4.179 2.25L12 21.75 2.25 16.5l4.179-2.25m11.142 0-5.571 3-5.571-3"/>
                            </svg>
                        </div>
                        <p class="text-sm font-medium text-gray-700">
                            {{ componentSearch || componentStatusFilter || componentFilter ? 'Sin resultados' : 'Sin componentes registrados' }}
                        </p>
                        <p class="text-xs text-gray-400 mt-1">
                            {{ componentSearch || componentStatusFilter || componentFilter ? 'Prueba con otros filtros' : 'Agrega sub-componentes a este equipo con el botón de arriba' }}
                        </p>
                    </div>

                    <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <RouterLink
                            v-for="child in filteredChildren"
                            :key="child.id"
                            :to="{ name: 'ops.equipos.show', params: { id: child.id } }"
                            class="block bg-white rounded-2xl border border-gray-100 shadow-sm hover:shadow-md hover:border-emerald-200 transition-all p-4"
                        >
                            <div class="flex items-start gap-3">
                                <div class="w-10 h-10 rounded-xl overflow-hidden bg-slate-100 shrink-0 border border-gray-100">
                                    <img v-if="child.primary_photo_url" :src="child.primary_photo_url" class="w-full h-full object-cover" />
                                    <div v-else class="w-full h-full flex items-center justify-center text-slate-300">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l5.653-4.655m5.8-7.425c.316-.316.316-.828 0-1.143L9.79 2.94a.806.806 0 0 0-1.143 0L7.09 4.508a.806.806 0 0 0 0 1.143l5.4 5.4c.316.316.828.316 1.143 0l1.787-1.787z"/>
                                        </svg>
                                    </div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-[10px] font-mono font-bold text-gray-400">{{ child.code }}</p>
                                    <p class="text-sm font-semibold text-gray-900 leading-snug mt-0.5 truncate">{{ child.name }}</p>
                                    <p v-if="child.model" class="text-[10px] text-gray-400 truncate">{{ child.model }}</p>
                                    <div class="flex items-center gap-1.5 mt-1.5 flex-wrap">
                                        <span
                                            class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full"
                                            :class="statusColors[child.status] ?? 'bg-gray-100 text-gray-600'"
                                        >
                                            {{ statusLabels[child.status] ?? child.status }}
                                        </span>
                                        <span
                                            v-if="child.criticality && child.criticality !== 'low'"
                                            class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full"
                                            :class="criticalityColors[child.criticality] ?? 'bg-gray-100 text-gray-600'"
                                        >
                                            {{ criticalityLabels[child.criticality] }}
                                        </span>
                                        <span v-if="child.category" class="text-[10px] text-gray-400 truncate">{{ child.category.name }}</span>
                                    </div>
                                </div>
                                <svg class="w-4 h-4 text-gray-300 shrink-0 mt-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <polyline points="9 18 15 12 9 6"/>
                                </svg>
                            </div>
                        </RouterLink>
                    </div>

                    <!-- Modal: Agregar componente -->
                    <Teleport to="body">
                        <Transition
                            enter-active-class="transition-opacity duration-200"
                            leave-active-class="transition-opacity duration-150"
                            enter-from-class="opacity-0"
                            leave-to-class="opacity-0"
                        >
                            <div
                                v-if="showAddModal"
                                class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4"
                                @click.self="closeAddModal"
                            >
                                <div class="absolute inset-0 bg-black/40 backdrop-blur-[2px]" @click="closeAddModal" />
                                <div class="relative w-full sm:max-w-md bg-white rounded-t-3xl sm:rounded-2xl shadow-2xl overflow-hidden">
                                    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                                        <h2 class="text-base font-bold text-gray-900">Agregar componente</h2>
                                        <button @click="closeAddModal" class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 hover:bg-gray-200 transition-colors">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                                            </svg>
                                        </button>
                                    </div>
                                    <form @submit.prevent="submitComponent" class="px-5 py-4 space-y-4">
                                        <div>
                                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Nombre <span class="text-red-500">*</span></label>
                                            <input v-model="form.name" type="text" placeholder="Ej. Sello mecánico frontal" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent" required autofocus />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Código <span class="text-gray-400 font-normal">(opcional)</span></label>
                                            <input v-model="form.code" type="text" placeholder="Ej. COMP-001" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent" />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Tipo de componente</label>
                                            <select v-model="form.category_id" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                                                <option value="">Sin categoría</option>
                                                <option v-for="cat in componentCategoryOptions" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                                            </select>
                                        </div>
                                        <div class="grid grid-cols-2 gap-3">
                                            <div>
                                                <label class="block text-xs font-semibold text-gray-700 mb-1.5">Estado</label>
                                                <select v-model="form.status" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                                                    <option value="active">Activo</option>
                                                    <option value="inactive">Inactivo</option>
                                                    <option value="under_maintenance">En mantenimiento</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block text-xs font-semibold text-gray-700 mb-1.5">Criticidad</label>
                                                <select v-model="form.criticality" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent">
                                                    <option value="">Sin definir</option>
                                                    <option value="critical">Crítico</option>
                                                    <option value="high">Alto</option>
                                                    <option value="medium">Medio</option>
                                                    <option value="low">Bajo</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="grid grid-cols-2 gap-3">
                                            <div>
                                                <label class="block text-xs font-semibold text-gray-700 mb-1.5">Modelo <span class="text-gray-400 font-normal">(opcional)</span></label>
                                                <input v-model="form.model" type="text" placeholder="Ej. SKF 6205" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent" />
                                            </div>
                                            <div>
                                                <label class="block text-xs font-semibold text-gray-700 mb-1.5">N° de serie <span class="text-gray-400 font-normal">(opcional)</span></label>
                                                <input v-model="form.serial_number" type="text" placeholder="Ej. SN-00123" class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent" />
                                            </div>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Notas <span class="text-gray-400 font-normal">(opcional)</span></label>
                                            <textarea v-model="form.notes" rows="2" placeholder="Observaciones sobre este componente..." class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent" />
                                        </div>
                                        <p v-if="formError" class="text-xs text-red-600 bg-red-50 px-3 py-2 rounded-lg">{{ formError }}</p>
                                        <div class="flex gap-3 pt-1 pb-2">
                                            <button type="button" @click="closeAddModal" class="flex-1 px-4 py-2.5 rounded-xl border border-gray-200 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition-colors">Cancelar</button>
                                            <button type="submit" :disabled="submitting || !form.name" class="flex-1 px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-semibold transition-colors flex items-center justify-center gap-2">
                                                <svg v-if="submitting" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                                </svg>
                                                {{ submitting ? 'Guardando...' : 'Agregar' }}
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </Transition>
                    </Teleport>
                </div>

                <!-- ── Historial ───────────────────────────────────────────── -->
                <div v-else-if="activeTab === 'history'">
                    <div v-if="historyLoading" class="space-y-3">
                        <div v-for="i in 4" :key="i" class="bg-white rounded-2xl border border-gray-100 p-4 space-y-2">
                            <div class="flex justify-between">
                                <div class="skeleton h-4 w-1/2 rounded" />
                                <div class="skeleton h-5 w-16 rounded-full" />
                            </div>
                            <div class="skeleton h-3 w-1/3 rounded" />
                        </div>
                    </div>
                    <template v-else>
                        <!-- Toolbar: type filter + view toggle -->
                        <div v-if="workOrders.length" class="flex items-center gap-3 mb-4">
                            <div class="flex gap-1.5 overflow-x-auto flex-1">
                                <button
                                    @click="historyTypeFilter = null"
                                    class="shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold transition-colors"
                                    :class="historyTypeFilter === null
                                        ? 'bg-slate-900 text-white'
                                        : 'bg-white border border-gray-200 text-gray-600 hover:border-gray-300'"
                                >
                                    Todos <span class="ml-1 opacity-60">{{ workOrders.length }}</span>
                                </button>
                                <button
                                    v-for="type in historyTypeOptions"
                                    :key="type"
                                    @click="historyTypeFilter = type"
                                    class="shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold transition-colors"
                                    :class="historyTypeFilter === type
                                        ? 'bg-slate-700 text-white'
                                        : 'bg-white border border-gray-200 text-gray-600 hover:border-gray-300'"
                                >
                                    {{ woTypeLabel(type) }}
                                </button>
                            </div>
                            <!-- View toggle -->
                            <div class="flex items-center gap-0.5 bg-gray-100 rounded-lg p-0.5 shrink-0">
                                <button
                                    @click="historyView = 'list'"
                                    class="px-2.5 py-1 rounded-md text-[10px] font-semibold transition-colors"
                                    :class="historyView === 'list' ? 'bg-white shadow-sm text-gray-800' : 'text-gray-400 hover:text-gray-600'"
                                >
                                    Lista
                                </button>
                                <button
                                    @click="historyView = 'timeline'"
                                    class="px-2.5 py-1 rounded-md text-[10px] font-semibold transition-colors"
                                    :class="historyView === 'timeline' ? 'bg-white shadow-sm text-gray-800' : 'text-gray-400 hover:text-gray-600'"
                                >
                                    Timeline
                                </button>
                            </div>
                        </div>

                        <!-- Empty state -->
                        <div v-if="!filteredWorkOrders.length" class="flex flex-col items-center justify-center py-20 text-center">
                            <div class="w-14 h-14 rounded-2xl bg-gray-100 flex items-center justify-center mb-4">
                                <svg class="w-7 h-7 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.35 3.836c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m8.9-4.414c.376.023.75.05 1.124.08 1.131.094 1.976 1.057 1.976 2.192V16.5A2.25 2.25 0 0 1 18 18.75h-2.25m-7.5-10.5H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V18.75m-7.5-10.5h6.375c.621 0 1.125.504 1.125 1.125v9.375m-8.25-3 1.5 1.5 3-3.75"/>
                                </svg>
                            </div>
                            <p class="text-sm font-medium text-gray-700">Sin órdenes de trabajo</p>
                            <p class="text-xs text-gray-400 mt-1">No hay historial de mantenimiento para este equipo</p>
                        </div>

                        <!-- List view -->
                        <div v-else-if="historyView === 'list'" class="space-y-2">
                            <RouterLink
                                v-for="wo in filteredWorkOrders"
                                :key="wo.id"
                                :to="{ name: 'ops.ordenes.show', params: { id: wo.id } }"
                                class="block bg-white rounded-2xl border border-gray-100 p-4 hover:shadow-md hover:border-gray-200 transition-all group"
                            >
                                <div class="flex items-start justify-between gap-3 mb-1.5">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 mb-1">
                                            <span class="font-mono text-[10px] text-gray-400">{{ wo.work_order_number }}</span>
                                            <span
                                                v-if="wo.priority"
                                                class="text-[10px] font-bold px-1.5 py-0.5 rounded-full"
                                                :class="woPriorityColors[wo.priority] ?? 'bg-gray-100 text-gray-500'"
                                            >
                                                {{ wo.priority?.toUpperCase() }}
                                            </span>
                                        </div>
                                        <p class="text-sm font-semibold text-gray-900 leading-snug group-hover:text-emerald-700 transition-colors truncate">
                                            {{ wo.title || wo.description || wo.work_order_number }}
                                        </p>
                                    </div>
                                    <span
                                        class="shrink-0 text-[10px] font-bold px-2 py-0.5 rounded-full"
                                        :class="woStatusColors[wo.status] ?? 'bg-gray-100 text-gray-600'"
                                    >
                                        {{ woStatusLabels[wo.status] ?? wo.status }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-3 text-xs text-gray-400">
                                    <span
                                        v-if="wo.work_order_type"
                                        class="font-medium"
                                        :class="wo.work_order_type === 'corrective' ? 'text-red-400' : 'text-blue-400'"
                                    >
                                        {{ woTypeLabel(wo.work_order_type) }}
                                    </span>
                                    <span>{{ relativeTime(wo.created_at) }}</span>
                                    <svg class="w-3.5 h-3.5 text-gray-300 ml-auto shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <polyline points="9 18 15 12 9 6"/>
                                    </svg>
                                </div>
                            </RouterLink>
                        </div>

                        <!-- Timeline view -->
                        <div v-else class="pb-4">
                            <div v-for="group in groupedWorkOrders" :key="group.key" class="mb-8 last:mb-0">
                                <!-- Month header -->
                                <div class="flex items-center gap-3 mb-3">
                                    <div class="w-7 flex justify-center shrink-0">
                                        <div class="w-2 h-2 rounded-full bg-gray-300" />
                                    </div>
                                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest capitalize">{{ group.label }}</p>
                                </div>
                                <!-- Events with connecting line -->
                                <div class="ml-[13px] border-l-2 border-gray-100 space-y-2.5">
                                    <div v-for="wo in group.items" :key="wo.id" class="relative pl-6">
                                        <!-- Type dot -->
                                        <div
                                            class="absolute top-3.5 w-3 h-3 rounded-full ring-2 ring-white shadow-sm"
                                            style="left: -7px"
                                            :class="woTypeDotClass(wo.work_order_type)"
                                        />
                                        <!-- Event card -->
                                        <RouterLink
                                            :to="{ name: 'ops.ordenes.show', params: { id: wo.id } }"
                                            class="block bg-white rounded-xl border border-gray-100 p-3.5 hover:shadow-md hover:border-gray-200 transition-all group"
                                        >
                                            <div class="flex items-start justify-between gap-2 mb-1">
                                                <div class="flex-1 min-w-0">
                                                    <div class="flex items-center gap-1.5 mb-1 flex-wrap">
                                                        <span class="font-mono text-[10px] text-gray-400">{{ wo.work_order_number }}</span>
                                                        <span
                                                            v-if="wo.work_order_type"
                                                            class="text-[10px] font-bold px-1.5 py-0.5 rounded-full"
                                                            :class="{
                                                                'bg-red-100 text-red-700': wo.work_order_type === 'corrective',
                                                                'bg-blue-100 text-blue-700': wo.work_order_type === 'preventive',
                                                                'bg-purple-100 text-purple-700': wo.work_order_type === 'predictive',
                                                                'bg-emerald-100 text-emerald-700': wo.work_order_type === 'inspection',
                                                            }"
                                                        >
                                                            {{ woTypeLabel(wo.work_order_type) }}
                                                        </span>
                                                        <span
                                                            v-if="wo.priority"
                                                            class="text-[10px] font-bold px-1.5 py-0.5 rounded-full"
                                                            :class="woPriorityColors[wo.priority] ?? 'bg-gray-100 text-gray-500'"
                                                        >
                                                            {{ wo.priority?.toUpperCase() }}
                                                        </span>
                                                    </div>
                                                    <p class="text-sm font-semibold text-gray-900 leading-snug group-hover:text-emerald-700 transition-colors truncate">
                                                        {{ wo.title || wo.description || wo.work_order_number }}
                                                    </p>
                                                </div>
                                                <span
                                                    class="shrink-0 text-[10px] font-bold px-2 py-0.5 rounded-full"
                                                    :class="woStatusColors[wo.status] ?? 'bg-gray-100 text-gray-600'"
                                                >
                                                    {{ woStatusLabels[wo.status] ?? wo.status }}
                                                </span>
                                            </div>
                                            <p class="text-[11px] text-gray-400">{{ relativeTime(wo.created_at) }}</p>
                                        </RouterLink>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- ── Planes preventivos ──────────────────────────────────── -->
                <div v-else-if="activeTab === 'plans'">
                    <div v-if="plansLoading" class="space-y-3">
                        <div v-for="i in 3" :key="i" class="bg-white rounded-2xl border border-gray-100 p-4 space-y-2">
                            <div class="flex justify-between">
                                <div class="skeleton h-4 w-1/2 rounded" />
                                <div class="skeleton h-5 w-20 rounded-full" />
                            </div>
                            <div class="skeleton h-3 w-1/3 rounded" />
                        </div>
                    </div>
                    <div v-else-if="!plansData.length" class="flex flex-col items-center justify-center py-20 text-center">
                        <div class="w-14 h-14 rounded-2xl bg-gray-100 flex items-center justify-center mb-4">
                            <svg class="w-7 h-7 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
                            </svg>
                        </div>
                        <p class="text-sm font-medium text-gray-700">Sin planes preventivos</p>
                        <p class="text-xs text-gray-400 mt-1">No hay planes de mantenimiento activos para este equipo</p>
                    </div>
                    <div v-else class="space-y-3">
                        <div
                            v-for="plan in plansData"
                            :key="plan.id"
                            class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4"
                        >
                            <div class="flex items-start justify-between gap-3 mb-2">
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-[10px] font-bold text-gray-400">{{ plan.plan_number }}</span>
                                    <span
                                        class="text-[10px] font-bold px-2 py-0.5 rounded-full"
                                        :class="triggerBadgeClass(plan.trigger_source)"
                                    >
                                        {{ triggerBadgeLabel(plan.trigger_source) }}
                                    </span>
                                </div>
                                <span v-if="plan.schedule?.is_overdue" class="flex items-center gap-1 text-[10px] font-bold text-red-600 bg-red-50 px-2 py-0.5 rounded-full shrink-0">
                                    <span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse" />
                                    Vencido
                                </span>
                            </div>
                            <p class="text-sm font-bold text-gray-900 mb-1">{{ plan.name }}</p>
                            <p v-if="plan.frequency_label" class="text-xs text-gray-500 mb-3">{{ plan.frequency_label }}</p>
                            <div class="grid grid-cols-3 gap-2 text-center">
                                <div class="bg-gray-50 rounded-xl p-2">
                                    <p
                                        class="text-xs font-semibold"
                                        :class="plan.schedule?.is_overdue ? 'text-red-600' : planDueClass(plan)"
                                    >
                                        {{ plan.schedule?.next_due_at ? formatDate(plan.schedule.next_due_at) : '—' }}
                                    </p>
                                    <p class="text-[10px] text-gray-400 mt-0.5">Próx. vence</p>
                                </div>
                                <div class="bg-gray-50 rounded-xl p-2">
                                    <p class="text-xs font-semibold text-gray-700">
                                        {{ plan.schedule?.last_completed_at ? formatDate(plan.schedule.last_completed_at) : '—' }}
                                    </p>
                                    <p class="text-[10px] text-gray-400 mt-0.5">Último compl.</p>
                                </div>
                                <div class="bg-gray-50 rounded-xl p-2">
                                    <p class="text-xs font-semibold text-gray-700">{{ plan.schedule?.times_executed ?? '—' }}</p>
                                    <p class="text-[10px] text-gray-400 mt-0.5">Ejecuciones</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── Solicitudes ─────────────────────────────────────────── -->
                <div v-else-if="activeTab === 'solicitudes'">
                    <div v-if="solicitudesLoading" class="space-y-3">
                        <div v-for="i in 3" :key="i" class="bg-white rounded-2xl border border-gray-100 p-4 space-y-2">
                            <div class="flex justify-between">
                                <div class="skeleton h-4 w-1/2 rounded" />
                                <div class="skeleton h-5 w-20 rounded-full" />
                            </div>
                            <div class="skeleton h-3 w-1/3 rounded" />
                        </div>
                    </div>
                    <div v-else-if="!solicitudesData.length" class="flex flex-col items-center justify-center py-20 text-center">
                        <div class="w-14 h-14 rounded-2xl bg-gray-100 flex items-center justify-center mb-4">
                            <svg class="w-7 h-7 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 0 1 21.75 8.25Z"/>
                            </svg>
                        </div>
                        <p class="text-sm font-medium text-gray-700">Sin solicitudes</p>
                        <p class="text-xs text-gray-400 mt-1">No hay solicitudes de mantenimiento para este equipo</p>
                    </div>
                    <div v-else class="space-y-2">
                        <RouterLink
                            v-for="mr in solicitudesData"
                            :key="mr.id"
                            :to="{ name: 'ops.solicitudes.show', params: { id: mr.id } }"
                            class="block bg-white rounded-2xl border border-gray-100 p-4 hover:shadow-md hover:border-gray-200 transition-all group"
                        >
                            <div class="flex items-start justify-between gap-3 mb-1.5">
                                <p class="flex-1 min-w-0 text-sm font-semibold text-gray-900 group-hover:text-emerald-700 transition-colors truncate">
                                    {{ mr.title || mr.description || 'Solicitud #' + mr.id.slice(-6) }}
                                </p>
                                <span
                                    class="shrink-0 text-[10px] font-bold px-2 py-0.5 rounded-full"
                                    :class="mrStatusColors[mr.status] ?? 'bg-gray-100 text-gray-600'"
                                >
                                    {{ mrStatusLabels[mr.status] ?? mr.status }}
                                </span>
                            </div>
                            <div class="flex items-center gap-3 text-xs text-gray-400">
                                <span v-if="mr.request_type" class="font-medium text-gray-500">{{ mrTypeLabel(mr.request_type) }}</span>
                                <span>{{ relativeTime(mr.created_at) }}</span>
                                <svg class="w-3.5 h-3.5 text-gray-300 ml-auto shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <polyline points="9 18 15 12 9 6"/>
                                </svg>
                            </div>
                        </RouterLink>
                    </div>
                </div>

                <!-- ── Fotos ───────────────────────────────────────────────── -->
                <div v-else-if="activeTab === 'photos'">
                    <div v-if="!equipment.photos?.length" class="flex flex-col items-center justify-center py-20 text-center">
                        <div class="w-14 h-14 rounded-2xl bg-gray-100 flex items-center justify-center mb-4">
                            <svg class="w-7 h-7 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/>
                            </svg>
                        </div>
                        <p class="text-sm font-medium text-gray-700">Sin fotos</p>
                        <p class="text-xs text-gray-400 mt-1">No hay fotografías registradas para este equipo</p>
                    </div>
                    <div v-else class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                        <a
                            v-for="photo in equipment.photos"
                            :key="photo.id"
                            :href="photo.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="group relative aspect-square rounded-2xl overflow-hidden bg-slate-100 block"
                        >
                            <img :src="photo.url" :alt="photo.caption ?? 'Foto'" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" />
                            <div v-if="photo.is_primary" class="absolute top-2 left-2 bg-emerald-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full">Principal</div>
                            <div v-if="photo.caption" class="absolute bottom-0 inset-x-0 bg-linear-to-t from-black/60 to-transparent p-2">
                                <p class="text-[10px] text-white font-medium truncate">{{ photo.caption }}</p>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- ── Documentos ─────────────────────────────────────────── -->
                <div v-else-if="activeTab === 'documents'">
                    <div v-if="!equipment.documents?.length" class="flex flex-col items-center justify-center py-20 text-center">
                        <div class="w-14 h-14 rounded-2xl bg-gray-100 flex items-center justify-center mb-4">
                            <svg class="w-7 h-7 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/>
                            </svg>
                        </div>
                        <p class="text-sm font-medium text-gray-700">Sin documentos</p>
                        <p class="text-xs text-gray-400 mt-1">No hay documentos registrados para este equipo</p>
                    </div>
                    <div v-else class="space-y-2">
                        <a
                            v-for="doc in equipment.documents"
                            :key="doc.id"
                            :href="doc.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="flex items-center gap-3 bg-white rounded-2xl border border-gray-100 shadow-sm hover:shadow-md hover:border-gray-200 transition-all p-4"
                        >
                            <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/>
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-gray-900 truncate">{{ doc.title || doc.name }}</p>
                                <p class="text-xs text-gray-400 mt-0.5 capitalize">
                                    {{ doc.type ? doc.type.replace(/_/g, ' ') : 'Documento' }}
                                    <span v-if="doc.size"> · {{ humanFileSize(doc.size) }}</span>
                                    <span v-if="doc.expires_at"> · Vence {{ formatDate(doc.expires_at) }}</span>
                                </p>
                            </div>
                            <svg class="w-4 h-4 text-gray-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                            </svg>
                        </a>
                    </div>
                </div>

            </div>
        </template>

        <!-- Not found -->
        <div v-else class="flex flex-col items-center justify-center min-h-96 text-center px-4">
            <div class="w-16 h-16 rounded-2xl bg-red-50 flex items-center justify-center mb-4">
                <svg class="w-8 h-8 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                </svg>
            </div>
            <p class="text-sm font-medium text-gray-700">Equipo no encontrado</p>
            <RouterLink :to="{ name: 'ops.equipos' }" class="mt-3 text-sm text-emerald-600 hover:text-emerald-800 font-semibold">
                Volver a equipos
            </RouterLink>
        </div>

    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch, h, defineComponent } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { useApi } from '../composables/useApi.js'
import { useToast } from '../composables/useToast.js'

const route = useRoute()
const api = useApi()
const toast = useToast()

const equipment = ref(null)
const workOrders = ref([])
const plansData = ref([])
const solicitudesData = ref([])
const loading = ref(true)
const historyLoading = ref(false)
const plansLoading = ref(false)
const solicitudesLoading = ref(false)
const activeTab = ref('info')
const historyTypeFilter = ref(null)
const historyView = ref('list')

// ── Component filter ───────────────────────────────────────────────────────────

const componentFilter = ref(null)
const componentStatusFilter = ref(null)
const componentSearch = ref('')

const componentCategories = computed(() => {
    if (!equipment.value?.children?.length) { return [] }
    const seen = new Set()
    return equipment.value.children
        .filter(c => c.category)
        .reduce((acc, c) => {
            if (!seen.has(c.category.id)) {
                seen.add(c.category.id)
                acc.push(c.category)
            }
            return acc
        }, [])
})

const componentStatusStats = computed(() => {
    const children = equipment.value?.children ?? []
    const active = children.filter(c => c.status === 'active').length
    const maintenance = children.filter(c => c.status === 'under_maintenance').length
    const inactive = children.filter(c => !c.status || c.status === 'inactive').length
    return [
        { status: 'active', label: 'Activos', count: active, dotClass: 'bg-emerald-500', activeClass: 'bg-emerald-100 border-emerald-300 text-emerald-700' },
        { status: 'under_maintenance', label: 'En mant.', count: maintenance, dotClass: 'bg-amber-500', activeClass: 'bg-amber-100 border-amber-300 text-amber-700' },
        { status: 'inactive', label: 'Inactivos', count: inactive, dotClass: 'bg-gray-400', activeClass: 'bg-gray-200 border-gray-400 text-gray-700' },
    ].filter(s => s.count > 0)
})

function toggleStatusFilter(status) {
    componentStatusFilter.value = componentStatusFilter.value === status ? null : status
}

const filteredChildren = computed(() => {
    if (!equipment.value?.children?.length) { return [] }
    let list = equipment.value.children
    if (componentFilter.value) { list = list.filter(c => c.category?.id === componentFilter.value) }
    if (componentStatusFilter.value) {
        if (componentStatusFilter.value === 'inactive') {
            list = list.filter(c => !c.status || c.status === 'inactive')
        } else {
            list = list.filter(c => c.status === componentStatusFilter.value)
        }
    }
    const q = componentSearch.value.trim().toLowerCase()
    if (q) { list = list.filter(c => c.name?.toLowerCase().includes(q) || c.code?.toLowerCase().includes(q)) }
    return list
})

// ── History type filter ────────────────────────────────────────────────────────

const historyTypeOptions = computed(() => {
    const types = new Set(workOrders.value.map(wo => wo.work_order_type).filter(Boolean))
    return [...types]
})

const filteredWorkOrders = computed(() => {
    if (!historyTypeFilter.value) { return workOrders.value }
    return workOrders.value.filter(wo => wo.work_order_type === historyTypeFilter.value)
})

const groupedWorkOrders = computed(() => {
    const groups = new Map()
    filteredWorkOrders.value.forEach(wo => {
        const date = new Date(wo.created_at)
        const key = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`
        const label = date.toLocaleDateString('es', { month: 'long', year: 'numeric' })
        if (!groups.has(key)) { groups.set(key, { key, label, items: [] }) }
        groups.get(key).items.push(wo)
    })
    return [...groups.values()]
})

// ── Add component modal ────────────────────────────────────────────────────────

const showAddModal = ref(false)
const submitting = ref(false)
const formError = ref(null)
const componentCategoryOptions = ref([])

const form = ref({ name: '', code: '', category_id: '', status: 'active', criticality: '', model: '', serial_number: '', notes: '' })

function resetForm() {
    form.value = { name: '', code: '', category_id: '', status: 'active', criticality: '', model: '', serial_number: '', notes: '' }
    formError.value = null
}

async function openAddModal() {
    resetForm()
    showAddModal.value = true
    if (!componentCategoryOptions.value.length) {
        try {
            const res = await api.get('equipment-categories?component_types=true')
            componentCategoryOptions.value = res?.data ?? []
        } catch { /* silent */ }
    }
}

function closeAddModal() {
    if (submitting.value) { return }
    showAddModal.value = false
}

async function submitComponent() {
    if (submitting.value || !form.value.name) { return }
    submitting.value = true
    formError.value = null

    try {
        const payload = {
            parent_equipment_id: route.params.id,
            name: form.value.name,
            status: form.value.status,
        }
        if (form.value.code) { payload.code = form.value.code }
        if (form.value.category_id) { payload.category_id = form.value.category_id }
        if (form.value.criticality) { payload.criticality = form.value.criticality }
        if (form.value.model) { payload.model = form.value.model }
        if (form.value.serial_number) { payload.serial_number = form.value.serial_number }
        if (form.value.notes) { payload.notes = form.value.notes }

        const res = await api.post('equipment', payload)
        const newChild = res?.data

        if (newChild && equipment.value) {
            equipment.value.children = [...(equipment.value.children ?? []), newChild]
        }

        toast.success('Componente registrado correctamente')
        showAddModal.value = false
    } catch (err) {
        formError.value = err?.message ?? 'No se pudo guardar el componente'
    } finally {
        submitting.value = false
    }
}

// ── Tabs ───────────────────────────────────────────────────────────────────────

const tabs = computed(() => [
    { id: 'info', label: 'Información', count: null },
    { id: 'components', label: 'Componentes', count: equipment.value?.children?.length || null },
    { id: 'history', label: 'Historial', count: workOrders.value.length || null },
    { id: 'plans', label: 'Planes', count: plansData.value.length || null },
    { id: 'solicitudes', label: 'Solicitudes', count: solicitudesData.value.length || null },
    { id: 'photos', label: 'Fotos', count: equipment.value?.photos?.length || null },
    { id: 'documents', label: 'Documentos', count: equipment.value?.documents?.length || null },
])

// ── Label / color maps ─────────────────────────────────────────────────────────

const statusColors = {
    active: 'bg-emerald-100 text-emerald-700',
    inactive: 'bg-gray-100 text-gray-600',
    under_maintenance: 'bg-amber-100 text-amber-700',
    retired: 'bg-red-100 text-red-700',
    decommissioned: 'bg-slate-100 text-slate-600',
}
const statusLabels = {
    active: 'Activo', inactive: 'Inactivo', under_maintenance: 'En mantenimiento',
    retired: 'Retirado', decommissioned: 'Desmantelado',
}

const criticalityColors = {
    critical: 'bg-red-100 text-red-700',
    high: 'bg-orange-100 text-orange-700',
    medium: 'bg-yellow-100 text-yellow-700',
    low: 'bg-green-100 text-green-700',
}
const criticalityLabels = { critical: 'Crítico', high: 'Alto', medium: 'Medio', low: 'Bajo' }

const woStatusColors = {
    draft: 'bg-gray-100 text-gray-600', open: 'bg-blue-100 text-blue-700',
    in_progress: 'bg-amber-100 text-amber-700', on_hold: 'bg-orange-100 text-orange-700',
    completed: 'bg-emerald-100 text-emerald-700', cancelled: 'bg-red-100 text-red-600',
    verified: 'bg-teal-100 text-teal-700',
}
const woStatusLabels = {
    draft: 'Borrador', open: 'Abierta', in_progress: 'En curso',
    on_hold: 'En espera', completed: 'Completada', cancelled: 'Cancelada', verified: 'Verificada',
}

const woPriorityColors = {
    p1: 'bg-red-100 text-red-700',
    p2: 'bg-orange-100 text-orange-700',
    p3: 'bg-yellow-100 text-yellow-700',
    p4: 'bg-gray-100 text-gray-500',
}

const mrStatusColors = {
    draft: 'bg-gray-100 text-gray-600',
    submitted: 'bg-blue-100 text-blue-700',
    under_review: 'bg-amber-100 text-amber-700',
    approved: 'bg-emerald-100 text-emerald-700',
    rejected: 'bg-red-100 text-red-700',
    cancelled: 'bg-gray-100 text-gray-500',
    completed: 'bg-teal-100 text-teal-700',
}
const mrStatusLabels = {
    draft: 'Borrador', submitted: 'Enviada', under_review: 'En revisión',
    approved: 'Aprobada', rejected: 'Rechazada', cancelled: 'Cancelada', completed: 'Completada',
}

// ── Helpers ────────────────────────────────────────────────────────────────────

const woTypeLabel = (type) => {
    const map = { corrective: 'Correctivo', preventive: 'Preventivo', predictive: 'Predictivo', inspection: 'Inspección' }
    return map[type] ?? type
}

const woTypeDotClass = (type) => ({
    corrective: 'bg-red-500',
    preventive: 'bg-blue-500',
    predictive: 'bg-purple-500',
    inspection: 'bg-emerald-500',
})[type] ?? 'bg-gray-400'

const mrTypeLabel = (type) => {
    const map = { corrective: 'Correctiva', preventive: 'Preventiva', inspection: 'Inspección', other: 'Otro' }
    return map[type] ?? type
}

const triggerBadgeClass = (source) => ({
    time: 'bg-blue-100 text-blue-700',
    meter: 'bg-amber-100 text-amber-700',
    hybrid: 'bg-emerald-100 text-emerald-700',
    manual: 'bg-gray-100 text-gray-600',
})[source] ?? 'bg-gray-100 text-gray-600'

const triggerBadgeLabel = (source) => ({
    time: 'Calendario', meter: 'Horómetro', hybrid: 'Híbrido', manual: 'Manual',
})[source] ?? source

const planDueClass = (plan) => {
    if (!plan.schedule?.next_due_at) { return 'text-gray-400' }
    const days = (new Date(plan.schedule.next_due_at) - Date.now()) / 864e5
    if (days < 7) { return 'text-amber-600' }
    return 'text-gray-700'
}

const formatDate = (d) => {
    if (!d) { return null }
    return new Date(d).toLocaleDateString('es', { day: '2-digit', month: 'short', year: 'numeric' })
}

const formatCurrency = (amount, currency = 'MXN') => {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: currency ?? 'MXN', maximumFractionDigits: 0 }).format(amount)
}

const relativeTime = (dateStr) => {
    const diff = Date.now() - new Date(dateStr).getTime()
    const hrs = Math.floor(diff / 36e5)
    if (hrs < 1) { return 'hace menos de 1h' }
    if (hrs < 24) { return `hace ${hrs}h` }
    return `hace ${Math.floor(hrs / 24)}d`
}

const humanFileSize = (bytes) => {
    const units = ['B', 'KB', 'MB', 'GB']
    let size = bytes, unit = 0
    while (size >= 1024 && unit < units.length - 1) { size /= 1024; unit++ }
    return `${Math.round(size * 10) / 10} ${units[unit]}`
}

// ── InfoRow component ─────────────────────────────────────────────────────────

const InfoRow = defineComponent({
    props: { label: String, value: [String, Number] },
    setup(props) {
        return () => {
            if (props.value == null || props.value === '') { return null }
            return h('div', { class: 'flex items-start justify-between gap-4 py-2.5' }, [
                h('span', { class: 'text-xs text-gray-500 shrink-0' }, props.label),
                h('span', { class: 'text-xs font-medium text-gray-900 text-right break-words max-w-[60%]' }, String(props.value)),
            ])
        }
    },
})

// ── Data loaders ──────────────────────────────────────────────────────────────

async function loadHistory() {
    if (workOrders.value.length) { return }
    historyLoading.value = true
    try {
        const res = await api.get(`work-orders?equipment_id=${route.params.id}&per_page=50&page=1`)
        workOrders.value = res?.data ?? []
    } catch { /* silent */ } finally {
        historyLoading.value = false
    }
}

async function loadPlans() {
    if (plansData.value.length) { return }
    plansLoading.value = true
    try {
        const res = await api.get(`maintenance-plans?equipment_id=${route.params.id}&is_active=true&per_page=50`)
        plansData.value = res?.data ?? []
    } catch { /* silent */ } finally {
        plansLoading.value = false
    }
}

async function loadSolicitudes() {
    if (solicitudesData.value.length) { return }
    solicitudesLoading.value = true
    try {
        const res = await api.get(`maintenance-requests?equipment_id=${route.params.id}&per_page=50&page=1`)
        solicitudesData.value = res?.data ?? []
    } catch { /* silent */ } finally {
        solicitudesLoading.value = false
    }
}

watch(activeTab, (tab) => {
    if (tab === 'history') { loadHistory() }
    if (tab === 'plans') { loadPlans() }
    if (tab === 'solicitudes') { loadSolicitudes() }
})

onMounted(async () => {
    try {
        const res = await api.get(`equipment/${route.params.id}`)
        equipment.value = res?.data ?? null
    } catch { /* 404 → stays null */ } finally {
        loading.value = false
    }
})
</script>
