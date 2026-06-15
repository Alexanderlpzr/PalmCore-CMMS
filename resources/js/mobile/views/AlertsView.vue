<template>
    <AppLayout title="Alertas">
        <!-- Severity filter pills -->
        <div class="px-4 pt-4 pb-2 flex gap-2 overflow-x-auto scrollbar-none">
            <button
                v-for="f in severityFilters"
                :key="f.value"
                @click="activeSeverity = f.value"
                :class="[
                    'shrink-0 px-4 py-1.5 rounded-full text-sm font-medium transition',
                    activeSeverity === f.value
                        ? 'bg-amber-500 text-zinc-950'
                        : 'bg-zinc-800 text-zinc-400 hover:bg-zinc-700'
                ]"
            >
                {{ f.label }}
            </button>
        </div>

        <!-- Loading -->
        <div v-if="store.loading" class="px-4 py-2 space-y-3">
            <div v-for="i in 4" :key="i" class="bg-zinc-900 rounded-2xl p-4 space-y-2 animate-pulse">
                <div class="h-3 bg-zinc-800 rounded w-1/3"></div>
                <div class="h-4 bg-zinc-800 rounded w-2/3"></div>
            </div>
        </div>

        <!-- Error -->
        <div v-else-if="store.error" class="px-4 py-8 text-center">
            <p class="text-red-400 text-sm">{{ store.error }}</p>
            <button @click="store.fetchAlerts()" class="mt-3 text-amber-400 text-sm underline">
                Reintentar
            </button>
        </div>

        <!-- Empty -->
        <div v-else-if="filteredAlerts.length === 0" class="px-4 py-16 text-center">
            <div class="w-16 h-16 rounded-2xl bg-zinc-900 flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-zinc-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                </svg>
            </div>
            <p class="text-zinc-400 text-sm">Sin alertas abiertas</p>
        </div>

        <!-- Alert cards -->
        <div v-else class="px-4 py-2 space-y-3">
            <div
                v-for="alert in filteredAlerts"
                :key="alert.id"
                class="bg-zinc-900 border rounded-2xl p-4"
                :class="severityBorderClass(alert.severity)"
            >
                <!-- Header row -->
                <div class="flex items-start justify-between gap-3 mb-2">
                    <div class="flex items-center gap-2">
                        <span
                            class="text-xs font-semibold px-2 py-0.5 rounded-full"
                            :class="severityBadgeClass(alert.severity)"
                        >
                            {{ severityLabel(alert.severity) }}
                        </span>
                        <span class="text-xs text-zinc-400">{{ categoryLabel(alert.category) }}</span>
                    </div>
                    <span class="text-xs text-zinc-400 shrink-0">{{ formatDate(alert.created_at) }}</span>
                </div>

                <!-- Title -->
                <p class="text-sm font-semibold text-zinc-100 leading-snug">{{ alert.title }}</p>

                <!-- Message -->
                <p v-if="alert.message" class="text-xs text-zinc-400 mt-1 leading-relaxed">
                    {{ alert.message }}
                </p>

                <!-- Actions -->
                <div class="flex gap-2 mt-3">
                    <button
                        @click="handleResolve(alert)"
                        :disabled="actioning.has(alert.id)"
                        class="flex-1 py-2 px-3 rounded-xl text-xs font-semibold bg-emerald-500/20 text-emerald-400 hover:bg-emerald-500/30 active:scale-95 transition disabled:opacity-50"
                    >
                        {{ actioning.has(alert.id) ? '...' : 'Resolver' }}
                    </button>

                    <!-- Dismiss only available for non-critical alerts -->
                    <button
                        v-if="alert.severity !== 'critical'"
                        @click="handleDismiss(alert)"
                        :disabled="actioning.has(alert.id)"
                        class="flex-1 py-2 px-3 rounded-xl text-xs font-semibold bg-zinc-700/60 text-zinc-400 hover:bg-zinc-700 active:scale-95 transition disabled:opacity-50"
                    >
                        Descartar
                    </button>
                </div>
            </div>
        </div>

        <!-- Offline queued toast -->
        <Transition name="fade">
            <div
                v-if="showOfflineToast"
                class="fixed bottom-20 left-1/2 -translate-x-1/2 bg-zinc-700 text-zinc-200 text-xs px-4 py-2 rounded-full shadow-lg"
            >
                Sin conexión — se sincronizará automáticamente
            </div>
        </Transition>
    </AppLayout>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import AppLayout from '../components/AppLayout.vue'
import { useAlertsStore } from '../stores/alerts.js'

const store = useAlertsStore()

const severityFilters = [
    { label: 'Todas', value: null },
    { label: '🔴 Críticas', value: 'critical' },
    { label: '🟡 Advertencias', value: 'warning' },
    { label: 'ℹ️ Informativas', value: 'info' },
]

const activeSeverity = ref(null)
const actioning = ref(new Set())  // alertIds currently being actioned
const showOfflineToast = ref(false)

const filteredAlerts = computed(() => {
    if (!activeSeverity.value) return store.items
    return store.items.filter(a => a.severity === activeSeverity.value)
})

async function handleResolve(alert) {
    if (actioning.value.has(alert.id)) return
    actioning.value = new Set([...actioning.value, alert.id])

    try {
        const result = await store.resolveAlert(alert.id)
        if (result?.queued) showOfflineMessage()
    } catch {
        // Error already reverted in store; alert reappears in list
    } finally {
        actioning.value = new Set([...actioning.value].filter(id => id !== alert.id))
    }
}

async function handleDismiss(alert) {
    if (actioning.value.has(alert.id)) return
    actioning.value = new Set([...actioning.value, alert.id])

    try {
        const result = await store.dismissAlert(alert.id)
        if (result?.queued) showOfflineMessage()
    } catch {
        // Error already reverted in store
    } finally {
        actioning.value = new Set([...actioning.value].filter(id => id !== alert.id))
    }
}

function showOfflineMessage() {
    showOfflineToast.value = true
    setTimeout(() => { showOfflineToast.value = false }, 3000)
}

function severityBorderClass(severity) {
    return {
        critical: 'border-red-800',
        warning:  'border-yellow-800',
        info:     'border-blue-800',
    }[severity] ?? 'border-zinc-800'
}

function severityBadgeClass(severity) {
    return {
        critical: 'bg-red-500/20 text-red-400',
        warning:  'bg-yellow-500/20 text-yellow-400',
        info:     'bg-blue-500/20 text-blue-400',
    }[severity] ?? 'bg-zinc-700 text-zinc-400'
}

function severityLabel(severity) {
    return { critical: 'Crítica', warning: 'Advertencia', info: 'Info' }[severity] ?? severity
}

function categoryLabel(category) {
    return {
        inventory:   'Inventario',
        reliability: 'Confiabilidad',
        maintenance: 'Mantenimiento',
        automation:  'Automatización',
        work_order:  'OT',
        system:      'Sistema',
    }[category] ?? category
}

function formatDate(iso) {
    if (!iso) return ''
    return new Date(iso).toLocaleString('es', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })
}

onMounted(() => store.fetchAlerts())
</script>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
