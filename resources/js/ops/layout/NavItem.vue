<template>
    <RouterLink
        :to="{ name: item.to }"
        class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg text-sm transition-all group"
        :class="isActive
            ? 'bg-emerald-900/40 text-emerald-400 border-l-2 border-emerald-500 pl-[calc(0.625rem-2px)]'
            : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/70 border-l-2 border-transparent'"
    >
        <svg
            class="w-4 h-4 shrink-0 transition-colors"
            :class="isActive ? 'text-emerald-400' : 'text-slate-500 group-hover:text-slate-300'"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            stroke-width="1.75"
            stroke-linecap="round"
            stroke-linejoin="round"
            v-html="item.icon"
        />
        <span class="truncate font-medium">{{ item.name }}</span>
        <span
            v-if="item.badge"
            class="ml-auto shrink-0 min-w-[1.125rem] h-[1.125rem] flex items-center justify-center rounded-full text-[10px] font-bold px-1"
            :class="isActive ? 'bg-emerald-500 text-white' : 'bg-slate-700 text-slate-300'"
        >{{ item.badge }}</span>
    </RouterLink>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'

const props = defineProps({
    item: { type: Object, required: true },
})

const route = useRoute()
const isActive = computed(() => route.name === props.item.to)
</script>
