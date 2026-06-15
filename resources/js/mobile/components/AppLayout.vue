<template>
    <div class="flex flex-col h-screen bg-zinc-950 text-zinc-100">
        <!-- Top bar -->
        <header
            class="shrink-0 flex items-center gap-3 px-4 h-14 bg-zinc-900 border-b border-zinc-800 sticky top-0 z-10"
            :style="{ paddingTop: 'env(safe-area-inset-top)', height: 'calc(3.5rem + env(safe-area-inset-top))' }"
        >
            <button
                v-if="showBack"
                @click="router.back()"
                class="p-2 -ml-2 rounded-xl hover:bg-zinc-800 transition shrink-0"
                aria-label="Volver"
            >
                <svg class="w-5 h-5 text-zinc-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                </svg>
            </button>
            <h1 class="font-semibold text-base text-zinc-100 truncate flex-1">{{ title }}</h1>

            <!-- Logout button (only on top-level routes) -->
            <button
                v-if="!showBack"
                @click="handleLogout"
                class="p-2 -mr-2 rounded-xl hover:bg-zinc-800 transition shrink-0"
                aria-label="Cerrar sesión"
            >
                <svg class="w-5 h-5 text-zinc-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75"/>
                </svg>
            </button>
        </header>

        <!-- Status bars (below header, above content) -->
        <OfflineBar />
        <PendingActionsBar />

        <!-- Main scrollable content -->
        <main class="flex-1 overflow-y-auto">
            <slot />
        </main>

        <!-- Bottom navigation -->
        <BottomNav />
    </div>
</template>

<script setup>
import { useRouter } from 'vue-router'
import BottomNav from './BottomNav.vue'
import OfflineBar from './OfflineBar.vue'
import PendingActionsBar from './PendingActionsBar.vue'
import { usePushNotifications } from '../composables/usePushNotifications.js'
import { useAuthStore } from '../stores/auth.js'

defineProps({
    title: { type: String, default: 'Fronda' },
    showBack: { type: Boolean, default: false },
})

const router = useRouter()
const auth = useAuthStore()
const push = usePushNotifications()

async function handleLogout() {
    await push.deactivate()
    auth.logout()
    router.push({ name: 'login' })
}
</script>
