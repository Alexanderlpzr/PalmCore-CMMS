<template>
    <div class="flex h-full overflow-hidden bg-gray-50">

        <!-- Desktop sidebar (always visible ≥1024px) -->
        <AppSidebar class="hidden lg:flex flex-col" />

        <!-- Mobile sidebar overlay -->
        <Transition name="sidebar">
            <div v-if="sidebarOpen" class="fixed inset-0 z-50 flex lg:hidden">
                <!-- Backdrop -->
                <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="sidebarOpen = false" />
                <!-- Drawer -->
                <div class="relative flex flex-col w-60 h-full">
                    <AppSidebar @close="sidebarOpen = false" />
                </div>
            </div>
        </Transition>

        <!-- Main area -->
        <div class="flex flex-col flex-1 min-w-0 overflow-hidden">
            <!-- Mobile top bar -->
            <TopBar @toggle-sidebar="sidebarOpen = !sidebarOpen" />

            <!-- Page content -->
            <main class="flex-1 overflow-y-auto">
                <RouterView v-slot="{ Component }">
                    <Transition name="page" mode="out-in">
                        <component :is="Component" :key="route.name" />
                    </Transition>
                </RouterView>
            </main>

            <!-- Mobile bottom nav -->
            <MobileBottomNav class="lg:hidden" />
        </div>

        <!-- Global command palette (Cmd/Ctrl + K) -->
        <CommandPalette />
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { RouterView, useRoute } from 'vue-router'
import AppSidebar from './AppSidebar.vue'
import TopBar from './TopBar.vue'
import MobileBottomNav from './MobileBottomNav.vue'
import CommandPalette from '../components/CommandPalette.vue'
import { useCommandPalette } from '../composables/useCommandPalette.js'

const sidebarOpen = ref(false)
const route = useRoute()
const palette = useCommandPalette()

function onGlobalKey(e) {
    if ((e.metaKey || e.ctrlKey) && (e.key === 'k' || e.key === 'K')) {
        e.preventDefault()
        palette.toggle()
    }
}

onMounted(() => window.addEventListener('keydown', onGlobalKey))
onUnmounted(() => window.removeEventListener('keydown', onGlobalKey))
</script>

<style scoped>
.sidebar-enter-active, .sidebar-leave-active { transition: opacity 0.2s ease; }
.sidebar-enter-from, .sidebar-leave-to { opacity: 0; }

.page-enter-active, .page-leave-active { transition: opacity 0.15s ease; }
.page-enter-from, .page-leave-to { opacity: 0; }
</style>
