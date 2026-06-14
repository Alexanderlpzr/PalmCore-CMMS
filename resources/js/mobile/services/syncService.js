import db from '../db/database.js'
import { useAuthStore } from '../stores/auth.js'

const MAX_RETRIES_NETWORK = 5
const MAX_RETRIES_SERVER = 3

function getAuthToken() {
    // Read from Pinia store (in-memory) — not localStorage
    try {
        return useAuthStore().token ?? null
    } catch {
        return null
    }
}

function buildHeaders(idempotencyKey = null, includeJson = true) {
    const token = getAuthToken()
    return {
        Accept: 'application/json',
        ...(includeJson ? { 'Content-Type': 'application/json' } : {}),
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
        ...(idempotencyKey ? { 'Idempotency-Key': idempotencyKey } : {}),
    }
}

async function syncPost(endpoint, body, idempotencyKey) {
    try {
        return await fetch(`/api/v1/${endpoint}`, {
            method: 'POST',
            credentials: 'include',
            headers: buildHeaders(idempotencyKey, true),
            body: JSON.stringify(body),
        })
    } catch {
        return { status: 0 }
    }
}

async function syncPatch(endpoint) {
    try {
        return await fetch(`/api/v1/${endpoint}`, {
            method: 'PATCH',
            credentials: 'include',
            headers: buildHeaders(null, true),
        })
    } catch {
        return { status: 0 }
    }
}

async function syncUpload(endpoint, formData, idempotencyKey) {
    try {
        return await fetch(`/api/v1/${endpoint}`, {
            method: 'POST',
            credentials: 'include',
            headers: buildHeaders(idempotencyKey, false), // no Content-Type for multipart
            body: formData,
        })
    } catch {
        return { status: 0 }
    }
}

function classify(status) {
    if (status === 0) return 'network_error'
    if (status === 401) return 'auth_error'
    if (status === 409) return 'conflict'
    if (status >= 400 && status < 500) return 'client_error'
    if (status >= 500) return 'server_error'
    return 'ok'
}

async function extractMessage(response) {
    try {
        const data = await response.json()
        return data.message ?? `Error ${response.status}`
    } catch {
        return `Error ${response.status}`
    }
}

async function processAction(action) {
    const wid = action.work_order_id

    switch (action.action_type) {
        case 'TIME_ENTRY': {
            const r = await syncPost(
                `work-orders/${wid}/time-entries`,
                action.payload,
                action.idempotency_key,
            )
            return { classification: classify(r.status), response: r }
        }

        case 'COMMENT': {
            const r = await syncPost(
                `work-orders/${wid}/comments`,
                action.payload,
                action.idempotency_key,
            )
            return { classification: classify(r.status), response: r }
        }

        case 'MEDIA_UPLOAD': {
            const form = new FormData()
            form.append('file', action.media_blob, `media_${action.id}.jpg`)
            form.append('attachment_type', action.payload.attachment_type)
            if (action.payload.caption) form.append('caption', action.payload.caption)
            if (action.payload.gps) {
                const gps = action.payload.gps
                form.append('gps[latitude]', gps.latitude)
                form.append('gps[longitude]', gps.longitude)
                form.append('gps[accuracy]', gps.accuracy)
                if (gps.source) form.append('gps[source]', gps.source)
                if (gps.gps_timestamp) form.append('gps[gps_timestamp]', gps.gps_timestamp)
            }
            const r = await syncUpload(`work-orders/${wid}/media`, form, action.idempotency_key)
            return { classification: classify(r.status), response: r }
        }

        case 'SIGNATURE': {
            // Step 1: upload PNG blob via /media
            const form = new FormData()
            form.append('file', action.media_blob, `signature_${action.id}.png`)
            form.append('attachment_type', 'evidence')
            form.append('caption', 'Firma técnico')
            const r1 = await syncUpload(`work-orders/${wid}/media`, form, action.idempotency_key)
            const c1 = classify(r1.status)
            if (c1 !== 'ok') return { classification: c1, response: r1 }

            // Step 2: record signature event — uses a separate idempotency key stored in payload
            const sigBody = { signature_type: action.payload.signature_type, notes: action.payload.notes }
            if (action.payload.gps) sigBody.gps = action.payload.gps
            const r2 = await syncPost(
                `work-orders/${wid}/signature`,
                sigBody,
                action.payload.sig_key,
            )
            return { classification: classify(r2.status), response: r2 }
        }

        case 'ALERT_RESOLVE': {
            const r = await syncPatch(`alerts/${action.alert_id}/resolve`)
            return { classification: classify(r.status), response: r }
        }

        case 'ALERT_DISMISS': {
            const r = await syncPatch(`alerts/${action.alert_id}/dismiss`)
            // 422 = cannot_dismiss_critical — treat as client_error (don't retry)
            return { classification: classify(r.status), response: r }
        }

        default:
            return { classification: 'client_error', response: { status: 400 } }
    }
}

/**
 * Removes synced records and permanently-failed/conflict records older than 7 days.
 * Runs on every sync cycle, even when there are no pending actions.
 */
async function purgeStaleActions() {
    const cutoff = new Date(Date.now() - 7 * 24 * 60 * 60 * 1000)
    await db.pendingActions
        .where('status').equals('synced')
        .filter(a => new Date(a.created_at) < cutoff)
        .delete()
    await db.pendingActions
        .where('status').anyOf(['failed', 'conflict'])
        .filter(a => new Date(a.created_at) < cutoff)
        .delete()
}

/**
 * Processes all pending actions in insertion order.
 * Returns an array of work_order_ids that had at least one action synced.
 */
export async function syncService({ authError } = {}) {
    // Always purge stale terminal records — even when there is nothing to sync
    await purgeStaleActions()

    // Snapshot pending IDs before starting — ignore actions added during this run
    const ids = await db.pendingActions.where('status').equals('pending').primaryKeys()
    if (ids.length === 0) return []

    await db.pendingActions.where('id').anyOf(ids).modify({ status: 'syncing' })

    const syncedWorkOrderIds = new Set()

    for (const id of ids) {
        const action = await db.pendingActions.get(id)
        if (!action || action.status !== 'syncing') continue

        const { classification, response } = await processAction(action)

        if (classification === 'auth_error') {
            // Rollback everything and abort — actions are precious
            await db.pendingActions.where('status').equals('syncing').modify({ status: 'pending' })
            if (authError) authError.value = true
            return [...syncedWorkOrderIds]
        }

        if (classification === 'ok') {
            await db.pendingActions.update(id, { status: 'synced', error_message: null })
            syncedWorkOrderIds.add(action.work_order_id)
            continue
        }

        if (classification === 'conflict') {
            const msg = await extractMessage(response)
            await db.pendingActions.update(id, { status: 'conflict', error_message: msg })
            continue
        }

        if (classification === 'client_error') {
            const msg = await extractMessage(response)
            await db.pendingActions.update(id, { status: 'failed', error_message: msg })
            continue
        }

        // network_error or server_error: increment retry counter
        const newCount = (action.retry_count ?? 0) + 1
        const maxRetries = classification === 'network_error' ? MAX_RETRIES_NETWORK : MAX_RETRIES_SERVER

        if (newCount >= maxRetries) {
            await db.pendingActions.update(id, {
                status: 'failed',
                retry_count: newCount,
                error_message: classification === 'network_error' ? 'Sin conexión' : 'Error del servidor',
            })
        } else {
            await db.pendingActions.update(id, { status: 'pending', retry_count: newCount })
        }
    }

    return [...syncedWorkOrderIds]
}
