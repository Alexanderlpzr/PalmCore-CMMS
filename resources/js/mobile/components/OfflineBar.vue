<template>
    <Transition name="slide-down">
        <div
            v-if="!network.isOnline"
            class="bg-amber-500/10 border-b border-amber-500/20 px-4 py-2 flex items-center gap-2"
        >
            <div class="w-2 h-2 rounded-full bg-amber-500 shrink-0 animate-pulse" />
            <p class="text-xs font-medium text-amber-400">
                Sin conexión — trabajando en modo offline
            </p>
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
