import { ref } from 'vue'
import { useNetworkStore } from '../stores/networkStore.js'
import { useApi } from './useApi.js'
import db from '../db/database.js'

async function registerBackgroundSync() {
    if ('serviceWorker' in navigator && 'SyncManager' in window) {
        try {
            const reg = await navigator.serviceWorker.ready
            await reg.sync.register('palmcore-sync')
        } catch {
            // Background Sync unavailable — online/offline events are the fallback
        }
    }
}

function newPendingAction(fields) {
    return {
        created_at: new Date(),
        retry_count: 0,
        status: 'pending',
        error_message: null,
        media_blob: null,
        ...fields,
    }
}

export function usePendingActions() {
    const network = useNetworkStore()
    const api = useApi()
    const loading = ref(false)
    const error = ref(null)

    async function queueOrSubmitTimeEntry(workOrderId, payload, gps = null) {
        const idempotencyKey = crypto.randomUUID()
        loading.value = true
        error.value = null

        const fullPayload = { ...payload, ...(gps ? { gps } : {}) }

        try {
            if (!network.isOnline) {
                await db.pendingActions.add(newPendingAction({
                    action_type: 'TIME_ENTRY',
                    work_order_id: workOrderId,
                    payload: fullPayload,
                    idempotency_key: idempotencyKey,
                }))
                await registerBackgroundSync()
                return { queued: true }
            }

            try {
                const result = await api.post(
                    `work-orders/${workOrderId}/time-entries`,
                    fullPayload,
                    { 'Idempotency-Key': idempotencyKey },
                )
                return { queued: false, data: result }
            } catch (e) {
                if (e instanceof TypeError) {
                    await db.pendingActions.add(newPendingAction({
                        action_type: 'TIME_ENTRY',
                        work_order_id: workOrderId,
                        payload: fullPayload,
                        idempotency_key: idempotencyKey,
                    }))
                    await registerBackgroundSync()
                    return { queued: true }
                }
                throw e
            }
        } catch (e) {
            error.value = e.message
            throw e
        } finally {
            loading.value = false
        }
    }

    async function queueOrSubmitComment(workOrderId, body, isInternal = false, gps = null) {
        const idempotencyKey = crypto.randomUUID()
        loading.value = true
        error.value = null

        try {
            const payload = { body, is_internal: isInternal, ...(gps ? { gps } : {}) }

            if (!network.isOnline) {
                await db.pendingActions.add(newPendingAction({
                    action_type: 'COMMENT',
                    work_order_id: workOrderId,
                    payload,
                    idempotency_key: idempotencyKey,
                }))
                await registerBackgroundSync()
                return { queued: true }
            }

            try {
                const result = await api.post(
                    `work-orders/${workOrderId}/comments`,
                    payload,
                    { 'Idempotency-Key': idempotencyKey },
                )
                return { queued: false, data: result }
            } catch (e) {
                if (e instanceof TypeError) {
                    await db.pendingActions.add(newPendingAction({
                        action_type: 'COMMENT',
                        work_order_id: workOrderId,
                        payload,
                        idempotency_key: idempotencyKey,
                    }))
                    await registerBackgroundSync()
                    return { queued: true }
                }
                throw e
            }
        } catch (e) {
            error.value = e.message
            throw e
        } finally {
            loading.value = false
        }
    }

    async function queueOrSubmitMedia(workOrderId, blob, attachmentType, caption = '', gps = null) {
        const idempotencyKey = crypto.randomUUID()
        loading.value = true
        error.value = null

        try {
            const payload = { attachment_type: attachmentType, caption, ...(gps ? { gps } : {}) }

            if (!network.isOnline) {
                await checkStorageQuota()
                await db.pendingActions.add(newPendingAction({
                    action_type: 'MEDIA_UPLOAD',
                    work_order_id: workOrderId,
                    payload,
                    media_blob: blob,
                    idempotency_key: idempotencyKey,
                }))
                await registerBackgroundSync()
                return { queued: true }
            }

            try {
                const form = buildMediaForm(blob, attachmentType, caption, gps)
                const result = await api.upload(`work-orders/${workOrderId}/media`, form, {
                    'Idempotency-Key': idempotencyKey,
                })
                return { queued: false, data: result }
            } catch (e) {
                if (e instanceof TypeError) {
                    await checkStorageQuota()
                    await db.pendingActions.add(newPendingAction({
                        action_type: 'MEDIA_UPLOAD',
                        work_order_id: workOrderId,
                        payload,
                        media_blob: blob,
                        idempotency_key: idempotencyKey,
                    }))
                    await registerBackgroundSync()
                    return { queued: true }
                }
                throw e
            }
        } catch (e) {
            error.value = e.message
            throw e
        } finally {
            loading.value = false
        }
    }

    async function queueOrSubmitSignature(workOrderId, blob, signatureType, notes = '', gps = null) {
        const idempotencyKey = crypto.randomUUID()
        loading.value = true
        error.value = null

        try {
            if (!network.isOnline) {
                await checkStorageQuota()
                await db.pendingActions.add(newPendingAction({
                    action_type: 'SIGNATURE',
                    work_order_id: workOrderId,
                    payload: { signature_type: signatureType, notes, ...(gps ? { gps } : {}) },
                    media_blob: blob,
                    idempotency_key: idempotencyKey,
                }))
                await registerBackgroundSync()
                return { queued: true }
            }

            try {
                // Single request: the signature image travels with its own record,
                // so it can never be orphaned from the metadata that describes it.
                const form = buildSignatureForm(blob, signatureType, notes, gps)
                await api.upload(`work-orders/${workOrderId}/signature`, form, {
                    'Idempotency-Key': idempotencyKey,
                })
                return { queued: false }
            } catch (e) {
                if (e instanceof TypeError) {
                    await checkStorageQuota()
                    await db.pendingActions.add(newPendingAction({
                        action_type: 'SIGNATURE',
                        work_order_id: workOrderId,
                        payload: { signature_type: signatureType, notes, ...(gps ? { gps } : {}) },
                        media_blob: blob,
                        idempotency_key: idempotencyKey,
                    }))
                    await registerBackgroundSync()
                    return { queued: true }
                }
                throw e
            }
        } catch (e) {
            error.value = e.message
            throw e
        } finally {
            loading.value = false
        }
    }

    async function queueOrSubmitAlertResolve(alertId) {
        return _queueOrSubmitAlertAction(alertId, 'ALERT_RESOLVE', `alerts/${alertId}/resolve`)
    }

    async function queueOrSubmitAlertDismiss(alertId) {
        return _queueOrSubmitAlertAction(alertId, 'ALERT_DISMISS', `alerts/${alertId}/dismiss`)
    }

    async function _queueOrSubmitAlertAction(alertId, actionType, endpoint) {
        loading.value = true
        error.value = null

        try {
            if (!network.isOnline) {
                await _deduplicateAndQueueAlert(alertId, actionType)
                await registerBackgroundSync()
                return { queued: true }
            }

            try {
                const result = await api.patch(endpoint, null)
                return { queued: false, data: result }
            } catch (e) {
                if (e instanceof TypeError) {
                    await _deduplicateAndQueueAlert(alertId, actionType)
                    await registerBackgroundSync()
                    return { queued: true }
                }
                throw e
            }
        } catch (e) {
            error.value = e.message
            throw e
        } finally {
            loading.value = false
        }
    }

    async function _deduplicateAndQueueAlert(alertId, actionType) {
        const existing = await db.pendingActions
            .where('alert_id').equals(alertId)
            .filter(a => a.action_type === actionType && a.status === 'pending')
            .first()

        if (!existing) {
            await db.pendingActions.add(newPendingAction({
                action_type: actionType,
                alert_id: alertId,
                idempotency_key: crypto.randomUUID(),
            }))
        }
    }

    return {
        loading,
        error,
        queueOrSubmitTimeEntry,
        queueOrSubmitComment,
        queueOrSubmitMedia,
        queueOrSubmitSignature,
        queueOrSubmitAlertResolve,
        queueOrSubmitAlertDismiss,
    }
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function buildSignatureForm(blob, signatureType, notes, gps) {
    const form = new FormData()
    form.append('signature_image', blob, `signature_${Date.now()}.png`)
    form.append('signature_type', signatureType)
    if (notes) form.append('notes', notes)
    if (gps) {
        form.append('gps[latitude]', gps.latitude)
        form.append('gps[longitude]', gps.longitude)
        form.append('gps[accuracy]', gps.accuracy)
        if (gps.source) form.append('gps[source]', gps.source)
        if (gps.gps_timestamp) form.append('gps[gps_timestamp]', gps.gps_timestamp)
    }
    return form
}

function buildMediaForm(blob, attachmentType, caption, gps) {
    const ext = blob.type === 'image/png' ? 'png' : 'jpg'
    const form = new FormData()
    form.append('file', blob, `photo_${Date.now()}.${ext}`)
    form.append('attachment_type', attachmentType)
    if (caption) form.append('caption', caption)
    if (gps) {
        form.append('gps[latitude]', gps.latitude)
        form.append('gps[longitude]', gps.longitude)
        form.append('gps[accuracy]', gps.accuracy)
        if (gps.source) form.append('gps[source]', gps.source)
        if (gps.gps_timestamp) form.append('gps[gps_timestamp]', gps.gps_timestamp)
    }
    return form
}

async function checkStorageQuota() {
    if (!navigator.storage?.estimate) return
    try {
        const { usage, quota } = await navigator.storage.estimate()
        const remaining = quota - usage
        if (remaining < 20 * 1024 * 1024) {
            throw new Error('Almacenamiento limitado — sincronizá antes de continuar')
        }
    } catch (e) {
        if (e.message.includes('Almacenamiento')) throw e
        // estimate() failed for other reason — ignore
    }
}
