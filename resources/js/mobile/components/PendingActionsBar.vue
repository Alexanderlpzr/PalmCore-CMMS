<template>
    <Transition name="slide-down">
        <div
            v-if="network.isOnline && (network.pendingCount > 0 || network.syncInProgress)"
            class="bg-blue-500/10 border-b border-blue-500/20 px-4 py-2 flex items-center justify-between gap-2"
        >
            <div class="flex items-center gap-2">
                <svg
                    v-if="network.syncInProgress"
                    class="w-3.5 h-3.5 text-blue-400 animate-spin shrink-0"
                    fill="none" viewBox="0 0 24 24"
                >
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                <div v-else class="w-2 h-2 rounded-full bg-blue-400 shrink-0" />
                <p class="text-xs font-medium text-blue-300">
                    {{ network.syncInProgress
                        ? 'Sincronizando…'
                        : `${network.pendingCount} acción${network.pendingCount !== 1 ? 'es' : ''} pendiente${network.pendingCount !== 1 ? 's' : ''}`
                    }}
                </p>
            </div>
            <button
                v-if="!network.syncInProgress && network.pendingCount > 0"
                type="button"
                @click="network.triggerSync()"
                class="text-xs font-semibold text-blue-400 hover:text-blue-300 transition"
            >
                Sincronizar
            </button>
        </div>
    </Transition>
</template>

<script setup>
import { useNetworkStore } from '../stores/networkStore.js'

const network = useNetworkStore()
</script>

<style scoped>
.slide-down-enter-active, .slide-down-leave-active { transition: all 220ms ease; }
.slide-down-enter-from, .slide-down-leave-to { opacity: 0; transform: translateY(-100%); }
</style>
