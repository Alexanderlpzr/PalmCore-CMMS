<template>
    <aside class="flex flex-col w-60 h-full bg-slate-900 border-r border-slate-800 select-none shrink-0">

        <ImpersonationBanner />

        <!-- Logo + Tenant -->
        <div class="flex items-center gap-3 px-4 h-16 border-b border-slate-800 shrink-0">
            <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-emerald-600 shrink-0">
                <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 004.486-6.336l-3.276 3.277a3.004 3.004 0 01-2.25-2.25l3.276-3.276a4.5 4.5 0 00-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437l1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008z"/>
                </svg>
            </div>
            <div class="min-w-0">
                <p class="text-sm font-bold text-white leading-none tracking-wide">FRONDA</p>
                <p class="text-xs text-slate-400 mt-0.5 truncate leading-none">{{ auth.tenantName ?? 'CMMS' }}</p>
            </div>
        </div>

        <!-- Global search trigger -->
        <div class="px-2 pt-3">
            <button
                @click="palette.open()"
                class="w-full flex items-center gap-2 px-2.5 py-2 rounded-lg bg-slate-800/60 hover:bg-slate-800 text-slate-400 hover:text-slate-200 transition-colors"
            >
                <AppIcon name="search" class="w-4 h-4 shrink-0" />
                <span class="text-sm">Buscar…</span>
                <kbd class="ml-auto shrink-0 text-[11px] font-semibold text-slate-500 bg-slate-900 rounded px-1.5 py-0.5">⌘K</kbd>
            </button>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 overflow-y-auto py-3 px-2 space-y-0.5">
            <!-- Inicio -->
            <NavItem :item="{ name: 'Inicio', to: 'ops.inicio', icon: icons.home }" />
            <!-- Dashboard -->
            <NavItem :item="{ name: 'Dashboard', to: 'ops.dashboard', icon: icons.chartBar }" />

            <!-- Mantenimiento -->
            <NavGroup label="Mantenimiento">
                <NavItem :item="{ name: 'Solicitudes', to: 'ops.solicitudes', icon: icons.clipboard }" />
                <NavItem :item="{ name: 'Órdenes de trabajo', to: 'ops.ordenes', icon: icons.wrench }" />
                <NavItem :item="{ name: 'Mantenimiento Programado', to: 'ops.preventivos', icon: icons.calendar }" />
                <NavItem :item="{ name: 'Paros', to: 'ops.paros', icon: icons.wrench }" />
            </NavGroup>

            <!-- Activos -->
            <NavGroup label="Activos">
                <NavItem :item="{ name: 'Equipos', to: 'ops.equipos', icon: icons.cube }" />
            </NavGroup>

            <!-- Inventario -->
            <NavGroup label="Inventario">
                <NavItem :item="{ name: 'Repuestos', to: 'ops.repuestos', icon: icons.package }" />
                <NavItem :item="{ name: 'Almacenes', to: 'ops.almacenes', icon: icons.warehouse }" />
            </NavGroup>

            <!-- Análisis -->
            <NavGroup label="Análisis">
                <NavItem :item="{ name: 'Eficiencia de planta', to: 'ops.eficiencia', icon: icons.chartBar }" />
                <NavItem :item="{ name: 'Indicadores', to: 'ops.kpis', icon: icons.chartBar }" />
                <NavItem :item="{ name: 'Resumen Ejecutivo', to: 'ops.gerencial', icon: icons.chartBar }" />
                <NavItem :item="{ name: 'Reportes', to: 'ops.reportes', icon: icons.fileText }" />
            </NavGroup>

            <!-- Plataforma — exclusivo Super Admin -->
            <NavGroup v-if="auth.isSuperAdmin" label="Plataforma">
                <NavItem :item="{ name: 'Dashboard Global', to: 'ops.plataforma', icon: icons.chartBar }" />
            </NavGroup>

            <div class="my-2 border-t border-slate-800" />

            <NavItem :item="{ name: 'Alertas', to: 'ops.alertas', icon: icons.bell }" />
        </nav>

        <!-- User footer -->
        <div class="border-t border-slate-800 px-3 py-3 shrink-0">
            <div class="flex items-center gap-2.5">
                <div class="flex items-center justify-center w-7 h-7 rounded-full bg-emerald-700 text-xs font-bold text-white shrink-0">
                    {{ auth.userInitials }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-slate-200 truncate leading-none">{{ auth.userName ?? auth.userEmail }}</p>
                    <p class="text-xs text-slate-500 mt-0.5 truncate leading-none">{{ auth.userEmail }}</p>
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
import { useCommandPalette } from '../composables/useCommandPalette.js'
import { icons } from '../../shared/icons.js'
import AppIcon from '../components/AppIcon.vue'
import ImpersonationBanner from '../components/ImpersonationBanner.vue'
import NavItem from './NavItem.vue'
import NavGroup from './NavGroup.vue'

const emit = defineEmits(['close'])
const router = useRouter()
const auth = useAuthStore()
const palette = useCommandPalette()

async function handleLogout() {
    await auth.logout()
    router.push({ name: 'ops.login' })
}
</script>
