<template>
    <!-- Backdrop -->
    <Transition name="fade">
        <div
            v-if="open"
            class="fixed inset-0 bg-black/70 z-40"
            @click="$emit('update:open', false)"
        />
    </Transition>

    <!-- Sheet -->
    <Transition name="slide-up">
        <div
            v-if="open"
            class="fixed bottom-0 inset-x-0 z-50 bg-zinc-900 rounded-t-3xl shadow-xl overflow-hidden"
            :class="tall ? 'max-h-[85vh]' : 'max-h-[75vh]'"
            :style="{ paddingBottom: 'env(safe-area-inset-bottom, 0px)' }"
        >
            <!-- Handle -->
            <div class="flex justify-center pt-3 pb-1">
                <div class="w-10 h-1 rounded-full bg-zinc-700" />
            </div>

            <!-- Title -->
            <div v-if="title" class="px-5 pb-3 border-b border-zinc-800">
                <h3 class="text-base font-semibold text-zinc-100">{{ title }}</h3>
            </div>

            <!-- Scrollable content -->
            <div
                class="overflow-y-auto"
                :class="tall ? 'max-h-[80vh]' : 'max-h-[68vh]'"
            >
                <slot />
            </div>
        </div>
    </Transition>
</template>

<script setup>
import { watch } from 'vue'

const props = defineProps({
    open: { type: Boolean, required: true },
    title: { type: String, default: '' },
    tall: { type: Boolean, default: false },
})

defineEmits(['update:open'])

watch(
    () => props.open,
    (val) => { document.body.style.overflow = val ? 'hidden' : '' },
)
</script>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 200ms ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

.slide-up-enter-active, .slide-up-leave-active {
    transition: transform 280ms cubic-bezier(0.32, 0.72, 0, 1);
}
.slide-up-enter-from, .slide-up-leave-to { transform: translateY(100%); }
</style>
