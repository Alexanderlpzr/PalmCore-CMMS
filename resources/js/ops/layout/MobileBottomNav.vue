<template>
    <nav class="flex items-stretch bg-white border-t border-gray-200 pb-safe shrink-0">
        <NavTab v-for="tab in tabs" :key="tab.to" :tab="tab" />
    </nav>
</template>

<script setup>
import { defineComponent, h, computed } from 'vue'
import { RouterLink, useRoute } from 'vue-router'

const route = useRoute()

const tabs = [
    {
        to: 'ops.dashboard',
        label: 'Inicio',
        icon: `<path stroke-linecap="round" stroke-linejoin="round" d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline stroke-linecap="round" stroke-linejoin="round" points="9,22 9,12 15,12 15,22"/>`,
    },
    {
        to: 'ops.ordenes',
        label: 'OTs',
        icon: `<path stroke-linecap="round" stroke-linejoin="round" d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>`,
    },
    {
        to: 'ops.solicitudes',
        label: 'Solicitudes',
        icon: `<path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="2" ry="2"/>`,
    },
    {
        to: 'ops.equipos',
        label: 'Equipos',
        icon: `<path stroke-linecap="round" stroke-linejoin="round" d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline stroke-linecap="round" stroke-linejoin="round" points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>`,
    },
]

const NavTab = defineComponent({
    props: { tab: Object },
    setup(props) {
        const isActive = computed(() => route.name === props.tab.to)
        return () => h(RouterLink,
            {
                to: { name: props.tab.to },
                class: `flex flex-col items-center justify-center gap-1 flex-1 py-2 px-1 text-[10px] font-medium transition-colors ${isActive.value ? 'text-emerald-600' : 'text-gray-400'}`,
            },
            () => [
                h('svg', {
                    class: 'w-5 h-5',
                    fill: 'none',
                    viewBox: '0 0 24 24',
                    stroke: 'currentColor',
                    'stroke-width': '2',
                    'stroke-linecap': 'round',
                    'stroke-linejoin': 'round',
                    innerHTML: props.tab.icon,
                }),
                h('span', props.tab.label),
            ]
        )
    },
})
</script>
