<template>
    <div class="p-5 lg:p-8 max-w-6xl mx-auto">

        <!-- Header -->
        <div class="flex items-start justify-between gap-4 mb-6">
            <div>
                <h1 class="text-xl font-bold text-gray-900">Reportes de Confiabilidad</h1>
                <p v-if="!loading && kpis.length" class="text-sm text-gray-500 mt-0.5">
                    {{ kpis.length }} equipo{{ kpis.length !== 1 ? 's' : '' }} · Datos al {{ today }}
                </p>
            </div>
            <div v-if="!loading && kpis.length" class="flex items-center gap-2 shrink-0">
                <button
                    @click="exportCsv"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-gray-200 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition-colors"
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                    </svg>
                    CSV
                </button>
                <button
                    @click="exportPdf"
                    :disabled="downloadingPdf"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-gray-200 text-xs font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-50 transition-colors"
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z"/>
                    </svg>
                    {{ downloadingPdf ? 'Generando…' : 'PDF' }}
                </button>
            </div>
        </div>

        <!-- Loading skeleton -->
        <div v-if="loading" class="space-y-6">
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                <div v-for="i in 4" :key="i" class="skeleton h-20 rounded-2xl" />
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div v-for="i in 4" :key="i" class="skeleton h-72 rounded-2xl" />
            </div>
            <div class="skeleton h-72 rounded-2xl" />
        </div>

        <template v-else-if="kpis.length">

            <!-- KPI fleet summary strip -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-8">
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
                    <p class="text-xs font-bold uppercase tracking-wider text-emerald-600 mb-1">Disponibilidad prom.</p>
                    <p class="text-2xl font-bold text-gray-900">
                        {{ avgAvailability != null ? avgAvailability.toFixed(1) + '%' : '—' }}
                    </p>
                    <div v-if="avgAvailability != null" class="mt-1.5 h-1 bg-gray-100 rounded-full overflow-hidden">
                        <div
                            class="h-full rounded-full transition-all duration-700"
                            :class="availBarClass(avgAvailability)"
                            :style="{ width: animated ? avgAvailability + '%' : '0%' }"
                        />
                    </div>
                </div>
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
                    <p class="text-xs font-bold uppercase tracking-wider text-blue-600 mb-1">MTBF prom.</p>
                    <p class="text-2xl font-bold text-gray-900">
                        {{ avgMtbf != null ? avgMtbf.toFixed(0) + 'h' : '—' }}
                    </p>
                    <p class="text-xs text-gray-500 mt-1">Tiempo medio entre fallas</p>
                </div>
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
                    <p class="text-xs font-bold uppercase tracking-wider text-amber-600 mb-1">MTTR prom.</p>
                    <p class="text-2xl font-bold text-gray-900">
                        {{ avgMttr != null ? avgMttr.toFixed(1) + 'h' : '—' }}
                    </p>
                    <p class="text-xs text-gray-500 mt-1">Tiempo medio de reparación</p>
                </div>
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
                    <p class="text-xs font-bold uppercase tracking-wider text-red-600 mb-1">Fallas totales</p>
                    <p class="text-2xl font-bold text-gray-900">{{ totalFailures }}</p>
                    <p class="text-xs text-gray-500 mt-1">{{ kpis.length }} equipos evaluados</p>
                </div>
            </div>

            <!-- 2-column chart grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

                <!-- Disponibilidad -->
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
                    <div class="px-5 pt-4 pb-3 border-b border-gray-50">
                        <h3 class="text-sm font-bold text-gray-900">Disponibilidad por equipo</h3>
                        <p class="text-xs text-gray-500 mt-0.5">% — de mayor a menor · 🟢 ≥90% · 🟡 ≥70% · 🔴 &lt;70%</p>
                    </div>
                    <div class="px-5 py-4 space-y-2">
                        <div
                            v-for="kpi in sortedByAvailability.slice(0, 12)"
                            :key="kpi.id"
                            class="flex items-center gap-3"
                        >
                            <div class="w-28 text-xs text-gray-500 truncate text-right shrink-0" :title="kpi.equipment?.name">
                                {{ kpi.equipment?.name ?? kpi.equipment?.code ?? '—' }}
                            </div>
                            <div class="flex-1 h-5 bg-gray-100 rounded-md overflow-hidden">
                                <div
                                    class="h-full rounded-md transition-all duration-700"
                                    :class="availBarClass(kpi.availability_percentage)"
                                    :style="{ width: animated ? (kpi.availability_percentage ?? 0) + '%' : '0%' }"
                                />
                            </div>
                            <div class="w-12 text-xs font-bold text-right shrink-0" :class="availTextClass(kpi.availability_percentage)">
                                {{ kpi.availability_percentage != null ? kpi.availability_percentage.toFixed(1) + '%' : '—' }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Fallas por equipo -->
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
                    <div class="px-5 pt-4 pb-3 border-b border-gray-50">
                        <h3 class="text-sm font-bold text-gray-900">Fallas por equipo</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Cantidad — de mayor a menor</p>
                    </div>
                    <div class="px-5 py-4 space-y-2">
                        <div
                            v-for="kpi in sortedByFailures.slice(0, 12)"
                            :key="kpi.id"
                            class="flex items-center gap-3"
                        >
                            <div class="w-28 text-xs text-gray-500 truncate text-right shrink-0" :title="kpi.equipment?.name">
                                {{ kpi.equipment?.name ?? kpi.equipment?.code ?? '—' }}
                            </div>
                            <div class="flex-1 h-5 bg-gray-100 rounded-md overflow-hidden">
                                <div
                                    class="h-full rounded-md transition-all duration-700"
                                    :class="failureBarClass(kpi.failure_count)"
                                    :style="{ width: animated && maxFailures > 0 ? (((kpi.failure_count ?? 0) / maxFailures) * 100) + '%' : '0%' }"
                                />
                            </div>
                            <div class="w-12 text-xs font-bold text-right shrink-0 text-gray-700">
                                {{ kpi.failure_count ?? '—' }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- MTBF -->
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
                    <div class="px-5 pt-4 pb-3 border-b border-gray-50">
                        <h3 class="text-sm font-bold text-gray-900">MTBF por equipo</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Horas — mayor es mejor confiabilidad</p>
                    </div>
                    <div class="px-5 py-4 space-y-2">
                        <div
                            v-for="kpi in sortedByMtbf.slice(0, 12)"
                            :key="kpi.id"
                            class="flex items-center gap-3"
                        >
                            <div class="w-28 text-xs text-gray-500 truncate text-right shrink-0" :title="kpi.equipment?.name">
                                {{ kpi.equipment?.name ?? kpi.equipment?.code ?? '—' }}
                            </div>
                            <div class="flex-1 h-5 bg-gray-100 rounded-md overflow-hidden">
                                <div
                                    class="h-full rounded-md bg-blue-400 transition-all duration-700"
                                    :style="{ width: animated && maxMtbf > 0 ? (((kpi.mtbf_hours ?? 0) / maxMtbf) * 100) + '%' : '0%' }"
                                />
                            </div>
                            <div class="w-16 text-xs font-bold text-right shrink-0 text-blue-700">
                                {{ kpi.mtbf_hours != null ? kpi.mtbf_hours.toFixed(0) + 'h' : '—' }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- MTTR -->
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
                    <div class="px-5 pt-4 pb-3 border-b border-gray-50">
                        <h3 class="text-sm font-bold text-gray-900">MTTR por equipo</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Horas — menor es reparación más rápida</p>
                    </div>
                    <div class="px-5 py-4 space-y-2">
                        <div
                            v-for="kpi in sortedByMttr.slice(0, 12)"
                            :key="kpi.id"
                            class="flex items-center gap-3"
                        >
                            <div class="w-28 text-xs text-gray-500 truncate text-right shrink-0" :title="kpi.equipment?.name">
                                {{ kpi.equipment?.name ?? kpi.equipment?.code ?? '—' }}
                            </div>
                            <div class="flex-1 h-5 bg-gray-100 rounded-md overflow-hidden">
                                <div
                                    class="h-full rounded-md transition-all duration-700"
                                    :class="mttrBarClass(kpi.mttr_hours)"
                                    :style="{ width: animated && maxMttr > 0 ? (((kpi.mttr_hours ?? 0) / maxMttr) * 100) + '%' : '0%' }"
                                />
                            </div>
                            <div class="w-16 text-xs font-bold text-right shrink-0 text-gray-700">
                                {{ kpi.mttr_hours != null ? kpi.mttr_hours.toFixed(1) + 'h' : '—' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pareto chart — full width -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
                <div class="px-5 pt-4 pb-3 border-b border-gray-50 flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-sm font-bold text-gray-900">Análisis de Pareto — Fallas por equipo</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Los equipos que cruzan el umbral del 80% concentran la mayoría de las fallas (ley del 80/20)</p>
                    </div>
                    <div class="flex items-center gap-4 text-xs text-gray-500 shrink-0">
                        <span class="flex items-center gap-1.5">
                            <span class="w-3 h-3 rounded bg-red-400 shrink-0" />
                            Fallas
                        </span>
                        <span class="flex items-center gap-1.5">
                            <span class="inline-block w-5 border-t-2 border-dashed border-blue-400 shrink-0" />
                            Acum. %
                        </span>
                    </div>
                </div>

                <div v-if="paretoBarData.length" class="px-5 py-5 overflow-x-auto">
                    <svg
                        :viewBox="`0 0 ${svgW} ${svgH}`"
                        :style="{ minWidth: Math.max(paretoBarData.length * 40, 400) + 'px' }"
                        style="overflow: visible; width: 100%"
                    >
                        <!-- Left Y axis -->
                        <line :x1="mL" :y1="mT" :x2="mL" :y2="mT + cH" stroke="#e5e7eb" stroke-width="1"/>
                        <!-- X axis -->
                        <line :x1="mL" :y1="mT + cH" :x2="mL + cW" :y2="mT + cH" stroke="#e5e7eb" stroke-width="1"/>

                        <!-- Y grid ticks + labels -->
                        <g v-for="tick in yTicks" :key="tick.value">
                            <line
                                :x1="mL"
                                :y1="tick.y"
                                :x2="mL + cW"
                                :y2="tick.y"
                                stroke="#f3f4f6"
                                stroke-width="1"
                            />
                            <text
                                :x="mL - 6"
                                :y="tick.y + 4"
                                text-anchor="end"
                                font-size="12"
                                fill="#6b7280"
                            >{{ tick.label }}</text>
                        </g>

                        <!-- 80% threshold line -->
                        <line
                            :x1="mL"
                            :y1="mT + cH * 0.2"
                            :x2="mL + cW"
                            :y2="mT + cH * 0.2"
                            stroke="#3b82f6"
                            stroke-width="1.5"
                            stroke-dasharray="5,4"
                        />
                        <text
                            :x="mL + cW + 5"
                            :y="mT + cH * 0.2 + 4"
                            font-size="12"
                            fill="#3b82f6"
                            font-weight="600"
                        >80%</text>

                        <!-- Bars -->
                        <rect
                            v-for="d in paretoBarData"
                            :key="d.id"
                            :x="d.x"
                            :y="d.y"
                            :width="d.bw"
                            :height="d.h"
                            rx="2"
                            fill="#f87171"
                        />

                        <!-- Cumulative % line -->
                        <polyline
                            v-if="cumulativeLinePoints"
                            :points="cumulativeLinePoints"
                            fill="none"
                            stroke="#3b82f6"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />

                        <!-- Cumulative dots -->
                        <circle
                            v-for="d in paretoBarData"
                            :key="`dot-${d.id}`"
                            :cx="d.cx"
                            :cy="d.pctY"
                            r="3"
                            fill="white"
                            stroke="#3b82f6"
                            stroke-width="1.5"
                        />

                        <!-- % labels at top of each dot (only if space) -->
                        <text
                            v-if="paretoBarData.length <= 10"
                            v-for="d in paretoBarData"
                            :key="`pct-${d.id}`"
                            :x="d.cx"
                            :y="d.pctY - 7"
                            text-anchor="middle"
                            font-size="12"
                            fill="#3b82f6"
                        >{{ d.cumulativePct.toFixed(0) }}%</text>

                        <!-- X axis labels (rotated) -->
                        <text
                            v-for="d in paretoBarData"
                            :key="`label-${d.id}`"
                            :x="d.cx"
                            :y="mT + cH + 12"
                            text-anchor="end"
                            font-size="11"
                            fill="#6b7280"
                            :transform="`rotate(-40, ${d.cx}, ${mT + cH + 12})`"
                        >{{ (d.equipment?.code ?? d.equipment?.name ?? '').slice(0, 14) }}</text>

                        <!-- Count labels on bars -->
                        <text
                            v-for="d in paretoBarData"
                            :key="`count-${d.id}`"
                            :x="d.cx"
                            :y="d.h > 14 ? d.y + 12 : d.y - 4"
                            text-anchor="middle"
                            font-size="11"
                            :fill="d.h > 14 ? 'white' : '#6b7280'"
                            font-weight="600"
                        >{{ d.failure_count }}</text>
                    </svg>
                </div>

                <div v-else class="px-5 py-8 text-center text-xs text-gray-500">
                    No hay datos de fallas registrados para el análisis de Pareto
                </div>
            </div>

        </template>

        <!-- Empty state -->
        <EmptyState
            v-else
            icon="chartBar"
            title="Sin datos de confiabilidad"
            subtitle="Los KPIs se generan al registrar fallas y tiempo de inactividad en los equipos."
        />

    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useApi } from '../composables/useApi.js'
import EmptyState from '../components/EmptyState.vue'

const api = useApi()
const kpis = ref([])
const loading = ref(true)
const animated = ref(false)
const downloadingPdf = ref(false)

const today = new Date().toLocaleDateString('es', { day: '2-digit', month: 'long', year: 'numeric' })

// ── SVG Pareto constants ───────────────────────────────────────────────────────

const svgW = 600
const svgH = 260
const mT = 20   // margin top
const mR = 48   // margin right (for 80% label)
const mB = 82   // margin bottom (for rotated labels)
const mL = 42   // margin left (for count labels)
const cW = svgW - mL - mR   // 510
const cH = svgH - mT - mB   // 158

// ── Fleet aggregates ───────────────────────────────────────────────────────────

const avgAvailability = computed(() => {
    const valid = kpis.value.filter(k => k.availability_percentage != null)
    if (!valid.length) { return null }
    return valid.reduce((s, k) => s + k.availability_percentage, 0) / valid.length
})

const avgMtbf = computed(() => {
    const valid = kpis.value.filter(k => k.mtbf_hours != null && k.mtbf_hours > 0)
    if (!valid.length) { return null }
    return valid.reduce((s, k) => s + k.mtbf_hours, 0) / valid.length
})

const avgMttr = computed(() => {
    const valid = kpis.value.filter(k => k.mttr_hours != null && k.mttr_hours > 0)
    if (!valid.length) { return null }
    return valid.reduce((s, k) => s + k.mttr_hours, 0) / valid.length
})

const totalFailures = computed(() => kpis.value.reduce((s, k) => s + (k.failure_count ?? 0), 0))

// ── Sorted lists ──────────────────────────────────────────────────────────────

const sortedByAvailability = computed(() =>
    [...kpis.value]
        .filter(k => k.availability_percentage != null)
        .sort((a, b) => b.availability_percentage - a.availability_percentage)
)

const sortedByFailures = computed(() =>
    [...kpis.value]
        .filter(k => (k.failure_count ?? 0) > 0)
        .sort((a, b) => b.failure_count - a.failure_count)
)

const sortedByMtbf = computed(() =>
    [...kpis.value]
        .filter(k => k.mtbf_hours != null && k.mtbf_hours > 0)
        .sort((a, b) => b.mtbf_hours - a.mtbf_hours)
)

const sortedByMttr = computed(() =>
    [...kpis.value]
        .filter(k => k.mttr_hours != null && k.mttr_hours > 0)
        .sort((a, b) => a.mttr_hours - b.mttr_hours)
)

const maxFailures = computed(() => sortedByFailures.value[0]?.failure_count ?? 1)
const maxMtbf = computed(() => sortedByMtbf.value[0]?.mtbf_hours ?? 1)
const maxMttr = computed(() => Math.max(...sortedByMttr.value.map(k => k.mttr_hours ?? 0), 1))

// ── Pareto data ────────────────────────────────────────────────────────────────

const paretoBase = computed(() => {
    const sorted = sortedByFailures.value.slice(0, 15)
    const total = sorted.reduce((s, k) => s + (k.failure_count ?? 0), 0)
    let cumulative = 0
    return sorted.map(k => {
        cumulative += k.failure_count ?? 0
        return { ...k, cumulativePct: total > 0 ? (cumulative / total) * 100 : 0 }
    })
})

const paretoBarData = computed(() => {
    const items = paretoBase.value
    if (!items.length) { return [] }
    const n = items.length
    const slot = cW / n
    const bw = Math.max(slot * 0.65, 8)
    const maxCount = items[0]?.failure_count ?? 1

    return items.map((item, i) => {
        const x = mL + i * slot + (slot - bw) / 2
        const count = item.failure_count ?? 0
        const h = maxCount > 0 ? (count / maxCount) * cH : 0
        const y = mT + cH - h
        const cx = x + bw / 2
        const pctY = mT + cH * (1 - item.cumulativePct / 100)
        return { ...item, x, y, h, bw, cx, pctY }
    })
})

const cumulativeLinePoints = computed(() =>
    paretoBarData.value.map(d => `${d.cx},${d.pctY}`).join(' ')
)

const yTicks = computed(() => {
    const max = paretoBase.value[0]?.failure_count ?? 0
    if (!max) { return [] }
    const step = max <= 4 ? 1 : max <= 10 ? 2 : max <= 25 ? 5 : Math.ceil(max / 5)
    const ticks = []
    for (let v = step; v <= max; v += step) {
        ticks.push({ value: v, label: String(v), y: mT + cH - (v / max) * cH })
    }
    return ticks
})

// ── Color helpers ─────────────────────────────────────────────────────────────

const availBarClass = (pct) => {
    if (pct == null) { return 'bg-gray-300' }
    if (pct >= 90) { return 'bg-emerald-500' }
    if (pct >= 70) { return 'bg-amber-400' }
    return 'bg-red-400'
}

const availTextClass = (pct) => {
    if (pct == null) { return 'text-gray-500' }
    if (pct >= 90) { return 'text-emerald-700' }
    if (pct >= 70) { return 'text-amber-600' }
    return 'text-red-600'
}

const failureBarClass = (count) => {
    const ratio = maxFailures.value > 0 ? (count ?? 0) / maxFailures.value : 0
    if (ratio >= 0.7) { return 'bg-red-400' }
    if (ratio >= 0.35) { return 'bg-amber-400' }
    return 'bg-gray-400'
}

const mttrBarClass = (hours) => {
    const ratio = maxMttr.value > 0 ? (hours ?? 0) / maxMttr.value : 0
    if (ratio >= 0.7) { return 'bg-red-400' }
    if (ratio >= 0.35) { return 'bg-amber-400' }
    return 'bg-emerald-400'
}

// ── Export ────────────────────────────────────────────────────────────────────

function exportCsv() {
    const headers = ['Equipo', 'Código', 'Disponibilidad (%)', 'MTBF (h)', 'MTTR (h)', 'Fallas', 'Tiempo inactivo (h)']
    const rows = kpis.value.map(k => [
        k.equipment?.name ?? '',
        k.equipment?.code ?? '',
        k.availability_percentage?.toFixed(2) ?? '',
        k.mtbf_hours?.toFixed(1) ?? '',
        k.mttr_hours?.toFixed(1) ?? '',
        k.failure_count ?? 0,
        k.downtime_hours?.toFixed(1) ?? '',
    ])
    const csv = [headers, ...rows]
        .map(r => r.map(c => `"${String(c).replace(/"/g, '""')}"`).join(','))
        .join('\n')
    const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `kpis-confiabilidad-${new Date().toISOString().slice(0, 10)}.csv`
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
    URL.revokeObjectURL(url)
}

async function exportPdf() {
    if (downloadingPdf.value) { return }
    downloadingPdf.value = true
    try {
        await api.download('reports/reliability', `confiabilidad-${new Date().toISOString().slice(0, 10)}.pdf`)
    } catch { /* surfaced by the disabled state resetting */ } finally {
        downloadingPdf.value = false
    }
}

// ── Data loading ──────────────────────────────────────────────────────────────

onMounted(async () => {
    try {
        const res = await api.get('reliability/kpis?per_page=200')
        kpis.value = res?.data ?? []
    } catch { /* silent */ } finally {
        loading.value = false
        setTimeout(() => { animated.value = true }, 80)
    }
})
</script>
