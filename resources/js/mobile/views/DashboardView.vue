<template>
    <AppLayout title="Inicio">
        <div class="px-4 py-6 space-y-6">

            <!-- Greeting -->
            <div>
                <p class="text-sm text-zinc-400">Bienvenido</p>
                <h2 class="text-xl font-semibold text-zinc-100 truncate">
                    {{ auth.tenantName ?? 'Fronda' }}
                </h2>
                <p class="text-xs text-zinc-400 mt-0.5">{{ auth.userEmail }}</p>
            </div>

            <!-- Quick action: Mis OTs -->
            <RouterLink
                to="/mobile/work-orders"
                class="flex items-center justify-between bg-zinc-900 border border-zinc-800 rounded-2xl p-5 hover:border-zinc-700 transition"
            >
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-amber-500/10 flex items-center justify-center">
                        <svg class="w-6 h-6 text-amber-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2"/>
                        </svg>
                    </div>
                    <div>
                        <p class="font-semibold text-zinc-100">Mis órdenes de trabajo</p>
                        <p class="text-sm text-zinc-400">Ver OTs asignadas</p>
                    </div>
                </div>
                <svg class="w-5 h-5 text-zinc-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
            </RouterLink>

            <!-- Quick action: Escanear QR -->
            <RouterLink
                to="/mobile/scan"
                class="flex items-center justify-between bg-zinc-900 border border-zinc-800 rounded-2xl p-5 hover:border-zinc-700 transition"
            >
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-zinc-800 flex items-center justify-center">
                        <svg class="w-6 h-6 text-zinc-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 3.75 9.375v-4.5Z"/>
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5Z"/>
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 13.5 9.375v-4.5Z"/>
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M13.5 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5Z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="font-semibold text-zinc-100">Escanear QR</p>
                        <p class="text-sm text-zinc-400">Identificar equipo por código</p>
                    </div>
                </div>
                <svg class="w-5 h-5 text-zinc-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
            </RouterLink>

            <!-- Alerts: badge de alertas críticas -->
            <RouterLink
                to="/mobile/alerts"
                class="flex items-center justify-between bg-zinc-900 border border-zinc-800 rounded-2xl p-5 hover:border-zinc-700 transition"
            >
                <div class="flex items-center gap-4">
                    <div class="relative w-12 h-12 rounded-xl bg-red-500/10 flex items-center justify-center">
                        <svg class="w-6 h-6 text-red-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/>
                        </svg>
                        <span
                            v-if="alerts.criticalCount > 0"
                            class="absolute -top-1 -right-1 min-w-4.5 h-4.5 px-1 bg-red-500 text-white text-xs font-bold rounded-full flex items-center justify-center"
                        >
                            {{ alerts.criticalCount > 99 ? '99+' : alerts.criticalCount }}
                        </span>
                    </div>
                    <div>
                        <p class="font-semibold text-zinc-100">Centro de Alertas</p>
                        <p class="text-sm text-zinc-400">
                            <template v-if="alerts.criticalCount > 0">
                                {{ alerts.criticalCount }} alerta{{ alerts.criticalCount > 1 ? 's' : '' }} crítica{{ alerts.criticalCount > 1 ? 's' : '' }} abierta{{ alerts.criticalCount > 1 ? 's' : '' }}
                            </template>
                            <template v-else>Sin alertas críticas activas</template>
                        </p>
                    </div>
                </div>
                <svg class="w-5 h-5 text-zinc-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
            </RouterLink>

            <!-- Push notification prompt: only shown when permission not yet decided -->
            <button
                v-if="push.isSupported && push.permission === 'default'"
                @click="handleEnableNotifications"
                :disabled="enablingPush"
                class="w-full flex items-center gap-4 bg-zinc-900 border border-amber-500/30 rounded-2xl p-5 text-left hover:border-amber-500/60 transition"
            >
                <div class="w-12 h-12 rounded-xl bg-amber-500/10 flex items-center justify-center shrink-0">
                    <svg class="w-6 h-6 text-amber-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/>
                    </svg>
                </div>
                <div class="flex-1">
                    <p class="font-semibold text-zinc-100">Activar notificaciones</p>
                    <p class="text-sm text-zinc-400">Recibe alertas de nuevas OTs asignadas</p>
                </div>
                <svg v-if="!enablingPush" class="w-5 h-5 text-zinc-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
                <svg v-else class="w-5 h-5 text-zinc-400 animate-spin shrink-0" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
            </button>

            <!-- Denied state: guide user to settings -->
            <div
                v-if="push.isSupported && push.permission === 'denied'"
                class="flex items-start gap-4 bg-zinc-900 border border-zinc-700 rounded-2xl p-5"
            >
                <div class="w-12 h-12 rounded-xl bg-zinc-800 flex items-center justify-center shrink-0">
                    <svg class="w-6 h-6 text-zinc-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M9.143 17.082a24.248 24.248 0 0 0 3.844.148m-3.844-.148a23.856 23.856 0 0 1-5.455-1.31 8.964 8.964 0 0 0 2.3-5.542m3.155 6.852a3 3 0 0 0 5.667 1.97m1.965-2.277L21 21m-4.225-4.225a23.81 23.81 0 0 0 3.536-1.003A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6.53 6.53m10.245 10.245L6.53 6.53M3 3l3.53 3.53"/>
                    </svg>
                </div>
                <div>
                    <p class="font-semibold text-zinc-400 text-sm">Notificaciones desactivadas</p>
                    <p class="text-xs text-zinc-400 mt-1">Para activarlas, ve a la configuración del sitio en tu navegador y habilita las notificaciones para esta página.</p>
                </div>
            </div>

        </div>
    </AppLayout>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import AppLayout from '../components/AppLayout.vue'
import { usePushNotifications } from '../composables/usePushNotifications.js'
import { useAlertsStore } from '../stores/alerts.js'
import { useAuthStore } from '../stores/auth.js'

const auth = useAuthStore()
const push = usePushNotifications()
const alerts = useAlertsStore()
const enablingPush = ref(false)

onMounted(() => alerts.fetchCriticalCount())

async function handleEnableNotifications() {
    enablingPush.value = true
    await push.requestAndSubscribe()
    enablingPush.value = false
}
</script>
