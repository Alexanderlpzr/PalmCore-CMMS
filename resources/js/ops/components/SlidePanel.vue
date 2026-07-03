<template>
    <Teleport to="body">
        <div
            v-if="open"
            ref="containerRef"
            tabindex="-1"
            class="fixed inset-0 z-50 flex justify-end outline-none"
            @keydown.esc="$emit('close')"
            @keydown.tab="trapFocus"
        >
            <!-- Backdrop -->
            <Transition name="sp-backdrop" appear>
                <div class="absolute inset-0 bg-black/40" @click="$emit('close')" />
            </Transition>

            <!-- Panel -->
            <Transition name="sp-panel" appear>
                <div
                    role="dialog"
                    aria-modal="true"
                    :aria-label="title"
                    class="relative flex flex-col w-full max-w-[480px] h-full bg-white shadow-2xl"
                >
                    <!-- Header -->
                    <div class="flex items-start justify-between px-6 py-4 border-b border-gray-100 shrink-0">
                        <div>
                            <h2 class="text-base font-bold text-gray-900">{{ title }}</h2>
                            <p v-if="description" class="mt-0.5 text-xs text-gray-500">{{ description }}</p>
                        </div>
                        <button
                            type="button"
                            class="ml-4 text-gray-400 hover:text-gray-600 transition-colors"
                            @click="$emit('close')"
                        >
                            <span class="sr-only">Cerrar</span>
                            <svg class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
                            </svg>
                        </button>
                    </div>

                    <!-- Body -->
                    <div class="flex-1 overflow-y-auto">
                        <slot />
                    </div>

                    <!-- Footer -->
                    <div class="shrink-0">
                        <slot name="footer" />
                    </div>
                </div>
            </Transition>
        </div>
    </Teleport>
</template>

<script setup>
import { ref, watch, nextTick } from 'vue'

const props = defineProps({
    open: {
        type: Boolean,
        required: true,
    },
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        default: null,
    },
})

defineEmits(['close'])

const containerRef = ref(null)

watch(() => props.open, (isOpen) => {
    if (isOpen) {
        nextTick(() => containerRef.value?.focus())
    }
})

// Keep Tab from leaving the panel while it's open — a keyboard user should
// cycle through its controls, not tab into the dimmed page behind it.
function trapFocus(e) {
    const focusable = containerRef.value?.querySelectorAll(
        'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])'
    )
    if (!focusable || !focusable.length) return
    const first = focusable[0]
    const last = focusable[focusable.length - 1]
    if (e.shiftKey && document.activeElement === first) {
        e.preventDefault()
        last.focus()
    } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault()
        first.focus()
    }
}
</script>

<style scoped>
.sp-backdrop-enter-active,
.sp-backdrop-leave-active {
    transition: opacity 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}
.sp-backdrop-enter-from,
.sp-backdrop-leave-to {
    opacity: 0;
}

.sp-panel-enter-active,
.sp-panel-leave-active {
    transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}
.sp-panel-enter-from,
.sp-panel-leave-to {
    transform: translateX(100%);
}
</style>
