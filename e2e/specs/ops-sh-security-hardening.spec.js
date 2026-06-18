/**
 * Sprint Security Hardening — E2E Tests
 *
 * Valida tres cambios de seguridad aplicados en este sprint:
 *
 *   SH-01: POST /api/v1/auth/refresh usa throttle:api-refresh (30/min)
 *          separado del throttle:api-tokens (5/min) para login.
 *   SH-02: Los endpoints de sub-recursos de WO (comments, time-entries, media, signature)
 *          usan ->middleware('idempotency') para deduplicación server-side.
 *   SH-03: Las fotos de WO se almacenan en el disco 'work_orders_private' (local, privado)
 *          y no son accesibles públicamente vía HTTP.
 *
 * Patrones adoptados de ops-13-mobile-offline-sync.spec.js.
 */
import { execSync } from 'child_process'
import { test, expect } from '@playwright/test'
import { BASE, OPS_CREDENTIALS } from '../helpers.js'

// ── Constantes ────────────────────────────────────────────────────────────────

const MOBILE_BASE = `${BASE}/mobile`
const WO_NUMBER = 'E2E-WO-0001'

// ── Tinker helpers ────────────────────────────────────────────────────────────

function tinkerCode(phpStatements) {
    try {
        return execSync(
            `php artisan tinker --execute "${phpStatements}"`,
            { encoding: 'utf8', stdio: ['pipe', 'pipe', 'ignore'] },
        ).replace(/[^\x20-\x7E-￿]/g, '').trim()
    } catch {
        return ''
    }
}

function getMobileToken(email, tenantSlug) {
    const abilities = "['work-orders.read','work-orders.write','equipment.read','maintenance-requests.read','maintenance-requests.write','inventory.read','plants.read','areas.read']"
    return tinkerCode(
        `$u=App\\Models\\User::where('email','${email}')->first();` +
        `$t=App\\Models\\Tenant::where('slug','${tenantSlug}')->first();` +
        `$r=$u->createToken('E2E SH',${abilities},now()->addDay());` +
        `$r->accessToken->forceFill(['tenant_id'=>$t->id])->save();` +
        `echo $r->plainTextToken;`,
    )
}

function tinker(phpExpr) {
    try {
        return execSync(
            `php artisan tinker --execute "echo ${phpExpr};"`,
            { encoding: 'utf8', stdio: ['pipe', 'pipe', 'ignore'] },
        )
    } catch {
        return ''
    }
}

function tinkerInt(phpExpr) {
    const out = tinker(phpExpr)
    const m = out.match(/\d+/)
    return m ? parseInt(m[0], 10) : 0
}

function tinkerString(phpExpr) {
    const out = tinker(phpExpr)
    return out.replace(/[^\x20-\x7E -￿]/g, '').trim()
}

function resolveWoId() {
    return tinkerString(
        `App\\Models\\WorkOrder::withoutGlobalScopes()->where('work_order_number','${WO_NUMBER}')->value('id')`,
    )
}

function countComments(woId) {
    return tinkerInt(
        `App\\Models\\WorkOrderComment::withoutGlobalScopes()->where('work_order_id','${woId}')->count()`,
    )
}

function countTimeEntries(woId) {
    return tinkerInt(
        `App\\Models\\WorkOrderTimeLog::withoutGlobalScopes()->where('work_order_id','${woId}')->count()`,
    )
}

function countMedia(woId) {
    return tinkerInt(
        `App\\Models\\WorkOrderAttachment::withoutGlobalScopes()->where('work_order_id','${woId}')->count()`,
    )
}

function countSignatures(woId) {
    return tinkerInt(
        `App\\Models\\WorkOrderSignature::withoutGlobalScopes()->where('work_order_id','${woId}')->count()`,
    )
}

function countSignaturesByNotes(woId, notes) {
    return tinkerInt(
        `App\\Models\\WorkOrderSignature::withoutGlobalScopes()->where('work_order_id','${woId}')->where('notes','${notes}')->count()`,
    )
}

// ── Browser helpers ───────────────────────────────────────────────────────────

async function loginMobile(page, creds = OPS_CREDENTIALS) {
    const token = MOBILE_TOKEN_A
    const tenantName = 'El Pajuil'

    await page.addInitScript(({ fakeToken, fakeTenantName }) => {
        const orig = window.fetch.bind(window)
        window.fetch = function (url, opts) {
            if (typeof url === 'string' && url.includes('/api/v1/auth/refresh')) {
                return Promise.resolve(new Response(
                    JSON.stringify({ token: fakeToken, user: { name: 'E2E' }, tenant: { name: fakeTenantName } }),
                    { status: 200, headers: { 'Content-Type': 'application/json' } },
                ))
            }
            return orig(url, opts)
        }
    }, { fakeToken: token, fakeTenantName: tenantName })

    await page.goto(`${MOBILE_BASE}/dashboard`, { waitUntil: 'domcontentloaded', timeout: 15_000 })

    await page.waitForFunction(
        () => !!document.querySelector('#app')?.__vue_app__?.config?.globalProperties?.$pinia?.state?.value?.auth?.token,
        { timeout: 10_000 },
    )
}

async function clearPendingActions(page) {
    await page.evaluate(async () => {
        await new Promise((resolve, reject) => {
            const req = indexedDB.open('PalmCoreDB')
            req.onsuccess = (e) => {
                const db = e.target.result
                const tx = db.transaction('pendingActions', 'readwrite')
                tx.objectStore('pendingActions').clear()
                tx.oncomplete = resolve
                tx.onerror = reject
            }
            req.onerror = reject
        })
    })
}

async function addPendingAction(page, fields, withBlob = false) {
    return page.evaluate(async ({ fields, withBlob }) => {
        const blob = withBlob
            ? new Blob(
                [new Uint8Array([
                    137, 80, 78, 71, 13, 10, 26, 10, 0, 0, 0, 13, 73, 72, 68, 82,
                    0, 0, 0, 1, 0, 0, 0, 1, 8, 2, 0, 0, 0, 144, 119, 83, 222, 0,
                    0, 0, 12, 73, 68, 65, 84, 8, 215, 99, 248, 207, 192, 0, 0, 0,
                    2, 0, 1, 226, 33, 188, 51, 0, 0, 0, 0, 73, 69, 78, 68, 174, 66,
                    96, 130,
                ])],
                { type: 'image/png' },
              )
            : null

        const record = {
            created_at: new Date(),
            retry_count: 0,
            status: 'pending',
            error_message: null,
            media_blob: blob,
            ...fields,
        }

        return new Promise((resolve, reject) => {
            const req = indexedDB.open('PalmCoreDB')
            req.onsuccess = (e) => {
                const db = e.target.result
                const tx = db.transaction('pendingActions', 'readwrite')
                const addReq = tx.objectStore('pendingActions').add(record)
                addReq.onsuccess = () => resolve(addReq.result)
                addReq.onerror = reject
            }
            req.onerror = reject
        })
    }, { fields, withBlob })
}

async function waitForSyncComplete(page, timeoutMs = 20_000) {
    const deadline = Date.now() + timeoutMs
    while (Date.now() < deadline) {
        const counts = await page.evaluate(async () => {
            return new Promise((resolve) => {
                const req = indexedDB.open('PalmCoreDB')
                req.onsuccess = (e) => {
                    const db = e.target.result
                    const tx = db.transaction('pendingActions', 'readonly')
                    const store = tx.objectStore('pendingActions')
                    const all = store.getAll()
                    all.onsuccess = () => {
                        const rows = all.result
                        resolve({
                            pending: rows.filter(r => r.status === 'pending').length,
                            syncing: rows.filter(r => r.status === 'syncing').length,
                            synced: rows.filter(r => r.status === 'synced').length,
                            failed: rows.filter(r => r.status === 'failed').length,
                            conflict: rows.filter(r => r.status === 'conflict').length,
                        })
                    }
                    all.onerror = () => resolve({ pending: 0, syncing: 0 })
                }
                req.onerror = () => resolve({ pending: 0, syncing: 0 })
            })
        })
        if (counts.pending === 0 && counts.syncing === 0) return counts
        await page.waitForTimeout(300)
    }
    throw new Error('waitForSyncComplete: timeout esperando que la cola se vacíe')
}

function setupErrorListeners(page) {
    const errors = []
    page.on('pageerror', (e) => {
        const msg = e.message ?? ''
        if (/Unable to preload CSS|Failed to fetch dynamically imported module/i.test(msg)) return
        errors.push(`pageerror: ${msg}`)
    })
    page.on('console', (msg) => {
        if (msg.type() !== 'error') return
        const text = msg.text()
        if (/favicon|manifest|Failed to load resource|net::ERR/i.test(text)) return
        if (/Content Security Policy/i.test(text)) return
        if (/ResizeObserver loop/i.test(text)) return
        if (/Unable to preload CSS|Failed to fetch dynamically imported module/i.test(text)) return
        errors.push(`console: ${text}`)
    })
    return errors
}

// ── Pre-obtained tokens ───────────────────────────────────────────────────────

const MOBILE_TOKEN_A = getMobileToken('admin@elpajuil.demo', 'el-pajuil')

// ── Resolve IDs at module load ────────────────────────────────────────────────

const WO_ID = resolveWoId()

// ══════════════════════════════════════════════════════════════════════════════
// Security Hardening Tests
// ══════════════════════════════════════════════════════════════════════════════

test.describe.serial('Security Hardening — SH-01 al SH-06', () => {
    test.beforeAll(() => {
        if (!WO_ID) throw new Error(`WO ${WO_NUMBER} no encontrada — verificar E2EDataSeeder`)
        if (!MOBILE_TOKEN_A) throw new Error('MOBILE_TOKEN_A no obtenido — verificar tinker/DB')
    })

    // ── SH-1: El refresh no consume el rate limit del login ────────────────────

    test('SH-1: refresh no dispara rate limit de login', async ({ page }) => {
        // Navigate to mobile login to establish origin for fetch (same-origin context).
        // We bypass the fetch interceptor by wrapping calls in page.evaluate() which
        // uses the browser's real fetch directly against the server.
        await page.goto(`${MOBILE_BASE}/login`, { waitUntil: 'domcontentloaded', timeout: 15_000 })

        // Fire 6 rapid POST /api/v1/auth/refresh requests (limit is 30/min — all must pass).
        const refreshResults = await page.evaluate(async (base) => {
            const results = []
            for (let i = 0; i < 6; i++) {
                try {
                    const res = await fetch(`${base}/api/v1/auth/refresh`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                        credentials: 'include',
                    })
                    results.push(res.status)
                } catch {
                    results.push(0) // network error
                }
            }
            return results
        }, BASE)

        // None of the 6 refresh calls should be rate-limited (429).
        // The endpoint will return 401 because there is no valid cookie — that is OK.
        // What we verify is that the refresh limiter (30/min) is NOT the login limiter (5/min).
        for (const status of refreshResults) {
            expect(status, `Refresh call returned ${status} — should not be 429`).not.toBe(429)
        }

        // Now verify that POST /api/v1/tokens (login) is on its own separate limiter and
        // returns 201 (not blocked by the refresh calls above).
        const loginStatus = await page.evaluate(async (base) => {
            try {
                const res = await fetch(`${base}/api/v1/tokens`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({
                        tenant_slug: 'el-pajuil',
                        email: 'admin@elpajuil.demo',
                        password: 'password',
                        token_name: 'E2E SH-1',
                    }),
                })
                return res.status
            } catch {
                return 0
            }
        }, BASE)

        expect(loginStatus, 'POST /api/v1/tokens debe retornar 201 (no bloqueado por llamadas al refresh)').toBe(201)
    })

    // ── SH-2: Comentarios offline no se duplican ───────────────────────────────

    test('SH-2: comentarios offline no se duplican (idempotency middleware)', async ({ page }) => {
        const errors = setupErrorListeners(page)
        await loginMobile(page)
        await clearPendingActions(page)

        const commentsBefore = countComments(WO_ID)

        const idempotencyKey = await page.evaluate(() => crypto.randomUUID())

        // Go offline, insert the SAME comment action TWICE with the same idempotency_key
        await page.context().setOffline(true)
        await addPendingAction(page, {
            action_type: 'COMMENT',
            work_order_id: WO_ID,
            payload: { body: '[E2E] SH-2 idempotency comment', is_internal: false },
            idempotency_key: idempotencyKey,
        })
        await addPendingAction(page, {
            action_type: 'COMMENT',
            work_order_id: WO_ID,
            payload: { body: '[E2E] SH-2 idempotency comment', is_internal: false },
            idempotency_key: idempotencyKey,
        })

        // Reconnect and sync
        await page.context().setOffline(false)
        await page.evaluate(() => window.dispatchEvent(new Event('online')))
        await waitForSyncComplete(page)

        // The idempotency middleware must deduplicate: server should have exactly 1 new comment
        const commentsAfter = countComments(WO_ID)
        expect(
            commentsAfter - commentsBefore,
            'La idempotencia debe evitar duplicados: solo debe crearse 1 comentario en el servidor',
        ).toBe(1)

        expect(errors).toEqual([])
    })

    // ── SH-3: Registros de tiempo offline no se duplican ──────────────────────

    test('SH-3: registros de tiempo offline no se duplican (idempotency middleware)', async ({ page }) => {
        const errors = setupErrorListeners(page)
        await loginMobile(page)
        await clearPendingActions(page)

        const before = countTimeEntries(WO_ID)

        const idempotencyKey = await page.evaluate(() => crypto.randomUUID())
        const now = new Date().toISOString()
        const end = new Date(Date.now() + 30 * 60 * 1000).toISOString()

        await page.context().setOffline(true)
        await addPendingAction(page, {
            action_type: 'TIME_ENTRY',
            work_order_id: WO_ID,
            payload: { started_at: now, ended_at: end, description: '[E2E] SH-3 idempotency time entry' },
            idempotency_key: idempotencyKey,
        })
        // Duplicate with the same key
        await addPendingAction(page, {
            action_type: 'TIME_ENTRY',
            work_order_id: WO_ID,
            payload: { started_at: now, ended_at: end, description: '[E2E] SH-3 idempotency time entry' },
            idempotency_key: idempotencyKey,
        })

        await page.context().setOffline(false)
        await page.evaluate(() => window.dispatchEvent(new Event('online')))
        await waitForSyncComplete(page)

        const after = countTimeEntries(WO_ID)
        expect(
            after - before,
            'La idempotencia debe evitar duplicados: solo debe crearse 1 time entry en el servidor',
        ).toBe(1)

        expect(errors).toEqual([])
    })

    // ── SH-4: Fotos offline no se duplican ────────────────────────────────────

    test('SH-4: fotos offline no se duplican (idempotency middleware)', async ({ page }) => {
        const errors = setupErrorListeners(page)
        await loginMobile(page)
        await clearPendingActions(page)

        const before = countMedia(WO_ID)

        const idempotencyKey = await page.evaluate(() => crypto.randomUUID())

        await page.context().setOffline(true)
        await addPendingAction(page, {
            action_type: 'MEDIA_UPLOAD',
            work_order_id: WO_ID,
            payload: { attachment_type: 'evidence', caption: '[E2E] SH-4 idempotency media' },
            idempotency_key: idempotencyKey,
        }, true /* withBlob */)
        // Duplicate with the same key
        await addPendingAction(page, {
            action_type: 'MEDIA_UPLOAD',
            work_order_id: WO_ID,
            payload: { attachment_type: 'evidence', caption: '[E2E] SH-4 idempotency media' },
            idempotency_key: idempotencyKey,
        }, true /* withBlob */)

        await page.context().setOffline(false)
        await page.evaluate(() => window.dispatchEvent(new Event('online')))
        await waitForSyncComplete(page)

        const after = countMedia(WO_ID)
        // The idempotency middleware excludes the multipart body from the fingerprint,
        // so retries with a different boundary hash to the same key, ensuring exact dedup.
        expect(
            after - before,
            'La idempotencia debe evitar duplicados: solo debe crearse 1 attachment en el servidor',
        ).toBe(1)

        expect(errors).toEqual([])
    })

    // ── SH-5: Firmas offline no se duplican ───────────────────────────────────

    test('SH-5: firmas offline no se duplican (idempotency middleware)', async ({ page }) => {
        // SIGNATURE sync makes 2 requests:
        //   1. POST /media with idempotency_key = action.idempotency_key (uploads PNG blob, multipart)
        //   2. POST /signature with idempotency_key = action.payload.sig_key (records event, JSON)
        // Both are deduplicated exactly: multipart fingerprint excludes body, JSON fingerprint includes body.
        const errors = setupErrorListeners(page)
        await loginMobile(page)
        await clearPendingActions(page)

        const mediaBefore = countMedia(WO_ID)
        const sigsBefore = countSignatures(WO_ID)

        const idempotencyKey = await page.evaluate(() => crypto.randomUUID())
        const sigKey = await page.evaluate(() => crypto.randomUUID())

        await page.context().setOffline(true)
        await addPendingAction(page, {
            action_type: 'SIGNATURE',
            work_order_id: WO_ID,
            payload: {
                signature_type: 'technician_completion',
                notes: '[E2E] SH-5 idempotency signature',
                sig_key: sigKey,
            },
            idempotency_key: idempotencyKey,
        }, true /* withBlob */)
        // Duplicate with the same keys
        await addPendingAction(page, {
            action_type: 'SIGNATURE',
            work_order_id: WO_ID,
            payload: {
                signature_type: 'technician_completion',
                notes: '[E2E] SH-5 idempotency signature',
                sig_key: sigKey,
            },
            idempotency_key: idempotencyKey,
        }, true /* withBlob */)

        await page.context().setOffline(false)
        await page.evaluate(() => window.dispatchEvent(new Event('online')))
        await waitForSyncComplete(page)

        const mediaAfter = countMedia(WO_ID)
        const sigsAfter = countSignatures(WO_ID)
        const sigMatchCount = countSignaturesByNotes(WO_ID, '[E2E] SH-5 idempotency signature')

        // Step 2 (signature record) uses JSON — idempotency_key=sig_key deduplication is exact.
        // addSignature uses updateOrCreate keyed by signature_type, so the count may stay the same
        // if a signature of this type already exists (it updates rather than inserts). What matters
        // is that exactly 1 signature record holds the SH-5 notes (not 0, not 2+).
        expect(
            sigMatchCount,
            'Debe existir exactamente 1 firma con las notas de SH-5 (no duplicada por idempotencia)',
        ).toBe(1)

        // The overall count must not have grown by more than 1 (guards against an insert-path duplicate).
        expect(
            sigsAfter - sigsBefore,
            'El conteo de firmas no debe crecer más de 1 aunque se envíen 2 acciones duplicadas',
        ).toBeLessThanOrEqual(1)

        // Step 1 (media upload) uses multipart — middleware excludes body from fingerprint, ensuring exact dedup.
        expect(
            mediaAfter - mediaBefore,
            'El attachment de firma (PNG) no debe duplicarse: idempotency excluye el body multipart del fingerprint',
        ).toBe(1)

        expect(errors).toEqual([])
    })

    // ── SH-6: Evidencias privadas no son accesibles públicamente ──────────────

    test('SH-6: evidencias privadas no son accesibles públicamente (disco work_orders_private)', async ({ page }) => {
        const errors = setupErrorListeners(page)
        await loginMobile(page)
        await clearPendingActions(page)

        const mediaBefore = countMedia(WO_ID)

        // Upload one media attachment via offline sync
        await page.context().setOffline(true)
        await addPendingAction(page, {
            action_type: 'MEDIA_UPLOAD',
            work_order_id: WO_ID,
            payload: { attachment_type: 'evidence', caption: '[E2E] SH-6 private media' },
            idempotency_key: await page.evaluate(() => crypto.randomUUID()),
        }, true /* withBlob */)

        await page.context().setOffline(false)
        await page.evaluate(() => window.dispatchEvent(new Event('online')))
        await waitForSyncComplete(page)

        // Verify that a new attachment was created server-side
        const mediaAfter = countMedia(WO_ID)
        expect(mediaAfter - mediaBefore, 'Debe haberse creado al menos 1 attachment').toBeGreaterThanOrEqual(1)

        // Retrieve the file_path stored in the DB for the latest attachment
        const filePath = tinkerString(
            `App\\Models\\WorkOrderAttachment::withoutGlobalScopes()->where('work_order_id','${WO_ID}')->latest()->value('file_path')`,
        )
        expect(filePath, 'file_path del attachment debe existir en la BD').toBeTruthy()

        // Attempt to access the file via the public storage URL (would work if disk='public').
        // The work_orders_private disk stores at storage/app/private/work-orders — NOT under
        // public_path('storage'), so both URLs should return 404.
        const urlsToTry = [
            `${BASE}/storage/${filePath}`,
            `${BASE}/storage/work-orders/${filePath}`,
        ]

        for (const url of urlsToTry) {
            const status = await page.evaluate(async (targetUrl) => {
                try {
                    // Use no-cors to force an actual request even if CORS blocks it.
                    // We only care about whether the server exposes the file (200 vs 404/403).
                    const res = await fetch(targetUrl, { method: 'GET', credentials: 'omit' })
                    return res.status
                } catch {
                    return 0 // Network error = definitely not accessible
                }
            }, url)

            // INFO: If there is no download route, unauthenticated access should return 404 or 403.
            // A 200 here would mean the private file is publicly accessible — a security breach.
            expect(
                status,
                `El archivo privado NO debe ser accesible públicamente. URL: ${url} retornó ${status}`,
            ).not.toBe(200)
        }

        // INFO: There is currently no authenticated download route for work order media.
        // An authenticated API endpoint (e.g. GET /api/v1/work-orders/{id}/media/{attachment})
        // should be added in a future sprint so that the mobile app can render stored images.
        // For now we only verify the negative: unauthenticated access is blocked.

        expect(errors).toEqual([])
    })
})
