<template>
    <Teleport to="body">
        <div
            class="fixed inset-x-0 z-[60] flex flex-col gap-2 px-4 pointer-events-none"
            :style="{ bottom: 'calc(env(safe-area-inset-bottom, 0px) + 4.5rem)' }"
        >
            <TransitionGroup name="toast">
                <div
                    v-for="toast in toasts"
                    :key="toast.id"
                    class="w-full max-w-sm mx-auto px-4 py-3 rounded-2xl shadow-lg text-sm font-medium pointer-events-auto"
                    :class="{
                        'bg-green-600 text-white': toast.type === 'success',
                        'bg-red-600 text-white': toast.type === 'error',
                        'bg-blue-500 text-white': toast.type === 'info',
                    }"
                >
                    {{ toast.message }}
                </div>
            </TransitionGroup>
        </div>
    </Teleport>
</template>

<script setup>
import { useToast } from '../composables/useToast.js'

const { toasts } = useToast()
</script>

<style scoped>
.toast-enter-active, .toast-leave-active { transition: all 220ms ease; }
.toast-enter-from { opacity: 0; transform: translateY(0.75rem); }
.toast-leave-to { opacity: 0; transform: translateY(-0.5rem); }
</style>
