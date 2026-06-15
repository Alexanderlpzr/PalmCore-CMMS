<template>
    <div class="mt-4">
        <p class="px-2.5 mb-1 text-xs font-semibold uppercase tracking-widest text-slate-500">
            {{ label }}
        </p>
        <div class="space-y-0.5">
            <slot />
        </div>
    </div>
</template>

<script setup>
defineProps({ label: { type: String, required: true } })
</script>
