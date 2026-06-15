<template>
    <nav
        class="shrink-0 bg-zinc-900 border-t border-zinc-800 z-20"
        :style="{ paddingBottom: 'env(safe-area-inset-bottom)' }"
    >
        <div class="flex h-16">
            <RouterLink
                v-for="tab in tabs"
                :key="tab.to"
                :to="tab.to"
                class="flex-1 flex flex-col items-center justify-center gap-1 transition"
                :class="[
                    isActive(tab.to)
                        ? 'text-amber-400'
                        : 'text-zinc-400 hover:text-zinc-300'
                ]"
            >
                <component :is="tab.icon" class="w-6 h-6" />
                <span class="text-xs font-medium">{{ tab.label }}</span>
            </RouterLink>
        </div>
    </nav>
</template>

<script setup>
import { computed, h } from 'vue'
import { RouterLink, useRoute } from 'vue-router'

const route = useRoute()

function isActive(to) {
    return route.path === to || route.path.startsWith(to + '/')
}

const IconHome = {
    render: () => h('svg', { fill: 'none', stroke: 'currentColor', 'stroke-width': '1.75', viewBox: '0 0 24 24' }, [
        h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', d: 'M2.25 12l8.954-8.955a1.126 1.126 0 0 1 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25' }),
    ]),
}

const IconClipboard = {
    render: () => h('svg', { fill: 'none', stroke: 'currentColor', 'stroke-width': '1.75', viewBox: '0 0 24 24' }, [
        h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', d: 'M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2' }),
    ]),
}

const IconQr = {
    render: () => h('svg', { fill: 'none', stroke: 'currentColor', 'stroke-width': '1.75', viewBox: '0 0 24 24' }, [
        h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', d: 'M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 3.75 9.375v-4.5Z' }),
        h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', d: 'M3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5Z' }),
        h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', d: 'M13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 13.5 9.375v-4.5Z' }),
        h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', d: 'M13.5 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5Z' }),
    ]),
}

const tabs = computed(() => [
    { to: '/mobile/dashboard', label: 'Inicio', icon: IconHome },
    { to: '/mobile/work-orders', label: 'Mis OTs', icon: IconClipboard },
    { to: '/mobile/scan', label: 'Escanear', icon: IconQr },
])
</script>
