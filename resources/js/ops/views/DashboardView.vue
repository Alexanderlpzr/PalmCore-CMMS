<template>
    <div class="flex flex-col min-h-full bg-gray-50">

        <!-- ── 1. Encabezado ──────────────────────────────────────────────────── -->
        <div class="bg-white border-b border-gray-100 px-5 lg:px-8 py-5">
            <div class="max-w-5xl mx-auto flex items-start justify-between gap-4">
                <div>
                    <h1 class="text-xl font-bold text-gray-900">
                        Bienvenido, <span class="text-emerald-600">{{ firstName }}</span>
                    </h1>
                    <p class="text-sm text-gray-500 mt-0.5">{{ auth.tenantName }}</p>
                    <p class="text-xs text-gray-400 mt-0.5 capitalize">{{ formattedDate }}</p>
                </div>
                <div class="text-right shrink-0">
                    <p class="text-2xl font-bold text-gray-800 tabular-nums leading-none">{{ currentTime }}</p>
                    <p class="text-xs text-gray-400 mt-1">{{ auth.userName }}</p>
                </div>
            </div>
        </div>

        <div class="flex-1 px-5 lg:px-8 py-6 max-w-5xl mx-auto w-full space-y-6">

            <!-- ── 2. Carrusel institucional ─────────────────────────────────── -->
            <div v-if="institutionalImages.length > 0 || loadingImages" class="relative overflow-hidden rounded-2xl h-40 lg:h-52 bg-gray-200 shadow-sm">
                <div
                    class="flex transition-transform duration-500 ease-in-out h-full"
                    :style="{ transform: `translateX(-${carouselIndex * 100}%)` }"
                >
                    <div
                        v-for="(img, i) in institutionalImages"
                        :key="i"
                        class="min-w-full h-full bg-cover bg-center relative"
                        :style="img.url ? { backgroundImage: `url(${img.url})` } : {}"
                    >
                        <div class="absolute inset-0 bg-linear-to-t from-black/40 to-transparent" />
                        <div v-if="img.caption" class="absolute bottom-3 left-4 text-white text-sm font-medium">
                            {{ img.caption }}
                        </div>
                    </div>
                </div>

                <!-- Dots -->
                <div v-if="institutionalImages.length > 1" class="absolute bottom-3 right-4 flex gap-1.5">
                    <button
                        v-for="(_, i) in institutionalImages"
                        :key="i"
                        @click="carouselIndex = i"
                        class="w-1.5 h-1.5 rounded-full transition-colors"
                        :class="carouselIndex === i ? 'bg-white' : 'bg-white/40'"
                    />
                </div>

                <!-- Arrows -->
                <button
                    v-if="institutionalImages.length > 1"
                    @click="prevSlide"
                    class="absolute left-2 top-1/2 -translate-y-1/2 w-7 h-7 bg-black/30 hover:bg-black/50 rounded-full flex items-center justify-center text-white transition-colors"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                    </svg>
                </button>
                <button
                    v-if="institutionalImages.length > 1"
                    @click="nextSlide"
                    class="absolute right-2 top-1/2 -translate-y-1/2 w-7 h-7 bg-black/30 hover:bg-black/50 rounded-full flex items-center justify-center text-white transition-colors"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>
            </div>

            <!-- ── 3. Indicadores principales ────────────────────────────────── -->
            <div>
                <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Indicadores</h2>
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                    <RouterLink
                        v-for="stat in statCards"
                        :key="stat.label"
                        :to="{ name: stat.to }"
                        class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 flex flex-col gap-3 hover:shadow-md hover:border-gray-200 transition-all"
                    >
                        <div :class="`inline-flex items-center justify-center w-9 h-9 rounded-xl ${stat.bg}`">
                            <svg :class="`w-4 h-4 ${stat.color}`" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" v-html="stat.icon" />
                        </div>
                        <div>
                            <div v-if="loadingStats" class="skeleton h-7 w-12 rounded mb-1" />
                            <p v-else :class="`text-2xl font-bold tabular-nums ${stat.color}`">{{ stat.value }}</p>
                            <p class="text-xs text-gray-500 font-medium leading-tight">{{ stat.label }}</p>
                        </div>
                    </RouterLink>
                </div>
            </div>

            <!-- ── 4. Actividad reciente + 5. Novedades ───────────────────── -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                <!-- Actividad reciente -->
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                        <h2 class="text-sm font-semibold text-gray-800">Actividad reciente</h2>
                        <RouterLink :to="{ name: 'ops.ordenes' }" class="text-xs text-emerald-600 hover:text-emerald-700 font-medium">Ver OTs</RouterLink>
                    </div>

                    <!-- Loading -->
                    <div v-if="loadingActivity" class="divide-y divide-gray-50">
                        <div v-for="i in 4" :key="i" class="px-5 py-3.5 flex items-start gap-3">
                            <div class="skeleton w-7 h-7 rounded-lg shrink-0 mt-0.5" />
                            <div class="flex-1 space-y-2">
                                <div class="skeleton h-3 w-3/4 rounded" />
                                <div class="skeleton h-2.5 w-1/2 rounded" />
                            </div>
                        </div>
                    </div>

                    <!-- Empty -->
                    <div v-else-if="activityItems.length === 0" class="flex flex-col items-center justify-center py-10 text-center">
                        <svg class="w-8 h-8 text-gray-300 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p class="text-sm text-gray-500">Sin actividad reciente</p>
                    </div>

                    <!-- Feed -->
                    <div v-else class="divide-y divide-gray-50 max-h-80 overflow-y-auto">
                        <component
                            :is="item.type === 'work_order' ? RouterLink : 'div'"
                            v-for="item in activityItems"
                            :key="item.id"
                            v-bind="item.type === 'work_order' ? { to: { name: 'ops.ordenes.show', params: { id: item.id } } } : {}"
                            class="px-5 py-3 flex items-start gap-3 hover:bg-gray-50 transition-colors"
                        >
                            <!-- Icon -->
                            <div :class="`w-7 h-7 rounded-lg flex items-center justify-center shrink-0 mt-0.5 ${item.iconBg}`">
                                <svg :class="`w-3.5 h-3.5 ${item.iconColor}`" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" v-html="item.icon" />
                            </div>
                            <!-- Content -->
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate leading-snug">{{ item.title }}</p>
                                <p class="text-xs text-gray-500 mt-0.5 truncate">{{ item.subtitle }}</p>
                            </div>
                            <!-- Time -->
                            <span class="text-xs text-gray-400 shrink-0 mt-0.5">{{ item.time }}</span>
                        </component>
                    </div>
                </div>

                <!-- Novedades -->
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                        <h2 class="text-sm font-semibold text-gray-800">Novedades</h2>
                        <RouterLink :to="{ name: 'ops.alertas' }" class="text-xs text-emerald-600 hover:text-emerald-700 font-medium">Ver alertas</RouterLink>
                    </div>

                    <!-- Loading -->
                    <div v-if="loadingNovedades" class="divide-y divide-gray-50">
                        <div v-for="i in 3" :key="i" class="px-5 py-3.5 flex items-start gap-3">
                            <div class="skeleton w-7 h-7 rounded-lg shrink-0 mt-0.5" />
                            <div class="flex-1 space-y-2">
                                <div class="skeleton h-3 w-2/3 rounded" />
                                <div class="skeleton h-2.5 w-1/3 rounded" />
                            </div>
                        </div>
                    </div>

                    <!-- Empty -->
                    <div v-else-if="novedadesItems.length === 0" class="flex flex-col items-center justify-center py-10 text-center">
                        <svg class="w-8 h-8 text-gray-300 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p class="text-sm text-gray-500">Todo en orden</p>
                    </div>

                    <!-- List -->
                    <div v-else class="divide-y divide-gray-50 max-h-80 overflow-y-auto">
                        <div
                            v-for="item in novedadesItems"
                            :key="item.id"
                            class="px-5 py-3 flex items-start gap-3"
                        >
                            <div :class="`w-7 h-7 rounded-lg flex items-center justify-center shrink-0 mt-0.5 ${item.iconBg}`">
                                <svg :class="`w-3.5 h-3.5 ${item.iconColor}`" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" v-html="item.icon" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 leading-snug truncate">{{ item.title }}</p>
                                <p class="text-xs text-gray-500 mt-0.5 truncate">{{ item.subtitle }}</p>
                            </div>
                            <span :class="`text-xs font-semibold px-2 py-0.5 rounded-full shrink-0 ${item.badgeCls}`">{{ item.badge }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── 6. Accesos rápidos ──────────────────────────────────────── -->
            <div>
                <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Accesos rápidos</h2>
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                    <RouterLink
                        v-for="action in quickActions"
                        :key="action.label"
                        :to="action.to"
                        class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 flex flex-col items-center gap-2.5 hover:shadow-md hover:border-emerald-200 hover:bg-emerald-50/40 transition-all group text-center"
                    >
                        <div :class="`w-10 h-10 rounded-xl flex items-center justify-center ${action.bg} group-hover:scale-105 transition-transform`">
                            <svg :class="`w-5 h-5 ${action.color}`" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" v-html="action.icon" />
                        </div>
                        <span class="text-xs font-semibold text-gray-700 group-hover:text-gray-900 leading-tight">{{ action.label }}</span>
                    </RouterLink>
                </div>
            </div>

        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { RouterLink } from 'vue-router'
import { useAuthStore } from '../stores/auth.js'
import { useApi } from '../composables/useApi.js'

const auth = useAuthStore()
const api = useApi()

// ── State ─────────────────────────────────────────────────────────────────────
const loadingStats = ref(true)
const loadingActivity = ref(true)
const loadingNovedades = ref(true)
const loadingImages = ref(false)

const summary = ref({ activeWOs: 0, pendingMRs: 0, criticalAlerts: 0, maintenanceEquipment: 0 })
const activityData = ref({ work_orders: [], comments: [] })
const novedadesData = ref({ critical_alerts: [], upcoming_plans: [] })
const institutionalImages = ref([])

const carouselIndex = ref(0)
let carouselTimer = null
let clockTimer = null
const currentTime = ref('')

// ── Header helpers ────────────────────────────────────────────────────────────
const firstName = computed(() => (auth.userName ?? '').split(' ')[0] || 'Usuario')

const formattedDate = computed(() =>
    new Date().toLocaleDateString('es', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }),
)

function updateClock() {
    currentTime.value = new Date().toLocaleTimeString('es', { hour: '2-digit', minute: '2-digit' })
}

// ── Carousel ──────────────────────────────────────────────────────────────────
function nextSlide() {
    carouselIndex.value = (carouselIndex.value + 1) % institutionalImages.value.length
}
function prevSlide() {
    carouselIndex.value = (carouselIndex.value - 1 + institutionalImages.value.length) % institutionalImages.value.length
}
function startCarousel() {
    if (institutionalImages.value.length > 1) {
        carouselTimer = setInterval(nextSlide, 5000)
    }
}

// ── Stat cards ────────────────────────────────────────────────────────────────
const WO_ICON = '<path stroke-linecap="round" stroke-linejoin="round" d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>'
const MR_ICON = '<path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="2" ry="2"/>'
const ALERT_ICON = '<path stroke-linecap="round" stroke-linejoin="round" d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path stroke-linecap="round" stroke-linejoin="round" d="M13.73 21a2 2 0 0 1-3.46 0"/>'
const EQUIP_ICON = '<path stroke-linecap="round" stroke-linejoin="round" d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>'

const statCards = computed(() => [
    { label: 'OTs activas', value: summary.value.activeWOs, color: 'text-blue-600', bg: 'bg-blue-50', icon: WO_ICON, to: 'ops.ordenes' },
    { label: 'Solicitudes pendientes', value: summary.value.pendingMRs, color: 'text-amber-600', bg: 'bg-amber-50', icon: MR_ICON, to: 'ops.solicitudes' },
    { label: 'Alertas críticas', value: summary.value.criticalAlerts, color: 'text-red-600', bg: 'bg-red-50', icon: ALERT_ICON, to: 'ops.alertas' },
    { label: 'Equipos en mantenimiento', value: summary.value.maintenanceEquipment, color: 'text-slate-600', bg: 'bg-slate-100', icon: EQUIP_ICON, to: 'ops.equipos' },
])

// ── Activity feed ─────────────────────────────────────────────────────────────
const COMMENT_ICON = '<path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>'

const STATUS_COLORS = {
    in_progress: 'bg-blue-50 text-blue-600',
    completed: 'bg-emerald-50 text-emerald-600',
    planned: 'bg-indigo-50 text-indigo-600',
    on_hold: 'bg-amber-50 text-amber-600',
    draft: 'bg-gray-100 text-gray-600',
}

const STATUS_LABELS = {
    in_progress: 'En proceso',
    completed: 'Completada',
    planned: 'Planificada',
    on_hold: 'En pausa',
    draft: 'Borrador',
}

function relativeTime(isoStr) {
    const diff = Date.now() - new Date(isoStr).getTime()
    const m = Math.floor(diff / 60000)
    if (m < 1) { return 'ahora' }
    if (m < 60) { return `${m}m` }
    const h = Math.floor(m / 60)
    if (h < 24) { return `${h}h` }
    return `${Math.floor(h / 24)}d`
}

const activityItems = computed(() => {
    const wos = (activityData.value.work_orders ?? []).map(wo => ({
        id: wo.id,
        type: 'work_order',
        icon: WO_ICON,
        iconBg: STATUS_COLORS[wo.status]?.split(' ')[0] ?? 'bg-indigo-50',
        iconColor: STATUS_COLORS[wo.status]?.split(' ')[1] ?? 'text-indigo-600',
        title: wo.title,
        subtitle: `${wo.work_order_number} · ${STATUS_LABELS[wo.status] ?? wo.status}${wo.equipment_code ? ` · ${wo.equipment_code}` : ''}`,
        time: relativeTime(wo.updated_at),
        sortKey: new Date(wo.updated_at).getTime(),
    }))

    const comments = (activityData.value.comments ?? []).map(c => ({
        id: `c-${c.id}`,
        type: 'comment',
        icon: COMMENT_ICON,
        iconBg: 'bg-purple-50',
        iconColor: 'text-purple-600',
        title: c.body,
        subtitle: `${c.user_name ?? '?'} en ${c.work_order_number ?? '—'}`,
        time: relativeTime(c.created_at),
        sortKey: new Date(c.created_at).getTime(),
    }))

    return [...wos, ...comments]
        .sort((a, b) => b.sortKey - a.sortKey)
        .slice(0, 8)
})

// ── Novedades ─────────────────────────────────────────────────────────────────
const SEVERITY_CFG = {
    critical: { bg: 'bg-red-50', color: 'text-red-600', badge: 'bg-red-100 text-red-700', label: 'Crítica' },
    high:     { bg: 'bg-orange-50', color: 'text-orange-600', badge: 'bg-orange-100 text-orange-700', label: 'Alta' },
}
const PLAN_ICON = '<path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>'

const novedadesItems = computed(() => {
    const alerts = (novedadesData.value.critical_alerts ?? []).map(a => {
        const cfg = SEVERITY_CFG[a.severity] ?? SEVERITY_CFG.high
        return {
            id: `a-${a.id}`,
            icon: ALERT_ICON,
            iconBg: cfg.bg,
            iconColor: cfg.color,
            title: a.message,
            subtitle: a.equipment_code ? `Equipo ${a.equipment_code}` : 'Sin equipo',
            badge: cfg.label,
            badgeCls: cfg.badge,
        }
    })

    const plans = (novedadesData.value.upcoming_plans ?? []).map(p => {
        const overdue = p.is_overdue
        return {
            id: `p-${p.id}`,
            icon: PLAN_ICON,
            iconBg: overdue ? 'bg-red-50' : 'bg-amber-50',
            iconColor: overdue ? 'text-red-600' : 'text-amber-600',
            title: p.name,
            subtitle: `${p.plan_number}${p.equipment_code ? ` · ${p.equipment_code}` : ''}`,
            badge: overdue ? 'Vencido' : formatDueDate(p.next_due_at),
            badgeCls: overdue ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700',
        }
    })

    return [...alerts, ...plans]
})

function formatDueDate(iso) {
    if (!iso) { return '—' }
    const d = new Date(iso)
    const today = new Date()
    const diff = Math.ceil((d - today) / 86400000)
    if (diff <= 0) { return 'Hoy' }
    if (diff === 1) { return 'Mañana' }
    return `En ${diff}d`
}

// ── Accesos rápidos ───────────────────────────────────────────────────────────
const quickActions = [
    {
        label: 'Crear OT',
        icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',
        bg: 'bg-blue-100',
        color: 'text-blue-700',
        to: { name: 'ops.ordenes' },
    },
    {
        label: 'Registrar solicitud',
        icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="2" ry="2"/><line x1="9" y1="12" x2="15" y2="12"/><line x1="9" y1="16" x2="13" y2="16"/>',
        bg: 'bg-amber-100',
        color: 'text-amber-700',
        to: { name: 'ops.solicitudes' },
    },
    {
        label: 'Escanear QR',
        icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 013.75 9.375v-4.5zM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 01-1.125-1.125v-4.5zM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0113.5 9.375v-4.5z"/><path d="M6.75 6.75h.75v.75h-.75v-.75zM6.75 16.5h.75v.75h-.75V16.5zM16.5 6.75h.75v.75h-.75v-.75zM13.5 13.5h.75v.75h-.75v-.75zM13.5 19.5h.75v.75h-.75v-.75zM19.5 13.5h.75v.75h-.75v-.75zM19.5 19.5h.75v.75h-.75v-.75zM16.5 16.5h.75v.75h-.75v-.75z"/>',
        bg: 'bg-emerald-100',
        color: 'text-emerald-700',
        to: { name: 'ops.equipos' },
    },
    {
        label: 'Nuevo equipo',
        icon: '<path stroke-linecap="round" stroke-linejoin="round" d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>',
        bg: 'bg-slate-100',
        color: 'text-slate-700',
        to: { name: 'ops.equipos' },
    },
]

// ── Data loading ──────────────────────────────────────────────────────────────
onMounted(async () => {
    updateClock()
    clockTimer = setInterval(updateClock, 30000)

    const [summaryRes, activityRes, novedadesRes] = await Promise.allSettled([
        api.get('dashboard/summary'),
        api.get('dashboard/activity'),
        api.get('dashboard/novedades'),
    ])

    if (summaryRes.status === 'fulfilled' && summaryRes.value) {
        summary.value = summaryRes.value
    }
    loadingStats.value = false

    if (activityRes.status === 'fulfilled' && activityRes.value) {
        activityData.value = activityRes.value
    }
    loadingActivity.value = false

    if (novedadesRes.status === 'fulfilled' && novedadesRes.value) {
        novedadesData.value = novedadesRes.value
    }
    loadingNovedades.value = false

    // Institutional images (non-blocking, best-effort)
    try {
        const imgs = await api.get('dashboard/images')
        if (imgs?.data?.length > 0) {
            institutionalImages.value = imgs.data
            startCarousel()
        }
    } catch { /* silent — no images yet */ }
})

onUnmounted(() => {
    clearInterval(clockTimer)
    clearInterval(carouselTimer)
})
</script>
