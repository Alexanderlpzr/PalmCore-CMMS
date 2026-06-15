<template>
    <header class="lg:hidden flex items-center gap-3 px-4 bg-white border-b border-gray-200 shrink-0"
            :style="{ height: 'calc(3.5rem + env(safe-area-inset-top))', paddingTop: 'env(safe-area-inset-top)' }">
        <!-- Hamburger -->
        <button
            @click="emit('toggle-sidebar')"
            class="p-2 -ml-2 rounded-xl text-gray-500 hover:bg-gray-100 transition-colors"
            aria-label="Menú"
        >
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <line x1="3" y1="6" x2="21" y2="6"/>
                <line x1="3" y1="12" x2="21" y2="12"/>
                <line x1="3" y1="18" x2="21" y2="18"/>
            </svg>
        </button>

        <!-- Logo -->
        <div class="flex items-center gap-2 flex-1">
            <div class="flex items-center justify-center w-6 h-6 rounded-md bg-emerald-600">
                <svg class="w-3.5 h-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 004.486-6.336l-3.276 3.277a3.004 3.004 0 01-2.25-2.25l3.276-3.276a4.5 4.5 0 00-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437l1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008z"/>
                </svg>
            </div>
            <span class="text-sm font-bold text-gray-900 tracking-wide">FRONDA</span>
        </div>

        <!-- User avatar -->
        <div class="flex items-center justify-center w-8 h-8 rounded-full bg-emerald-700 text-xs font-bold text-white shrink-0">
            {{ auth.userInitials }}
        </div>
    </header>
</template>

<script setup>
import { useAuthStore } from '../stores/auth.js'

defineEmits(['toggle-sidebar'])
const auth = useAuthStore()
</script>
