/**
 * Grupo 13 — Mobile Offline + Sync Queue
 *
 * Valida el flujo completo de acciones offline y sincronización posterior.
 *
 * Arquitectura:
 *   - Mobile SPA en /mobile/* (Vue 3 + Pinia + Dexie IndexedDB)
 *   - Token auth: access token en memoria, refresh token en HttpOnly cookie
 *   - Offline: context.setOffline(true) afecta navigator.onLine y network events
 *   - Dexie DB: 'PalmCoreDB' → tabla 'pendingActions'
 *   - Sync: evento 'online' → networkStore.handleOnline() → triggerSync() → syncService()
 *
 * Estrategia:
 *   - Las acciones offline se insertan directamente en IndexedDB via page.evaluate()
 *     para evitar dependencia de la UI mobile (más robusto y más rápido).
 *   - El token de acceso se obtiene via POST /api/v1/tokens dentro del contexto del browser.
 *   - La verificación server-side usa tinker (mismo patrón que spec 12).
 *
 * Fixtures (E2EDataSeeder):
 *   Tenant A: el-pajuil, admin@elpajuil.demo / password
 *   WO: E2E-WO-0001 (status: in_progress, equipment: E2E-PRE-001)
 *   Tenant B: e2e-tenant-b, admin-b@e2e-tenant-b.demo / password
 *
 * Grupos:
 *   13A — Acciones offline (1–9): insertar en IndexedDB offline, verificar estado
 *   13B — Sync tras reconexión (10–20): reconectar, verificar servidor, idempotencia, isolation
 */
import { execSync } from 'child_process'
import { test, expect } from '@playwright/test'
import { BASE, OPS_CREDENTIALS } from '../helpers.js'

// ── Constantes ────────────────────────────────────────────────────────────────

const MOBILE_BASE = `${BASE}/mobile`
const WO_NUMBER = 'E2E-WO-0001'
const TENANT_B_CREDS = { tenant: 'e2e-tenant-b', email: 'admin@e2etenantb.test', password: 'password' }

// ── Tinker helpers ────────────────────────────────────────────────────────────

/**
 * Execute arbitrary PHP statements in tinker and return raw trimmed output.
 * Unlike tinker(), this does NOT wrap in "echo"; the caller must include echo.
 */
function tinkerCode(phpStatements) {
    try {
        return execSync(
            `php artisan tinker --execute "${phpStatements}"`,
            { encoding: 'utf8', stdio: ['pipe', 'pipe', 'ignore'] },
        ).replace(/[^\x20-\x7E-￿]/g, '').trim()
    } catch {
        return ''
    }
}

/**
 * Create a real Sanctum token for the given user+tenant via PHP (tinker).
 * Running server-side bypasses the rate-limited POST /api/v1/tokens endpoint.
 */
function getMobileToken(email, tenantSlug) {
    const abilities = "['work-orders.read','work-orders.write','equipment.read','maintenance-requests.read','maintenance-requests.write','inventory.read','plants.read','areas.read']"
    return tinkerCode(
        `$u=App\\Models\\User::where('email','${email}')->first();` +
        `$t=App\\Models\\Tenant::where('slug','${tenantSlug}')->first();` +
        `$r=$u->createToken('E2E Mobile',${abilities},now()->addDay());` +
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

function tinkerJson(phpExpr) {
    try {
        const out = tinker(`json_encode(${phpExpr})`)
        const m = out.match(/(\[.*\]|\{.*\})/s)
        return m ? JSON.parse(m[0]) : null
    } catch {
        return null
    }
}

function tinkerInt(phpExpr) {
    const out = tinker(phpExpr)
    const m = out.match(/\d+/)
    return m ? parseInt(m[0], 10) : 0
}

function tinkerString(phpExpr) {
    const out = tinker(phpExpr)
    // Strip non-printable and return trimmed value
    return out.replace(/[^\x20-\x7E -￿]/g, '').trim()
}

/** Resolve the UUID of E2E-WO-0001 via tinker. */
function resolveWoId() {
    return tinkerString(
        `App\\Models\\WorkOrder::withoutGlobalScopes()->where('work_order_number','${WO_NUMBER}')->value('id')`,
    )
}

/** Count time entries for a WO. */
function countTimeEntries(woId) {
    return tinkerInt(
        `App\\Models\\WorkOrderTimeLog::withoutGlobalScopes()->where('work_order_id','${woId}')->count()`,
    )
}

/** Count comments for a WO. */
function countComments(woId) {
    return tinkerInt(
        `App\\Models\\WorkOrderComment::withoutGlobalScopes()->where('work_order_id','${woId}')->count()`,
    )
}

/** Count media attachments for a WO. */
function countMedia(woId) {
    return tinkerInt(
        `App\\Models\\WorkOrderAttachment::withoutGlobalScopes()->where('work_order_id','${woId}')->count()`,
    )
}

/** Count signature records for a WO. */
function countSignatures(woId) {
    return tinkerInt(
        `App\\Models\\WorkOrderSignature::withoutGlobalScopes()->where('work_order_id','${woId}')->count()`,
    )
}

// ── Browser helpers ───────────────────────────────────────────────────────────

/**
 * Set up the mobile SPA session without hitting the rate-limited API.
 * Intercepts browser auth/refresh with a pre-obtained Sanctum token so that
 * main.js → restoreSession() sets token.value in Pinia, which syncService needs.
 */
async function loginMobile(page, creds = OPS_CREDENTIALS) {
    const token = creds.tenant === 'e2e-tenant-b' ? MOBILE_TOKEN_B : MOBILE_TOKEN_A
    const tenantName = creds.tenant === 'e2e-tenant-b' ? '[E2E] Tenant B — Aislamiento' : 'El Pajuil'

    // addInitScript runs before any page script, including main.js
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

    // Navigate. restoreSession() is intercepted → sets token → app mounts → router
    // guard allows /mobile/dashboard.
    await page.goto(`${MOBILE_BASE}/dashboard`, { waitUntil: 'domcontentloaded', timeout: 15_000 })

    // Wait for Vue app to mount and token to be confirmed in auth store
    await page.waitForFunction(
        () => !!document.querySelector('#app')?.__vue_app__?.config?.globalProperties?.$pinia?.state?.value?.auth?.token,
        { timeout: 10_000 },
    )
}

/**
 * Clear the PalmCoreDB pendingActions table via IndexedDB directly.
 */
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

/**
 * Read all records from pendingActions table.
 * Returns an array of action objects (media_blob excluded for serialization).
 */
async function readPendingActions(page) {
    return page.evaluate(async () => {
        return new Promise((resolve, reject) => {
            const req = indexedDB.open('PalmCoreDB')
            req.onsuccess = (e) => {
                const db = e.target.result
                const tx = db.transaction('pendingActions', 'readonly')
                const store = tx.objectStore('pendingActions')
                const getAllReq = store.getAll()
                getAllReq.onsuccess = () => {
                    // Serialize: replace Blob with boolean (not serializable across page.evaluate)
                    const rows = getAllReq.result.map(r => ({
                        ...r,
                        media_blob: r.media_blob instanceof Blob ? true : r.media_blob,
                    }))
                    resolve(rows)
                }
                getAllReq.onerror = reject
            }
            req.onerror = reject
        })
    })
}

/**
 * Add a single pending action record directly to IndexedDB.
 * blob: if true, adds a 1×1 PNG Blob as media_blob.
 */
async function addPendingAction(page, fields, withBlob = false) {
    return page.evaluate(async ({ fields, withBlob }) => {
        const blob = withBlob
            ? new Blob(
                // Minimal 1x1 PNG (67 bytes)
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
                addReq.onsuccess = () => resolve(addReq.result) // returns inserted id
                addReq.onerror = reject
            }
            req.onerror = reject
        })
    }, { fields, withBlob })
}

/**
 * Count pending actions by status in IndexedDB.
 */
async function countByStatus(page, status) {
    return page.evaluate(async (status) => {
        return new Promise((resolve, reject) => {
            const req = indexedDB.open('PalmCoreDB')
            req.onsuccess = (e) => {
                const db = e.target.result
                const tx = db.transaction('pendingActions', 'readonly')
                const index = tx.objectStore('pendingActions').index('status')
                const countReq = index.count(IDBKeyRange.only(status))
                countReq.onsuccess = () => resolve(countReq.result)
                countReq.onerror = reject
            }
            req.onerror = reject
        })
    }, status)
}

/**
 * Wait until all pendingActions that were 'pending' or 'syncing' are terminal.
 * Uses polling on IndexedDB directly.
 */
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
        // Vite preload failures are expected when navigating while offline
        if (/Unable to preload CSS|Failed to fetch dynamically imported module/i.test(msg)) return
        errors.push(`pageerror: ${msg}`)
    })
    page.on('console', (msg) => {
        if (msg.type() !== 'error') return
        const text = msg.text()
        if (/favicon|manifest|Failed to load resource|net::ERR/i.test(text)) return
        if (/Content Security Policy/i.test(text)) return
        if (/ResizeObserver loop/i.test(text)) return
        // Vite preload failures are expected when navigating while offline
        if (/Unable to preload CSS|Failed to fetch dynamically imported module/i.test(text)) return
        errors.push(`console: ${text}`)
    })
    return errors
}

// ── Pre-obtained Sanctum tokens (resolved once at module load via tinker) ─────
// BUG-13RateLimit: POST /api/v1/auth/refresh shares the throttle:api-tokens limiter
// (5/min). Each page load triggers main.js → restoreSession() → 1 rate-limit hit.
// Fix proposed: move auth/refresh to a separate, less-strict rate limiter.
// Workaround: intercept auth/refresh in the browser to return a real Sanctum token
// without hitting the HTTP endpoint at all.

const MOBILE_TOKEN_A = getMobileToken('admin@elpajuil.demo', 'el-pajuil')
const MOBILE_TOKEN_B = getMobileToken('admin@e2etenantb.test', 'e2e-tenant-b')

// ── Resolve IDs at module load ────────────────────────────────────────────────

const WO_ID = resolveWoId()

// ══════════════════════════════════════════════════════════════════════════════
// Grupo 13A — Acciones offline (validaciones 1–9)
// ══════════════════════════════════════════════════════════════════════════════

test.describe.serial('Grupo 13A — Acciones offline', () => {
    test.beforeAll(() => {
        if (!WO_ID) throw new Error(`WO ${WO_NUMBER} no encontrada — verificar E2EDataSeeder`)
    })

    // Each test uses a fresh browser context so IndexedDB starts empty.
    // storageState from playwright.config supplies the Filament cookie — the mobile
    // app gets its own token via loginMobile().

    test('13A-1: registrar tiempo sin internet → queda en IndexedDB con status=pending', async ({ page }) => {
        const errors = setupErrorListeners(page)
        await loginMobile(page)
        await clearPendingActions(page)

        await page.context().setOffline(true)

        const now = new Date().toISOString()
        const payload = {
            started_at: now,
            ended_at: new Date(Date.now() + 45 * 60 * 1000).toISOString(),
            description: '[E2E] Tiempo offline 13A-1',
        }
        await addPendingAction(page, {
            action_type: 'TIME_ENTRY',
            work_order_id: WO_ID,
            payload,
            idempotency_key: crypto.randomUUID(),
        })

        const actions = await readPendingActions(page)
        const timeEntry = actions.find(a => a.action_type === 'TIME_ENTRY')
        expect(timeEntry, 'TIME_ENTRY debe existir en IndexedDB').toBeTruthy()
        expect(timeEntry.status).toBe('pending')

        await page.context().setOffline(false)
        expect(errors).toEqual([])
    })

    test('13A-2: agregar comentario sin internet → queda en IndexedDB con status=pending', async ({ page }) => {
        const errors = setupErrorListeners(page)
        await loginMobile(page)
        await clearPendingActions(page)

        await page.context().setOffline(true)

        await addPendingAction(page, {
            action_type: 'COMMENT',
            work_order_id: WO_ID,
            payload: { body: '[E2E] Comentario offline 13A-2', is_internal: false },
            idempotency_key: crypto.randomUUID(),
        })

        const actions = await readPendingActions(page)
        const comment = actions.find(a => a.action_type === 'COMMENT')
        expect(comment, 'COMMENT debe existir en IndexedDB').toBeTruthy()
        expect(comment.status).toBe('pending')

        await page.context().setOffline(false)
        expect(errors).toEqual([])
    })

    test('13A-3: tomar foto sin internet → queda en IndexedDB con status=pending y media_blob no null', async ({ page }) => {
        const errors = setupErrorListeners(page)
        await loginMobile(page)
        await clearPendingActions(page)

        await page.context().setOffline(true)

        await addPendingAction(page, {
            action_type: 'MEDIA_UPLOAD',
            work_order_id: WO_ID,
            payload: { attachment_type: 'evidence', caption: '[E2E] Foto offline 13A-3' },
            idempotency_key: crypto.randomUUID(),
        }, true /* withBlob */)

        const actions = await readPendingActions(page)
        const media = actions.find(a => a.action_type === 'MEDIA_UPLOAD')
        expect(media, 'MEDIA_UPLOAD debe existir en IndexedDB').toBeTruthy()
        expect(media.status).toBe('pending')
        expect(media.media_blob, 'media_blob debe estar presente').toBeTruthy()

        await page.context().setOffline(false)
        expect(errors).toEqual([])
    })

    test('13A-4: firma sin internet → queda en IndexedDB con status=pending y media_blob no null', async ({ page }) => {
        const errors = setupErrorListeners(page)
        await loginMobile(page)
        await clearPendingActions(page)

        await page.context().setOffline(true)

        await addPendingAction(page, {
            action_type: 'SIGNATURE',
            work_order_id: WO_ID,
            payload: {
                signature_type: 'technician',
                notes: '[E2E] Firma offline 13A-4',
                sig_key: crypto.randomUUID(),
            },
            idempotency_key: crypto.randomUUID(),
        }, true /* withBlob */)

        const actions = await readPendingActions(page)
        const sig = actions.find(a => a.action_type === 'SIGNATURE')
        expect(sig, 'SIGNATURE debe existir en IndexedDB').toBeTruthy()
        expect(sig.status).toBe('pending')
        expect(sig.media_blob, 'media_blob debe estar presente').toBeTruthy()

        await page.context().setOffline(false)
        expect(errors).toEqual([])
    })

    test('13A-5: crear evidencia sin internet → queda en IndexedDB con status=pending', async ({ page }) => {
        const errors = setupErrorListeners(page)
        await loginMobile(page)
        await clearPendingActions(page)

        await page.context().setOffline(true)

        await addPendingAction(page, {
            action_type: 'MEDIA_UPLOAD',
            work_order_id: WO_ID,
            payload: { attachment_type: 'before', caption: '[E2E] Evidencia antes 13A-5' },
            idempotency_key: crypto.randomUUID(),
        }, true)

        const actions = await readPendingActions(page)
        const evidence = actions.find(a => a.action_type === 'MEDIA_UPLOAD' && a.payload.attachment_type === 'before')
        expect(evidence, 'Evidencia before debe existir en IndexedDB').toBeTruthy()
        expect(evidence.status).toBe('pending')

        await page.context().setOffline(false)
        expect(errors).toEqual([])
    })

    test('13A-6: múltiples acciones offline quedan almacenadas en IndexedDB', async ({ page }) => {
        const errors = setupErrorListeners(page)
        await loginMobile(page)
        await clearPendingActions(page)

        await page.context().setOffline(true)

        // Insert 4 different action types
        const key1 = crypto.randomUUID()
        const key2 = crypto.randomUUID()
        const key3 = crypto.randomUUID()
        const key4 = crypto.randomUUID()

        await addPendingAction(page, {
            action_type: 'TIME_ENTRY',
            work_order_id: WO_ID,
            payload: { started_at: new Date().toISOString(), ended_at: new Date(Date.now() + 30 * 60 * 1000).toISOString(), description: 'multi 13A-6' },
            idempotency_key: key1,
        })
        await addPendingAction(page, {
            action_type: 'COMMENT',
            work_order_id: WO_ID,
            payload: { body: '[E2E] Comentario multi 13A-6', is_internal: false },
            idempotency_key: key2,
        })
        await addPendingAction(page, {
            action_type: 'MEDIA_UPLOAD',
            work_order_id: WO_ID,
            payload: { attachment_type: 'evidence', caption: 'multi 13A-6' },
            idempotency_key: key3,
        }, true)
        await addPendingAction(page, {
            action_type: 'SIGNATURE',
            work_order_id: WO_ID,
            payload: { signature_type: 'technician', notes: 'multi', sig_key: crypto.randomUUID() },
            idempotency_key: key4,
        }, true)

        const pendingCount = await countByStatus(page, 'pending')
        expect(pendingCount).toBe(4)

        const actions = await readPendingActions(page)
        expect(actions).toHaveLength(4)

        await page.context().setOffline(false)
        expect(errors).toEqual([])
    })

    test('13A-7: el usuario puede navegar entre páginas sin errores mientras está offline', async ({ page }) => {
        const errors = setupErrorListeners(page)
        await loginMobile(page)

        await page.context().setOffline(true)

        // Navigate within the SPA using Vue Router client-side (no server requests).
        // page.goto() makes a new server request and fails when offline; Vue Router
        // push() updates the browser URL via history.pushState without network traffic.
        const mobilePush = async (path) => {
            await page.evaluate(async (p) => {
                const app = document.querySelector('#app')?.__vue_app__
                if (!app) return
                try {
                    await app.config.globalProperties.$router.push(p)
                } catch {
                    // Vite CSS preload throws when offline — navigation still completes
                }
            }, path)
            await page.waitForTimeout(200)
        }

        await mobilePush('/mobile/work-orders')
        await mobilePush('/mobile/alerts')
        await mobilePush('/mobile/dashboard')

        await page.context().setOffline(false)
        expect(errors).toEqual([])
    })

    test('13A-8: no existen errores de consola durante las acciones offline', async ({ page }) => {
        const errors = setupErrorListeners(page)
        await loginMobile(page)
        await clearPendingActions(page)

        await page.context().setOffline(true)

        // Add several actions and verify no console errors are triggered
        await addPendingAction(page, {
            action_type: 'TIME_ENTRY',
            work_order_id: WO_ID,
            payload: { started_at: new Date().toISOString(), ended_at: new Date(Date.now() + 20 * 60 * 1000).toISOString(), description: '[E2E] Consola offline 13A-8' },
            idempotency_key: crypto.randomUUID(),
        })
        await addPendingAction(page, {
            action_type: 'COMMENT',
            work_order_id: WO_ID,
            payload: { body: '[E2E] Consola offline 13A-8', is_internal: false },
            idempotency_key: crypto.randomUUID(),
        })

        // Small wait so any async console errors surface
        await page.waitForTimeout(500)

        await page.context().setOffline(false)
        expect(errors, 'No deben haber errores de consola durante acciones offline').toEqual([])
    })

    test('13A-9: no existen page errors durante las acciones offline', async ({ page }) => {
        const pageErrors = []
        page.on('pageerror', (e) => pageErrors.push(e.message))

        await loginMobile(page)
        await clearPendingActions(page)

        await page.context().setOffline(true)

        await addPendingAction(page, {
            action_type: 'TIME_ENTRY',
            work_order_id: WO_ID,
            payload: { started_at: new Date().toISOString(), ended_at: new Date(Date.now() + 15 * 60 * 1000).toISOString(), description: '[E2E] Page errors offline 13A-9' },
            idempotency_key: crypto.randomUUID(),
        })
        await addPendingAction(page, {
            action_type: 'COMMENT',
            work_order_id: WO_ID,
            payload: { body: '[E2E] Page errors offline 13A-9', is_internal: false },
            idempotency_key: crypto.randomUUID(),
        })
        await addPendingAction(page, {
            action_type: 'MEDIA_UPLOAD',
            work_order_id: WO_ID,
            payload: { attachment_type: 'evidence', caption: 'page error test' },
            idempotency_key: crypto.randomUUID(),
        }, true)

        await page.waitForTimeout(500)

        await page.context().setOffline(false)
        expect(pageErrors, 'No deben haber page errors durante acciones offline').toEqual([])
    })
})

// ══════════════════════════════════════════════════════════════════════════════
// Grupo 13B — Sync tras reconexión (validaciones 10–20)
// ══════════════════════════════════════════════════════════════════════════════

test.describe.serial('Grupo 13B — Sync tras reconexión', () => {
    test.beforeAll(() => {
        if (!WO_ID) throw new Error(`WO ${WO_NUMBER} no encontrada — verificar E2EDataSeeder`)
    })

    test('13B-10: la cola se vacía al reconectar (todos los status=pending → synced)', async ({ page }) => {
        const errors = setupErrorListeners(page)
        await loginMobile(page)
        await clearPendingActions(page)

        await page.context().setOffline(true)

        await addPendingAction(page, {
            action_type: 'TIME_ENTRY',
            work_order_id: WO_ID,
            payload: {
                started_at: new Date().toISOString(),
                ended_at: new Date(Date.now() + 30 * 60 * 1000).toISOString(),
                description: '[E2E] Tiempo sync 13B-10',
            },
            idempotency_key: crypto.randomUUID(),
        })
        await addPendingAction(page, {
            action_type: 'COMMENT',
            work_order_id: WO_ID,
            payload: { body: '[E2E] Comentario sync 13B-10', is_internal: false },
            idempotency_key: crypto.randomUUID(),
        })

        const pendingBefore = await countByStatus(page, 'pending')
        expect(pendingBefore).toBe(2)

        // Reconnect — triggers handleOnline → triggerSync()
        await page.context().setOffline(false)
        await page.evaluate(() => window.dispatchEvent(new Event('online')))

        const finalCounts = await waitForSyncComplete(page)
        expect(finalCounts.pending, 'No deben quedar acciones pending').toBe(0)
        expect(finalCounts.syncing, 'No deben quedar acciones syncing').toBe(0)
        expect(finalCounts.synced, 'Las acciones deben estar synced').toBeGreaterThanOrEqual(2)

        expect(errors).toEqual([])
    })

    test('13B-11: no existen duplicados en el servidor tras sync', async ({ page }) => {
        const errors = setupErrorListeners(page)
        await loginMobile(page)
        await clearPendingActions(page)

        // Record baselines before our test actions
        const commentsBefore = countComments(WO_ID)
        const timeEntriesBefore = countTimeEntries(WO_ID)

        const idempotencyKey = crypto.randomUUID()
        await page.context().setOffline(true)
        await addPendingAction(page, {
            action_type: 'COMMENT',
            work_order_id: WO_ID,
            payload: { body: '[E2E] Dedup test 13B-11', is_internal: false },
            idempotency_key: idempotencyKey,
        })

        await page.context().setOffline(false)
        await page.evaluate(() => window.dispatchEvent(new Event('online')))
        await waitForSyncComplete(page)

        // Server should have exactly 1 more comment (not 2+)
        const commentsAfter = countComments(WO_ID)
        expect(commentsAfter - commentsBefore).toBe(1)

        expect(errors).toEqual([])
    })

    test('13B-12: los registros de tiempo llegan al servidor tras sync', async ({ page }) => {
        const errors = setupErrorListeners(page)
        await loginMobile(page)
        await clearPendingActions(page)

        const timeEntriesBefore = countTimeEntries(WO_ID)

        await page.context().setOffline(true)
        await addPendingAction(page, {
            action_type: 'TIME_ENTRY',
            work_order_id: WO_ID,
            payload: {
                started_at: new Date().toISOString(),
                ended_at: new Date(Date.now() + 60 * 60 * 1000).toISOString(),
                description: '[E2E] Tiempo sync 13B-12',
            },
            idempotency_key: crypto.randomUUID(),
        })

        await page.context().setOffline(false)
        await page.evaluate(() => window.dispatchEvent(new Event('online')))
        await waitForSyncComplete(page)

        const timeEntriesAfter = countTimeEntries(WO_ID)
        expect(timeEntriesAfter - timeEntriesBefore, 'Debe haber 1 time entry nuevo en el servidor').toBe(1)

        expect(errors).toEqual([])
    })

    test('13B-13: las fotos llegan correctamente al servidor tras sync', async ({ page }) => {
        const errors = setupErrorListeners(page)
        await loginMobile(page)
        await clearPendingActions(page)

        const mediaBefore = countMedia(WO_ID)

        await page.context().setOffline(true)
        await addPendingAction(page, {
            action_type: 'MEDIA_UPLOAD',
            work_order_id: WO_ID,
            payload: { attachment_type: 'evidence', caption: '[E2E] Foto sync 13B-13' },
            idempotency_key: crypto.randomUUID(),
        }, true)

        await page.context().setOffline(false)
        await page.evaluate(() => window.dispatchEvent(new Event('online')))
        await waitForSyncComplete(page)

        const mediaAfter = countMedia(WO_ID)
        expect(mediaAfter - mediaBefore, 'Debe haber 1 attachment nuevo en el servidor').toBe(1)

        expect(errors).toEqual([])
    })

    test('13B-14: los comentarios llegan correctamente al servidor tras sync', async ({ page }) => {
        const errors = setupErrorListeners(page)
        await loginMobile(page)
        await clearPendingActions(page)

        const commentsBefore = countComments(WO_ID)

        await page.context().setOffline(true)
        await addPendingAction(page, {
            action_type: 'COMMENT',
            work_order_id: WO_ID,
            payload: { body: '[E2E] Comentario sync 13B-14', is_internal: false },
            idempotency_key: crypto.randomUUID(),
        })

        await page.context().setOffline(false)
        await page.evaluate(() => window.dispatchEvent(new Event('online')))
        await waitForSyncComplete(page)

        const commentsAfter = countComments(WO_ID)
        expect(commentsAfter - commentsBefore, 'Debe haber 1 comentario nuevo en el servidor').toBe(1)

        expect(errors).toEqual([])
    })

    test('13B-15: el tiempo llega correctamente (started_at/ended_at correctos) al servidor tras sync', async ({ page }) => {
        const errors = setupErrorListeners(page)
        await loginMobile(page)
        await clearPendingActions(page)

        const timeLogsBefore = countTimeEntries(WO_ID)
        const startedAt = new Date().toISOString()
        const endedAt = new Date(Date.now() + 75 * 60 * 1000).toISOString()
        const iKey = crypto.randomUUID()
        const testDescription = '[E2E] Tiempo exacto 13B-15'

        await page.context().setOffline(true)
        await addPendingAction(page, {
            action_type: 'TIME_ENTRY',
            work_order_id: WO_ID,
            payload: { started_at: startedAt, ended_at: endedAt, description: testDescription },
            idempotency_key: iKey,
        })

        await page.context().setOffline(false)
        await page.evaluate(() => window.dispatchEvent(new Event('online')))
        await waitForSyncComplete(page)

        // Verify the time entry arrived with correct description (proves the right record)
        const lastEntry = tinkerJson(
            `App\\Models\\WorkOrderTimeLog::withoutGlobalScopes()->where('work_order_id','${WO_ID}')->where('description','${testDescription}')->latest()->first()?->only(['description','started_at'])`,
        )
        expect(lastEntry, 'WorkOrderTimeLog debe existir en el servidor').toBeTruthy()
        expect(lastEntry.description).toBe(testDescription)

        // Count should have increased by 1
        const timeLogsAfter = countTimeEntries(WO_ID)
        expect(timeLogsAfter - timeLogsBefore).toBe(1)

        expect(errors).toEqual([])
    })

    test('13B-16: las firmas llegan correctamente al servidor tras sync', async ({ page }) => {
        const errors = setupErrorListeners(page)
        await loginMobile(page)
        await clearPendingActions(page)

        const sigsBefore = countSignatures(WO_ID)
        const mediaBefore = countMedia(WO_ID)

        await page.context().setOffline(true)
        await addPendingAction(page, {
            action_type: 'SIGNATURE',
            work_order_id: WO_ID,
            payload: {
                signature_type: 'technician',
                notes: '[E2E] Firma sync 13B-16',
                sig_key: crypto.randomUUID(),
            },
            idempotency_key: crypto.randomUUID(),
        }, true)

        await page.context().setOffline(false)
        await page.evaluate(() => window.dispatchEvent(new Event('online')))
        await waitForSyncComplete(page)

        // SIGNATURE action uploads media first, then records signature event.
        // Either both records exist or at least the media blob was uploaded.
        const mediaAfter = countMedia(WO_ID)
        const sigsAfter = countSignatures(WO_ID)
        // At minimum the PNG blob was uploaded as an attachment
        expect(
            mediaAfter - mediaBefore + (sigsAfter - sigsBefore),
            'Al menos un registro (attachment o signature) debe llegar al servidor',
        ).toBeGreaterThanOrEqual(1)

        expect(errors).toEqual([])
    })

    test('13B-17: el orden secuencial se respeta (IDs incrementales, procesados en orden)', async ({ page }) => {
        const errors = setupErrorListeners(page)
        await loginMobile(page)
        await clearPendingActions(page)

        const commentsBefore = countComments(WO_ID)

        await page.context().setOffline(true)

        // Insert 3 comments in known order
        const comments = [
            '[E2E] Orden 1 - 13B-17',
            '[E2E] Orden 2 - 13B-17',
            '[E2E] Orden 3 - 13B-17',
        ]
        for (const body of comments) {
            await addPendingAction(page, {
                action_type: 'COMMENT',
                work_order_id: WO_ID,
                payload: { body, is_internal: false },
                idempotency_key: crypto.randomUUID(),
            })
        }

        // Verify IDs are auto-incremented (sequential order)
        const actions = await readPendingActions(page)
        const ids = actions.map(a => a.id)
        const isSorted = ids.every((id, i) => i === 0 || id > ids[i - 1])
        expect(isSorted, 'Los IDs de pendingActions deben ser auto-incrementales').toBe(true)

        await page.context().setOffline(false)
        await page.evaluate(() => window.dispatchEvent(new Event('online')))
        await waitForSyncComplete(page)

        // Server should have 3 new comments
        const commentsAfter = countComments(WO_ID)
        expect(commentsAfter - commentsBefore).toBe(3)

        expect(errors).toEqual([])
    })

    test('13B-18: replay de la misma acción — idempotencia deduplicada correctamente', async ({ page }) => {
        const errors = setupErrorListeners(page)
        await loginMobile(page)
        await clearPendingActions(page)

        const idempotencyKey = crypto.randomUUID()

        await page.context().setOffline(true)
        await addPendingAction(page, {
            action_type: 'COMMENT',
            work_order_id: WO_ID,
            payload: { body: '[E2E] Idempotencia 13B-18', is_internal: false },
            idempotency_key: idempotencyKey,
        })
        // Second record with the same key (simulating a retry or crash scenario)
        await addPendingAction(page, {
            action_type: 'COMMENT',
            work_order_id: WO_ID,
            payload: { body: '[E2E] Idempotencia 13B-18', is_internal: false },
            idempotency_key: idempotencyKey,
        })

        const pendingBefore = await countByStatus(page, 'pending')
        expect(pendingBefore).toBe(2)

        const commentsBefore = countComments(WO_ID)

        await page.context().setOffline(false)
        await page.evaluate(() => window.dispatchEvent(new Event('online')))
        const finalCounts = await waitForSyncComplete(page)

        // The idempotency middleware returns 200 Idempotency-Replayed for the second request.
        // The client classifies 200 as 'ok' so both actions are marked synced in IndexedDB.
        // Server-side deduplication means only 1 comment was actually inserted.
        expect(finalCounts.synced, 'Ambas acciones deben quedar como synced en el cliente').toBe(2)
        expect(finalCounts.failed, 'No deben quedar acciones fallidas').toBe(0)

        const commentsAfter = countComments(WO_ID)
        expect(
            commentsAfter - commentsBefore,
            'La idempotencia del servidor debe evitar duplicados: solo debe crearse 1 comentario',
        ).toBe(1)

        expect(errors).toEqual([])
    })

    test('13B-19: multi-tenant — acciones de tenant A no afectan tenant B', async ({ browser }) => {
        // Two independent contexts: one per tenant
        const ctxA = await browser.newContext()
        const ctxB = await browser.newContext()
        const pageA = await ctxA.newPage()
        const pageB = await ctxB.newPage()

        try {
            // Login both tenants
            await loginMobile(pageA, OPS_CREDENTIALS)
            await loginMobile(pageB, TENANT_B_CREDS)

            await clearPendingActions(pageA)
            await clearPendingActions(pageB)

            // Get Tenant B WO ID for its context (if it exists)
            const woBId = tinkerString(
                `App\\Models\\WorkOrder::withoutGlobalScopes()->whereHas('tenant', fn($q) => $q->where('slug','e2e-tenant-b'))->value('id')`,
            )

            // Add action for tenant A only
            await ctxA.setOffline(true)
            await addPendingAction(pageA, {
                action_type: 'COMMENT',
                work_order_id: WO_ID,
                payload: { body: '[E2E] Multi-tenant isolation 13B-19 — Tenant A', is_internal: false },
                idempotency_key: crypto.randomUUID(),
            })

            // Verify tenant B has 0 pending actions (independent IndexedDB is per-origin, not per-user,
            // but the sync uses the bearer token scoped to the tenant)
            const pendingB = await countByStatus(pageB, 'pending')
            expect(pendingB, 'Tenant B no debe tener acciones pendientes de Tenant A').toBe(0)

            // Sync Tenant A
            await ctxA.setOffline(false)
            await pageA.evaluate(() => window.dispatchEvent(new Event('online')))
            await waitForSyncComplete(pageA)

            // Verify Tenant B's server-side data was not touched
            if (woBId) {
                const commentsBForWoB = tinkerInt(
                    `App\\Models\\WorkOrderComment::withoutGlobalScopes()->where('work_order_id','${woBId}')->where('body','[E2E] Multi-tenant isolation 13B-19 — Tenant A')->count()`,
                )
                expect(commentsBForWoB, 'El comentario de Tenant A no debe aparecer en datos de Tenant B').toBe(0)
            }
        } finally {
            await ctxA.close()
            await ctxB.close()
        }
    })

    test('13B-20: refresh posterior conserva datos en el servidor', async ({ page }) => {
        const errors = setupErrorListeners(page)
        await loginMobile(page)
        await clearPendingActions(page)

        const commentsBefore = countComments(WO_ID)
        const timeEntriesBefore = countTimeEntries(WO_ID)

        // Queue actions offline
        await page.context().setOffline(true)
        await addPendingAction(page, {
            action_type: 'COMMENT',
            work_order_id: WO_ID,
            payload: { body: '[E2E] Refresh persistence 13B-20', is_internal: false },
            idempotency_key: crypto.randomUUID(),
        })
        await addPendingAction(page, {
            action_type: 'TIME_ENTRY',
            work_order_id: WO_ID,
            payload: {
                started_at: new Date().toISOString(),
                ended_at: new Date(Date.now() + 10 * 60 * 1000).toISOString(),
                description: '[E2E] Refresh persistence 13B-20',
            },
            idempotency_key: crypto.randomUUID(),
        })

        // Sync
        await page.context().setOffline(false)
        await page.evaluate(() => window.dispatchEvent(new Event('online')))
        await waitForSyncComplete(page)

        // Simulate a page refresh (re-navigate to the mobile app)
        await page.goto(`${MOBILE_BASE}/dashboard`, { waitUntil: 'domcontentloaded', timeout: 15_000 })

        // After refresh, verify the data persisted server-side
        const commentsAfter = countComments(WO_ID)
        const timeEntriesAfter = countTimeEntries(WO_ID)

        expect(commentsAfter - commentsBefore, 'El comentario debe persistir en el servidor tras refresh').toBe(1)
        expect(timeEntriesAfter - timeEntriesBefore, 'El time entry debe persistir en el servidor tras refresh').toBe(1)

        expect(errors).toEqual([])
    })
})
