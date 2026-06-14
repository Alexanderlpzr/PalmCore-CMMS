import { defineStore } from 'pinia'
import { ref } from 'vue'
import { useApi } from '../composables/useApi.js'
import { usePendingActions } from '../composables/usePendingActions.js'
import { useNetworkStore } from './networkStore.js'

export const useAlertsStore = defineStore('alerts', () => {
    const items = ref([])
    const criticalCount = ref(0)
    const loading = ref(false)
    const error = ref(null)

    async function fetchAlerts(params = {}) {
        loading.value = true
        error.value = null
        try {
            const api = useApi()
            const qs = new URLSearchParams({ status: 'open', per_page: 50, ...params }).toString()
            const data = await api.get(`alerts?${qs}`)
            items.value = data.data ?? []
        } catch (e) {
            error.value = e.message
        } finally {
            loading.value = false
        }
    }

    async function fetchCriticalCount() {
        try {
            const api = useApi()
            const data = await api.get('alerts/count?status=open&severity=critical')
            criticalCount.value = data.count ?? 0
        } catch {
            // Silently fail — badge is best-effort
        }
    }

    /**
     * Resolve an alert. Optimistic: removes it from the open list immediately.
     * Queues the action offline if there is no network.
     */
    async function resolveAlert(alertId) {
        const idx = items.value.findIndex(a => a.id === alertId)
        const original = idx !== -1 ? items.value[idx] : null

        // Optimistic removal from open list
        if (idx !== -1) items.value.splice(idx, 1)
        if (criticalCount.value > 0 && original?.severity === 'critical') {
            criticalCount.value--
        }

        const network = useNetworkStore()

        if (!network.isOnline) {
            const { queueOrSubmitAlertResolve } = usePendingActions()
            await queueOrSubmitAlertResolve(alertId)
            return { queued: true }
        }

        try {
            const { queueOrSubmitAlertResolve } = usePendingActions()
            const result = await queueOrSubmitAlertResolve(alertId)
            // result.data.status === 'resolved' | 'already_closed' — both are success
            return { queued: false, status: result.data?.status }
        } catch (e) {
            // Revert optimistic update on unexpected error
            if (original && idx !== -1) items.value.splice(idx, 0, original)
            if (original?.severity === 'critical') criticalCount.value++
            throw e
        }
    }

    /**
     * Dismiss an alert. Only valid for non-critical alerts (enforced by UI and API).
     * Optimistic: removes it from the open list immediately.
     */
    async function dismissAlert(alertId) {
        const idx = items.value.findIndex(a => a.id === alertId)
        const original = idx !== -1 ? items.value[idx] : null

        // Optimistic removal
        if (idx !== -1) items.value.splice(idx, 1)

        const network = useNetworkStore()

        if (!network.isOnline) {
            const { queueOrSubmitAlertDismiss } = usePendingActions()
            await queueOrSubmitAlertDismiss(alertId)
            return { queued: true }
        }

        try {
            const { queueOrSubmitAlertDismiss } = usePendingActions()
            const result = await queueOrSubmitAlertDismiss(alertId)
            return { queued: false, status: result.data?.status }
        } catch (e) {
            // Revert on error (e.g. 422 cannot_dismiss_critical, though UI guard prevents this)
            if (original && idx !== -1) items.value.splice(idx, 0, original)
            throw e
        }
    }

    return {
        items,
        criticalCount,
        loading,
        error,
        fetchAlerts,
        fetchCriticalCount,
        resolveAlert,
        dismissAlert,
    }
})
