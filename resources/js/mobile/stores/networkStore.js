import { defineStore } from 'pinia'
import { ref } from 'vue'
import { liveQuery } from 'dexie'
import db from '../db/database.js'
import { syncService } from '../services/syncService.js'

export const useNetworkStore = defineStore('network', () => {
    const isOnline = ref(navigator.onLine)
    const lastSyncAt = ref(null)
    const syncInProgress = ref(false)
    const syncRevision = ref(0) // increments after each sync — views watch this to refetch
    const pendingCount = ref(0)
    const failedCount = ref(0)
    const authError = ref(false)

    let pendingSub = null
    let failedSub = null
    let initialized = false

    function init() {
        if (initialized) return
        initialized = true

        // Reset actions stuck in 'syncing' from a previous crashed/killed session
        db.pendingActions.where('status').equals('syncing').modify({ status: 'pending' })

        // Reactive counts via Dexie liveQuery — automatically updates refs when DB changes
        pendingSub = liveQuery(() =>
            db.pendingActions.where('status').anyOf(['pending', 'syncing']).count(),
        ).subscribe({
            next: (count) => { pendingCount.value = count },
            error: () => {},
        })

        failedSub = liveQuery(() =>
            db.pendingActions.where('status').anyOf(['failed', 'conflict']).count(),
        ).subscribe({
            next: (count) => { failedCount.value = count },
            error: () => {},
        })

        window.addEventListener('online', handleOnline)
        window.addEventListener('offline', handleOffline)
        document.addEventListener('visibilitychange', handleVisibilityChange)
    }

    function handleOnline() {
        isOnline.value = true
        triggerSync()
    }

    function handleOffline() {
        isOnline.value = false
    }

    function handleVisibilityChange() {
        if (document.visibilityState === 'visible' && isOnline.value) {
            triggerSync()
        }
    }

    async function triggerSync() {
        if (syncInProgress.value || !isOnline.value) return
        const pending = await db.pendingActions.where('status').equals('pending').count()
        if (pending === 0) return

        syncInProgress.value = true
        try {
            await syncService({ authError })
            syncRevision.value++
            lastSyncAt.value = new Date()
        } finally {
            syncInProgress.value = false
        }
    }

    function destroy() {
        pendingSub?.unsubscribe()
        failedSub?.unsubscribe()
        window.removeEventListener('online', handleOnline)
        window.removeEventListener('offline', handleOffline)
        document.removeEventListener('visibilitychange', handleVisibilityChange)
    }

    return {
        isOnline,
        lastSyncAt,
        syncInProgress,
        syncRevision,
        pendingCount,
        failedCount,
        authError,
        init,
        triggerSync,
        destroy,
    }
})
