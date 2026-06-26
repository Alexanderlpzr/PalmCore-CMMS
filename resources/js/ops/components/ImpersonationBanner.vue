<template>
    <div
        v-if="auth.isImpersonating"
        class="flex items-center gap-3 px-4 py-2 bg-amber-500 text-amber-950 text-sm font-medium shrink-0"
    >
        <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
        </svg>
        <span>
            Viendo como <strong>{{ auth.impersonationContext?.impersonated_name ?? 'otro usuario' }}</strong>
            <span v-if="auth.impersonationContext?.tenant_name"> — {{ auth.impersonationContext.tenant_name }}</span>
        </span>
        <a
            href="/admin"
            class="ml-auto text-xs underline hover:no-underline shrink-0"
        >Volver al panel</a>
    </div>
</template>

<script setup>
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
</script>
