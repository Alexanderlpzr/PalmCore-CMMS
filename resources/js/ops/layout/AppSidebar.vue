<template>
    <aside class="flex flex-col w-60 h-full bg-slate-900 border-r border-slate-800 select-none shrink-0">

        <!-- Logo + Tenant -->
        <div class="flex items-center gap-3 px-4 h-16 border-b border-slate-800 shrink-0">
            <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-emerald-600 shrink-0">
                <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 004.486-6.336l-3.276 3.277a3.004 3.004 0 01-2.25-2.25l3.276-3.276a4.5 4.5 0 00-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437l1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008z"/>
                </svg>
            </div>
            <div class="min-w-0">
                <p class="text-sm font-bold text-white leading-none tracking-wide">FRONDA</p>
                <p class="text-[10px] text-slate-400 mt-0.5 truncate leading-none">{{ auth.tenantName ?? 'CMMS' }}</p>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 overflow-y-auto py-3 px-2 space-y-0.5">
            <!-- Dashboard -->
            <NavItem :item="{ name: 'Dashboard', to: 'ops.dashboard', icon: icons.home }" />

            <!-- Mantenimiento -->
            <NavGroup label="Mantenimiento">
                <NavItem :item="{ name: 'Solicitudes', to: 'ops.solicitudes', icon: icons.clipboard }" />
                <NavItem :item="{ name: 'Órdenes de trabajo', to: 'ops.ordenes', icon: icons.tool }" />
                <NavItem :item="{ name: 'Preventivos', to: 'ops.preventivos', icon: icons.calendar }" />
            </NavGroup>

            <!-- Activos -->
            <NavGroup label="Activos">
                <NavItem :item="{ name: 'Equipos', to: 'ops.equipos', icon: icons.box }" />
            </NavGroup>

            <!-- Inventario -->
            <NavGroup label="Inventario">
                <NavItem :item="{ name: 'Repuestos', to: 'ops.repuestos', icon: icons.package }" />
                <NavItem :item="{ name: 'Almacenes', to: 'ops.almacenes', icon: icons.warehouse }" />
            </NavGroup>

            <!-- Análisis -->
            <NavGroup label="Análisis">
                <NavItem :item="{ name: 'KPIs', to: 'ops.kpis', icon: icons.chartBar }" />
                <NavItem :item="{ name: 'Reportes', to: 'ops.reportes', icon: icons.fileText }" />
            </NavGroup>

            <div class="my-2 border-t border-slate-800" />

            <NavItem :item="{ name: 'Alertas', to: 'ops.alertas', icon: icons.bell }" />
        </nav>

        <!-- User footer -->
        <div class="border-t border-slate-800 px-3 py-3 shrink-0">
            <div class="flex items-center gap-2.5">
                <div class="flex items-center justify-center w-7 h-7 rounded-full bg-emerald-700 text-[10px] font-bold text-white shrink-0">
                    {{ auth.userInitials }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-slate-200 truncate leading-none">{{ auth.userName ?? auth.userEmail }}</p>
                    <p class="text-[10px] text-slate-500 mt-0.5 truncate leading-none">{{ auth.userEmail }}</p>
                </div>
                <button
                    @click="handleLogout"
                    title="Cerrar sesión"
                    class="p-1.5 rounded-lg text-slate-500 hover:text-slate-300 hover:bg-slate-800 transition-colors"
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75"/>
                    </svg>
                </button>
            </div>
        </div>
    </aside>
</template>

<script setup>
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth.js'
import NavItem from './NavItem.vue'
import NavGroup from './NavGroup.vue'

const emit = defineEmits(['close'])
const router = useRouter()
const auth = useAuthStore()

async function handleLogout() {
    await auth.logout()
    router.push({ name: 'ops.login' })
}

const icons = {
    home: `<path stroke-linecap="round" stroke-linejoin="round" d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline stroke-linecap="round" stroke-linejoin="round" points="9,22 9,12 15,12 15,22"/>`,
    clipboard: `<path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="2" ry="2"/><line x1="9" y1="12" x2="15" y2="12"/><line x1="9" y1="16" x2="13" y2="16"/>`,
    tool: `<path stroke-linecap="round" stroke-linejoin="round" d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>`,
    calendar: `<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>`,
    box: `<path stroke-linecap="round" stroke-linejoin="round" d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline stroke-linecap="round" stroke-linejoin="round" points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>`,
    package: `<path stroke-linecap="round" stroke-linejoin="round" d="M16.5 9.4 7.5 4.21M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline stroke-linecap="round" stroke-linejoin="round" points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>`,
    warehouse: `<path stroke-linecap="round" stroke-linejoin="round" d="M3 9.5 12 3l9 6.5V20a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9.5z"/><path stroke-linecap="round" stroke-linejoin="round" d="M9 21V12h6v9"/>`,
    chartBar: `<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>`,
    fileText: `<path stroke-linecap="round" stroke-linejoin="round" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline stroke-linecap="round" stroke-linejoin="round" points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>`,
    bell: `<path stroke-linecap="round" stroke-linejoin="round" d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path stroke-linecap="round" stroke-linejoin="round" d="M13.73 21a2 2 0 0 1-3.46 0"/>`,
}
</script>
