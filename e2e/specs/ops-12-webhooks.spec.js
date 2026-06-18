/**
 * Grupo 12 — Webhooks
 *
 * Valida el flujo completo del sistema de webhooks de extremo a extremo.
 *
 * Flujo validado:
 *   Evento de dominio → WebhookTriggerListener → WebhookDispatcher
 *   → DeliverWebhookJob (sync) → Http::fake() → webhook_delivery_logs
 *
 * Requisitos de infraestructura:
 *   - QUEUE_CONNECTION=sync        (playwright.config.js webServer.env)
 *   - FAKE_WEBHOOK_RESPONSES=true  (playwright.config.js webServer.env)
 *     → AppServiceProvider::configureE2EWebhookFakes():
 *         - SsrfValidator DNS resolver retorna IP pública para cualquier host
 *         - Http::fake() intercepta la entrega y responde 200 sin salir a la red
 *
 * Fixtures (E2EDataSeeder):
 *   Tenant A: suscripción → https://webhook.e2e.test/receive
 *   Tenant B: suscripción → https://webhook.e2e.test/receive-b
 *   E2E-WO-WEBHOOK: OT en estado 'verified' para el test de work_order.closed
 *   [E2E] Alerta para webhook test: alerta open para el test de alert.resolved
 *
 * Pruebas:
 *   12A — Suscripción visible en Filament y con eventos configurados
 *   12B — work_order.created: delivery log registrado con status='success'
 *   12C — work_order.closed: delivery log registrado con status='success'
 *   12D — maintenance_request.created: delivery log registrado con status='success'
 *   12E — alert.created: delivery log registrado con status='success'
 *   12F — alert.resolved: delivery log registrado con status='success'
 *   12G — Estructura del delivery log: campos event_name, status, http_status
 *   12H — Tenant isolation: suscripción de Tenant B tiene 0 logs tras eventos de Tenant A
 */
import { execSync } from 'child_process'
import { test, expect } from '@playwright/test'
import { adminUrl, confirmModal, PATHS, BASE } from '../helpers.js'

const E2E_WEBHOOK_URL = 'https://webhook.e2e.test/receive'
const E2E_WEBHOOK_URL_B = 'https://webhook.e2e.test/receive-b'

function setupErrorListeners(page) {
    const errors = []
    page.on('pageerror', (e) => {
        // Filament v5 sidebar navigation bug: $store.sidebar.groupIsCollapsed() calls
        // .includes() on a null collapsedGroups state during Alpine.js initialization.
        // This is a Filament-internal issue unrelated to our webhook behaviour.
        if (e.stack?.includes('groupIsCollapsed')) return
        errors.push(`pageerror: ${e.message}`)
    })
    page.on('console', (msg) => {
        if (msg.type() !== 'error') return
        const text = msg.text()
        if (/favicon|manifest|Failed to load resource|net::ERR/i.test(text)) return
        if (/Content Security Policy/i.test(text)) return
        errors.push(`console: ${text}`)
    })
    return errors
}

function tinkerUuid(phpExpr) {
    try {
        const out = execSync(
            `php artisan tinker --execute "echo ${phpExpr};"`,
            { encoding: 'utf8', stdio: ['pipe', 'pipe', 'ignore'] },
        )
        const m = out.match(/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i)
        return m?.[0] ?? null
    } catch {
        return null
    }
}

/**
 * Returns the most recent delivery log for the given event name, or null.
 * Fields: event_name, status, http_status, webhook_subscription_id
 */
function lastLog(eventName) {
    try {
        const out = execSync(
            `php artisan tinker --execute "echo json_encode(App\\Models\\WebhookDeliveryLog::withoutGlobalScopes()->where('event_name','${eventName}')->latest()->first()?->only(['event_name','status','http_status','webhook_subscription_id']));"`,
            { encoding: 'utf8', stdio: ['pipe', 'pipe', 'ignore'] },
        )
        const m = out.match(/\{[^}]+\}/)
        return m ? JSON.parse(m[0]) : null
    } catch {
        return null
    }
}

/** Returns the delivery log count for a given subscription ID. */
function countLogsForSub(subId) {
    try {
        const out = execSync(
            `php artisan tinker --execute "echo App\\Models\\WebhookDeliveryLog::withoutGlobalScopes()->where('webhook_subscription_id','${subId}')->count();"`,
            { encoding: 'utf8', stdio: ['pipe', 'pipe', 'ignore'] },
        )
        const m = out.match(/\d+/)
        return m ? parseInt(m[0], 10) : 0
    } catch {
        return 0
    }
}

// Resolve fixture IDs at module load (idempotent — seeder uses updateOrCreate)
const WO_WEBHOOK_ID = tinkerUuid(
    "App\\Models\\WorkOrder::withoutGlobalScopes()->where('work_order_number','E2E-WO-WEBHOOK')->value('id')",
)
const ALERT_WEBHOOK_ID = tinkerUuid(
    "App\\Models\\Alert::withoutGlobalScopes()->whereNull('deleted_at')->where('title','[E2E] Alerta para webhook test')->value('id')",
)
const SUB_A_ID = tinkerUuid(
    `App\\Models\\WebhookSubscription::withoutGlobalScopes()->where('url','${E2E_WEBHOOK_URL}')->value('id')`,
)
const SUB_B_ID = tinkerUuid(
    `App\\Models\\WebhookSubscription::withoutGlobalScopes()->where('url','${E2E_WEBHOOK_URL_B}')->value('id')`,
)

// ══════════════════════════════════════════════════════════════════════════════
// Grupo 12A — Suscripción visible en Filament
// ══════════════════════════════════════════════════════════════════════════════

test.describe.serial('Grupo 12A — Suscripción visible en Filament', () => {
    test('suscripción E2E aparece en la lista de integraciones con eventos configurados', async ({ page }) => {
        const errors = setupErrorListeners(page)

        await page.goto(adminUrl(PATHS.webhooks))
        await expect(page.getByText(E2E_WEBHOOK_URL, { exact: false })).toBeVisible({ timeout: 10_000 })

        // Navigate to the subscription view page via the Ver action link in the table row.
        // Clicking the URL text only copies it (copyable column) — it doesn't navigate.
        await page.getByRole('link', { name: 'Ver' }).first().click()
        await page.waitForURL(/webhook-subscriptions\/[^/]+$/, { timeout: 15_000 })

        // Subscribed events are shown in the infolist
        await expect(page.getByText('work_order.created', { exact: false })).toBeVisible()
        await expect(page.getByText('alert.created', { exact: false })).toBeVisible()
        await expect(page.getByText('alert.resolved', { exact: false })).toBeVisible()

        await page.waitForTimeout(300)
        expect(errors).toEqual([])
    })
})

// ══════════════════════════════════════════════════════════════════════════════
// Grupo 12B — work_order.created
// ══════════════════════════════════════════════════════════════════════════════

test.describe.serial('Grupo 12B — work_order.created', () => {
    test.beforeEach(() => {
        execSync('php artisan cache:clear', { stdio: 'ignore' })
        if (! SUB_A_ID) { throw new Error('Tenant A subscription ID no disponible — verificar E2EDataSeeder') }
    })

    test('crear OT en Filament dispara work_order.created → delivery log success', async ({ page }) => {
        const errors = setupErrorListeners(page)

        await page.goto(adminUrl(`${PATHS.workOrders}/create`))
        await page.waitForLoadState('networkidle', { timeout: 10_000 })

        // Filament v5 searchable Select renders as a <button> with placeholder text.
        // The first "Seleccione una opción" button on the page is the Equipo field.
        await page.getByRole('button', { name: /Seleccione una opción/ }).first().click()
        await page.keyboard.type('E2E-PRE-001')
        await page.getByRole('option', { name: /E2E-PRE-001/i }).first().click()

        // Tipo is a native <select>. Use getByLabel (substring match handles the "Tipo *" label).
        // Prioridad already defaults to P3-Medio — no interaction needed.
        await page.getByLabel('Tipo').selectOption({ label: 'Correctivo' })

        await page.getByLabel('Título').fill('[E2E] OT para test de webhook')
        await page.getByLabel('Descripción').fill('OT creada automáticamente por el test E2E de webhooks.')

        await page.getByRole('button', { name: 'Crear', exact: true }).click()
        await page.waitForURL(/work-orders\/[0-9a-f-]{36}$/, { timeout: 20_000 })

        // QUEUE_CONNECTION=sync + Http::fake() → job ran in-request → log exists now
        const log = lastLog('work_order.created')
        expect(log, 'delivery log work_order.created debe existir').not.toBeNull()
        expect(log.status).toBe('success')
        expect(log.http_status).toBe(200)
        expect(log.webhook_subscription_id).toBe(SUB_A_ID)

        await page.waitForTimeout(300)
        expect(errors).toEqual([])
    })
})

// ══════════════════════════════════════════════════════════════════════════════
// Grupo 12C — work_order.closed
// ══════════════════════════════════════════════════════════════════════════════

test.describe.serial('Grupo 12C — work_order.closed', () => {
    test.beforeAll(() => {
        if (! WO_WEBHOOK_ID) { throw new Error('E2E-WO-WEBHOOK ID no disponible — verificar E2EDataSeeder') }
        if (! SUB_A_ID) { throw new Error('Tenant A subscription ID no disponible') }
    })

    test.beforeEach(() => execSync('php artisan cache:clear', { stdio: 'ignore' }))

    test('cerrar OT verificada dispara work_order.closed → delivery log success', async ({ page }) => {
        const errors = setupErrorListeners(page)

        await page.goto(adminUrl(`${PATHS.workOrders}/${WO_WEBHOOK_ID}`))

        // "Cerrar OT" is only visible when status === Verified
        const closeBtn = page.getByRole('button', { name: /Cerrar OT/i })
        await expect(closeBtn, '"Cerrar OT" debe estar visible (status=verified)').toBeVisible({ timeout: 8_000 })
        await closeBtn.click()

        await confirmModal(page)
        await page.waitForLoadState('networkidle', { timeout: 15_000 })

        const log = lastLog('work_order.closed')
        expect(log, 'delivery log work_order.closed debe existir').not.toBeNull()
        expect(log.status).toBe('success')
        expect(log.http_status).toBe(200)
        expect(log.webhook_subscription_id).toBe(SUB_A_ID)

        await page.waitForTimeout(300)
        expect(errors).toEqual([])
    })
})

// ══════════════════════════════════════════════════════════════════════════════
// Grupo 12D — maintenance_request.created
// ══════════════════════════════════════════════════════════════════════════════

test.describe.serial('Grupo 12D — maintenance_request.created', () => {
    test.beforeEach(() => {
        execSync('php artisan cache:clear', { stdio: 'ignore' })
        if (! SUB_A_ID) { throw new Error('Tenant A subscription ID no disponible') }
    })

    test('crear SR en Filament dispara maintenance_request.created → delivery log success', async ({ page }) => {
        const errors = setupErrorListeners(page)

        await page.goto(adminUrl(`${PATHS.maintenanceRequests}/create`))
        await page.waitForLoadState('networkidle', { timeout: 10_000 })

        // MR form equipment options use name only (not code). Search by name fragment.
        await page.getByRole('button', { name: /Seleccione una opción/ }).first().click()
        await page.keyboard.type('Extractora')
        await page.getByRole('option', { name: /Prensa Extractora/i }).first().click()

        // Tipo is a native <select> in the MR form — use selectOption, not click+option
        await page.getByLabel('Tipo').selectOption({ label: 'Correctivo' })

        await page.getByLabel('Título').fill('[E2E] SR para test de webhook')
        await page.getByLabel('Descripción').fill('Solicitud creada automáticamente por el test E2E de webhooks.')

        await page.getByRole('button', { name: 'Crear', exact: true }).click()
        await page.waitForURL(/maintenance-requests\/[0-9a-f-]{36}$/, { timeout: 20_000 })

        const log = lastLog('maintenance_request.created')
        expect(log, 'delivery log maintenance_request.created debe existir').not.toBeNull()
        expect(log.status).toBe('success')
        expect(log.http_status).toBe(200)
        expect(log.webhook_subscription_id).toBe(SUB_A_ID)

        await page.waitForTimeout(300)
        expect(errors).toEqual([])
    })
})

// ══════════════════════════════════════════════════════════════════════════════
// Grupo 12E — alert.created
// ══════════════════════════════════════════════════════════════════════════════

test.describe.serial('Grupo 12E — alert.created', () => {
    test.beforeEach(() => {
        execSync('php artisan cache:clear', { stdio: 'ignore' })
        if (! SUB_A_ID) { throw new Error('Tenant A subscription ID no disponible') }
    })

    test('ruta E2E crea alerta y dispara alert.created → delivery log success', async ({ page }) => {
        const errors = setupErrorListeners(page)

        // Navigate first so the browser origin is http://localhost:8000 (not about:blank).
        // fetch() from about:blank has a null origin which can be blocked by CORS.
        await page.goto(adminUrl('/'))
        await page.waitForLoadState('networkidle', { timeout: 10_000 })

        // Trigger AlertCreated from within the browser context (same webserver process → Http::fake() active).
        const resp = await page.evaluate(async (url) => {
            const res = await fetch(url, { method: 'POST' })
            return { status: res.status }
        }, `${BASE}/api/v1/e2e/create-alert`)
        expect(resp.status, 'POST /api/v1/e2e/create-alert debe retornar 200').toBe(200)

        const log = lastLog('alert.created')
        expect(log, 'delivery log alert.created debe existir').not.toBeNull()
        expect(log.status).toBe('success')
        expect(log.http_status).toBe(200)
        expect(log.webhook_subscription_id).toBe(SUB_A_ID)

        await page.waitForTimeout(300)
        expect(errors).toEqual([])
    })
})

// ══════════════════════════════════════════════════════════════════════════════
// Grupo 12F — alert.resolved
// ══════════════════════════════════════════════════════════════════════════════

test.describe.serial('Grupo 12F — alert.resolved', () => {
    test.beforeAll(() => {
        if (! ALERT_WEBHOOK_ID) { throw new Error('ALERT_WEBHOOK_ID no disponible — verificar E2EDataSeeder') }
        if (! SUB_A_ID) { throw new Error('Tenant A subscription ID no disponible') }
    })

    test.beforeEach(() => execSync('php artisan cache:clear', { stdio: 'ignore' }))

    test('resolver alerta en Filament dispara alert.resolved → delivery log success', async ({ page }) => {
        const errors = setupErrorListeners(page)

        await page.goto(adminUrl(`${PATHS.alerts}/${ALERT_WEBHOOK_ID}`))

        const resolveBtn = page.getByRole('button', { name: 'Resolver', exact: true }).first()
        await expect(resolveBtn, '"Resolver" debe estar visible (status=open)').toBeVisible({ timeout: 8_000 })
        await resolveBtn.click()

        await confirmModal(page)
        await page.waitForLoadState('networkidle', { timeout: 15_000 })

        const log = lastLog('alert.resolved')
        expect(log, 'delivery log alert.resolved debe existir').not.toBeNull()
        expect(log.status).toBe('success')
        expect(log.http_status).toBe(200)
        expect(log.webhook_subscription_id).toBe(SUB_A_ID)

        await page.waitForTimeout(300)
        expect(errors).toEqual([])
    })
})

// ══════════════════════════════════════════════════════════════════════════════
// Grupo 12G — Estructura del delivery log
// ══════════════════════════════════════════════════════════════════════════════

test.describe.serial('Grupo 12G — Estructura del delivery log', () => {
    test('delivery log registra event_name, status=success, http_status=200', async ({ page }) => {
        const errors = setupErrorListeners(page)

        // Verify structure of the log for at least one event (work_order.created)
        const log = lastLog('work_order.created')
        expect(log, 'debe existir al menos un log de work_order.created (requiere 12B)').not.toBeNull()
        expect(log.event_name).toBe('work_order.created')
        expect(log.status).toBe('success')
        expect(log.http_status).toBe(200)
        expect(log.webhook_subscription_id).toBe(SUB_A_ID)

        // Verify through the Filament view page that logs appear in the UI
        await page.goto(adminUrl(`${PATHS.webhooks}/${SUB_A_ID}`))
        await page.waitForLoadState('networkidle', { timeout: 15_000 })

        // "Historial de entregas recientes" section shows at least one entry
        await expect(page.getByText('work_order.created', { exact: false }).first()).toBeVisible({ timeout: 8_000 })

        await page.waitForTimeout(300)
        expect(errors).toEqual([])
    })
})

// ══════════════════════════════════════════════════════════════════════════════
// Grupo 12H — Tenant isolation
// ══════════════════════════════════════════════════════════════════════════════

test.describe.serial('Grupo 12H — Tenant isolation', () => {
    test('suscripción de Tenant B tiene 0 delivery logs tras todos los eventos de Tenant A', async () => {
        if (! SUB_B_ID) { throw new Error('Tenant B subscription ID no disponible — verificar E2EDataSeeder') }

        // WebhookDispatcher queries subscriptions by tenant_id.
        // Events from Tenant A (12B-12F) must never reach Tenant B's subscription.
        const countB = countLogsForSub(SUB_B_ID)
        expect(
            countB,
            'Tenant B subscription debe tener 0 delivery logs — los eventos de Tenant A no deben filtrarse',
        ).toBe(0)
    })
})
