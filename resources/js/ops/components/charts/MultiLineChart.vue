<template>
    <div class="relative">
        <!-- Y-axis labels (0-100%) -->
        <div class="flex">
            <div class="w-8 shrink-0 flex flex-col justify-between text-right pr-1.5 pb-6">
                <span class="text-[10px] text-gray-400">100%</span>
                <span class="text-[10px] text-gray-400">50%</span>
                <span class="text-[10px] text-gray-400">0%</span>
            </div>

            <!-- Chart area -->
            <div class="flex-1 relative">
                <svg
                    class="w-full overflow-visible"
                    :viewBox="`0 0 ${W} ${H}`"
                    preserveAspectRatio="none"
                    :height="chartHeightPx"
                >
                    <!-- Grid lines -->
                    <line
                        v-for="y in gridYs"
                        :key="y"
                        x1="0" :y1="y" :x2="W" :y2="y"
                        stroke="#f3f4f6" stroke-width="1"
                    />

                    <!-- Series lines -->
                    <g v-for="s in normalizedSeries" :key="s.label">
                        <polyline
                            :points="s.points"
                            fill="none"
                            :stroke="s.stroke"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            opacity="0.9"
                        />
                        <!-- Dots -->
                        <circle
                            v-for="(pt, i) in s.coords"
                            :key="i"
                            :cx="pt.x"
                            :cy="pt.y"
                            r="3"
                            :fill="s.stroke"
                            class="cursor-pointer"
                            @mouseenter="showTip($event, s, i)"
                            @mouseleave="hideTip"
                        />
                    </g>
                </svg>

                <!-- X-axis month labels -->
                <div class="flex justify-between mt-1 pb-1">
                    <span
                        v-for="(m, i) in visibleMonths"
                        :key="i"
                        class="text-[10px] text-gray-400 text-center"
                        :style="{ width: (100 / months.length) + '%' }"
                    >{{ m }}</span>
                </div>
            </div>
        </div>

        <!-- Legend -->
        <div class="flex flex-wrap gap-x-4 gap-y-1 mt-3 pl-8">
            <div v-for="s in series" :key="s.label" class="flex items-center gap-1.5">
                <span class="w-3 h-0.5 rounded-full inline-block" :style="{ backgroundColor: resolveColor(s.color) }" />
                <span class="text-xs text-gray-500">{{ s.label }}</span>
            </div>
        </div>

        <!-- Tooltip -->
        <div
            v-if="tip.visible"
            class="pointer-events-none fixed z-50 bg-gray-900 text-white text-xs rounded-lg px-3 py-2 shadow-lg"
            :style="{ top: tip.y + 'px', left: tip.x + 'px', transform: 'translate(-50%, -110%)' }"
        >
            <p class="font-semibold mb-1">{{ tip.month }}</p>
            <div v-for="row in tip.rows" :key="row.label" class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full shrink-0" :style="{ backgroundColor: resolveColor(row.color) }" />
                <span>{{ row.label }}: <strong>{{ row.value }}</strong></span>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive } from 'vue'

const props = defineProps({
    series: { type: Array, default: () => [] },
    months: { type: Array, default: () => [] },
    chartHeightPx: { type: Number, default: 180 },
})

const W = 400
const H = 100
const gridYs = [0, 25, 50, 75, 100]

const colorMap = {
    emerald: '#059669',
    indigo: '#4f46e5',
    amber: '#d97706',
    slate: '#475569',
    red: '#dc2626',
    blue: '#2563eb',
    violet: '#7c3aed',
}

function resolveColor(color) {
    if (!color) { return '#6b7280' }
    if (color.startsWith('#')) { return color }
    return colorMap[color] ?? color
}

const normalizedSeries = computed(() => {
    const count = props.months.length || 1
    const step = W / (count - 1 || 1)

    return props.series.map(s => {
        const values = s.data.map(d => d.y ?? 0)
        const min = Math.min(...values)
        const max = Math.max(...values)
        const range = max - min || 1

        const coords = s.data.map((d, i) => ({
            x: count === 1 ? W / 2 : i * step,
            y: H - ((d.y - min) / range) * H,
            raw: d.y,
        }))

        return {
            label: s.label,
            color: s.color,
            stroke: resolveColor(s.color),
            coords,
            points: coords.map(c => `${c.x},${c.y}`).join(' '),
        }
    })
})

const visibleMonths = computed(() => {
    if (props.months.length <= 6) { return props.months }
    return props.months.map((m, i) => (i % 2 === 0 ? m : ''))
})

const tip = reactive({ visible: false, x: 0, y: 0, month: '', rows: [] })

function showTip(event, seriesItem, idx) {
    tip.month = props.months[idx] ?? ''
    tip.rows = props.series.map(s => ({
        label: s.label,
        color: s.color,
        value: formatRaw(s.data[idx]?.y),
    }))
    tip.x = event.clientX
    tip.y = event.clientY
    tip.visible = true
}

function hideTip() {
    tip.visible = false
}

function formatRaw(value) {
    if (value == null) { return '—' }
    return Number.isInteger(value)
        ? value.toLocaleString('es')
        : value.toLocaleString('es', { minimumFractionDigits: 1, maximumFractionDigits: 1 })
}
</script>
