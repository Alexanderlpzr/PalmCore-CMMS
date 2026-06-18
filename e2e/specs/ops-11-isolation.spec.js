/**
 * Grupo 11 — Multi-tenant Isolation
 *
 * Valida que un tenant nunca puede ver información de otro tenant.
 * Este es el grupo de seguridad más crítico de Fronda CMMS.
 *
 * Mecanismo de aislamiento:
 *   - ResolveApiTenant middleware lee tenant_id del PersonalAccessToken
 *   - CurrentTenant::set() inyecta el tenant activo en el request
 *   - BelongsToTenant::TenantScope filtra TODOS los modelos por tenant_id
 *   - findOrFail() retorna 404 para IDs de otro tenant (no están en el scope)
 *
 * Fixtures Tenant A (el-pajuil):  E2E-PRE-001, E2E-WO-0001, E2E-MR-001, E2E-SP-001
 * Fixtures Tenant B (e2e-tenant-b): sin datos de dominio (solo usuario admin)
 *
 * Pruebas:
 *   11A — List endpoints: búsqueda de códigos E2E de Tenant A desde Tenant B → sin resultados
 *   11B — Show endpoints: GET directo con IDs de Tenant A desde token de Tenant B → 404
 *   11C — Search endpoint: búsqueda global desde Tenant B no retorna datos de Tenant A
 *   11D — Dashboard: dashboard de Tenant B no expone datos de Tenant A
 *   11E — PDF endpoints: generación de PDFs con IDs de Tenant A desde Tenant B → 404
 *   11F — Favoritos: UUIDs de Tenant A en localStorage de Tenant B → panel vacío
 */
import { execSync } from 'child_process'
import { test, expect } from '@playwright/test'
import { loginToApp } from '../helpers.js'

const TENANT_B_CREDS = {
    tenant: 'e2e-tenant-b',
    email: 'admin@e2etenantb.test',
    password: 'password',
}

function setupErrorListeners(page) {
    const errors = []
    page.on('pageerror', (e) => errors.push(`pageerror: ${e.message}`))
    page.on('console', (msg) => {
        if (msg.type() !== 'error') return
        const text = msg.text()
        if (/favicon|manifest|Failed to load resource|net::ERR/i.test(text)) return
        if (/Content Security Policy/i.test(text)) return
        errors.push(`console: ${text}`)
    })
    return errors
}

/**
 * Get a Tenant A E2E fixture UUID by running a tinker expression.
 * Stable across runs because E2EDataSeeder uses firstOrCreate / updateOrCreate.
 */
function tinkerUuid(phpExpr) {
    try {
        const out = execSync(
            `php artisan tinker --execute "echo ${phpExpr};"`,
            { encoding: 'utf8', stdio: ['pipe', 'pipe', 'ignore'] },
        )
        const m = out.match(/[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[0-9a-f]{4}-[0-9a-f]{12}/i)
        return m?.[0] ?? null
    } catch {
        return null
    }
}

// Resolve Tenant A resource IDs once at module load (tinker reads from DB directly)
const TENANT_A = {
    equipmentId: tinkerUuid("App\\Models\\Equipment::withoutGlobalScopes()->where('code','E2E-PRE-001')->value('id')"),
    workOrderId: tinkerUuid("App\\Models\\WorkOrder::withoutGlobalScopes()->where('work_order_number','E2E-WO-0001')->value('id')"),
    maintenanceRequestId: tinkerUuid("App\\Models\\MaintenanceRequest::withoutGlobalScopes()->where('request_number','E2E-MR-001')->value('id')"),
    sparePart: tinkerUuid("App\\Models\\SparePart::withoutGlobalScopes()->where('code','E2E-SP-001')->value('id')"),
}

/**
 * Login as Tenant B and capture the Bearer token from the auth response.
 * Clears existing cookies first to prevent the storageState's Tenant A refresh
 * cookie from triggering an auto-session-restore that skips the login form.
 */
async function loginAsTenantB(page) {
    await page.context().clearCookies()
    const [loginResp] = await Promise.all([
        page.waitForResponse(
            (r) => r.url().includes('/api/v1/tokens') && r.request().method() === 'POST',
            { timeout: 20_000 },
        ),
        loginToApp(page, TENANT_B_CREDS),
    ])
    const data = await loginResp.json()
    return data.token
}

/**
 * Make an authenticated GET request from within the browser context using the given token.
 * Returns { status, body } for assertion.
 */
async function apiGet(page, token, path) {
    return page.evaluate(
        async ({ token, path }) => {
            const res = await fetch(`/api/v1/${path}`, {
                method: 'GET',
                credentials: 'include',
                headers: { Accept: 'application/json', Authorization: `Bearer ${token}` },
            })
            const body = await res.json().catch(() => null)
            return { status: res.status, body }
        },
        { token, path },
    )
}

// ══════════════════════════════════════════════════════════════════════════════
// Grupo 11A — List endpoints aislados
// ══════════════════════════════════════════════════════════════════════════════

test.describe.serial('Grupo 11A — List endpoints aislados', () => {
    test.beforeEach(() => execSync('php artisan cache:clear', { stdio: 'ignore' }))

    test('búsqueda en listas de Tenant B no retorna datos de Tenant A', async ({ page }) => {
        const errors = setupErrorListeners(page)
        const token = await loginAsTenantB(page)

        // Equipment list: search by Tenant A's code — must return 0 items
        const eq = await apiGet(page, token, 'equipment?search=E2E-PRE-001&per_page=5')
        expect(eq.status).toBe(200)
        expect(eq.body?.data ?? []).toHaveLength(0)

        // Work orders list: search by Tenant A's WO number — 0 items
        const wo = await apiGet(page, token, 'work-orders?search=E2E-WO-0001&per_page=5')
        expect(wo.status).toBe(200)
        expect(wo.body?.data ?? []).toHaveLength(0)

        // Maintenance requests: search by Tenant A's request number — 0 items
        const mr = await apiGet(page, token, 'maintenance-requests?search=E2E-MR-001&per_page=5')
        expect(mr.status).toBe(200)
        expect(mr.body?.data ?? []).toHaveLength(0)

        // Spare parts list: search by Tenant A's code — 0 items
        const sp = await apiGet(page, token, 'inventory/spare-parts?search=E2E-SP-001&per_page=5')
        expect(sp.status).toBe(200)
        expect(sp.body?.data ?? []).toHaveLength(0)

        // Alerts list (open) — Tenant A's E2E alerts must not appear
        const alt = await apiGet(page, token, 'alerts?status=open&per_page=200')
        expect(alt.status).toBe(200)
        const alertTitles = (alt.body?.data ?? []).map((a) => a.title ?? '')
        expect(alertTitles.some((t) => t.includes('[E2E]'))).toBe(false)

        await page.waitForTimeout(300)
        expect(errors).toEqual([])
    })
})

// ══════════════════════════════════════════════════════════════════════════════
// Grupo 11B — Show endpoints: 404 para IDs de Tenant A
// ══════════════════════════════════════════════════════════════════════════════

test.describe.serial('Grupo 11B — Show endpoints aislados', () => {
    test.beforeAll(() => {
        if (! TENANT_A.equipmentId) { throw new Error('TENANT_A.equipmentId no disponible — verificar E2EDataSeeder') }
        if (! TENANT_A.workOrderId) { throw new Error('TENANT_A.workOrderId no disponible') }
        if (! TENANT_A.maintenanceRequestId) { throw new Error('TENANT_A.maintenanceRequestId no disponible') }
    })

    test.beforeEach(() => execSync('php artisan cache:clear', { stdio: 'ignore' }))

    test('show() retorna 404 para recursos de Tenant A accedidos desde Tenant B', async ({ page }) => {
        const errors = setupErrorListeners(page)
        const token = await loginAsTenantB(page)

        // Equipment show — TenantScope filters out cross-tenant ID → findOrFail throws → 404
        const eq = await apiGet(page, token, `equipment/${TENANT_A.equipmentId}`)
        expect(eq.status, `GET equipment/${TENANT_A.equipmentId} debería ser 404`).toBe(404)

        // Work order show — same scope mechanism
        const wo = await apiGet(page, token, `work-orders/${TENANT_A.workOrderId}`)
        expect(wo.status, `GET work-orders/${TENANT_A.workOrderId} debería ser 404`).toBe(404)

        // Maintenance request show
        const mr = await apiGet(page, token, `maintenance-requests/${TENANT_A.maintenanceRequestId}`)
        expect(mr.status, `GET maintenance-requests/${TENANT_A.maintenanceRequestId} debería ser 404`).toBe(404)

        // Spare part show
        if (TENANT_A.sparePart) {
            const sp = await apiGet(page, token, `inventory/spare-parts/${TENANT_A.sparePart}`)
            expect(sp.status, `GET spare-parts/${TENANT_A.sparePart} debería ser 404`).toBe(404)
        }

        await page.waitForTimeout(300)
        expect(errors).toEqual([])
    })
})

// ══════════════════════════════════════════════════════════════════════════════
// Grupo 11C — Search endpoint aislado
// ══════════════════════════════════════════════════════════════════════════════

test.describe.serial('Grupo 11C — Search endpoint aislado', () => {
    test.beforeEach(() => execSync('php artisan cache:clear', { stdio: 'ignore' }))

    test('búsqueda global desde Tenant B no retorna datos de Tenant A', async ({ page }) => {
        const errors = setupErrorListeners(page)
        const token = await loginAsTenantB(page)

        // Search for Tenant A's equipment code — BelongsToTenant scopes all models in SearchController
        const r1 = await apiGet(page, token, 'search?q=E2E-PRE-001')
        expect(r1.status).toBe(200)
        const groups1 = r1.body?.groups ?? []
        const totalItems1 = groups1.reduce((n, g) => n + (g.items?.length ?? 0), 0)
        expect(totalItems1).toBe(0)

        // Search for Tenant A's WO number
        const r2 = await apiGet(page, token, 'search?q=E2E-WO-0001')
        expect(r2.status).toBe(200)
        const groups2 = r2.body?.groups ?? []
        const totalItems2 = groups2.reduce((n, g) => n + (g.items?.length ?? 0), 0)
        expect(totalItems2).toBe(0)

        // Search for E2E prefix — should return 0 items since all E2E data belongs to Tenant A
        const r3 = await apiGet(page, token, 'search?q=E2E-SP-001')
        expect(r3.status).toBe(200)
        const groups3 = r3.body?.groups ?? []
        const totalItems3 = groups3.reduce((n, g) => n + (g.items?.length ?? 0), 0)
        expect(totalItems3).toBe(0)

        await page.waitForTimeout(300)
        expect(errors).toEqual([])
    })
})

// ══════════════════════════════════════════════════════════════════════════════
// Grupo 11D — Dashboard de Tenant B aislado
// ══════════════════════════════════════════════════════════════════════════════

test.describe.serial('Grupo 11D — Dashboard aislado', () => {
    test.beforeEach(() => execSync('php artisan cache:clear', { stdio: 'ignore' }))

    test('dashboard de Tenant B no expone datos de Tenant A', async ({ page }) => {
        const errors = setupErrorListeners(page)

        await loginToApp(page, TENANT_B_CREDS)

        // Verify sidebar shows Tenant B's tenant name, not Tenant A's name
        // AppSidebar renders auth.tenantName from the login response
        await expect(page.getByText('Extractora El Pajuil', { exact: false })).not.toBeVisible()

        // Dashboard loads "Mis órdenes de trabajo" — Tenant B has no WOs, section should be empty
        await page.waitForResponse(
            (r) => r.url().includes('/api/v1/work-orders/mine') && r.request().method() === 'GET',
            { timeout: 15_000 },
        )
        await page.waitForLoadState('networkidle', { timeout: 10_000 })

        // "Sin órdenes asignadas" empty state — Tenant B user has no WOs from Tenant A
        const emptyWOs = page.getByText('Sin órdenes asignadas', { exact: true })
        await expect(emptyWOs).toBeVisible({ timeout: 5_000 })

        // No Tenant A work order titles visible anywhere on dashboard
        const tenantAWoTitle = page.getByText('[E2E] OT para pruebas de inventario', { exact: false })
        await expect(tenantAWoTitle).not.toBeVisible()

        await page.waitForTimeout(300)
        expect(errors).toEqual([])
    })
})

// ══════════════════════════════════════════════════════════════════════════════
// Grupo 11E — PDF endpoints aislados
// ══════════════════════════════════════════════════════════════════════════════

test.describe.serial('Grupo 11E — PDF endpoints aislados', () => {
    test.beforeAll(() => {
        if (! TENANT_A.equipmentId) { throw new Error('TENANT_A.equipmentId no disponible') }
        if (! TENANT_A.workOrderId) { throw new Error('TENANT_A.workOrderId no disponible') }
    })

    test.beforeEach(() => execSync('php artisan cache:clear', { stdio: 'ignore' }))

    test('PDF endpoints retornan 404 para recursos de Tenant A accedidos desde Tenant B', async ({ page }) => {
        const errors = setupErrorListeners(page)
        const token = await loginAsTenantB(page)

        // Equipment PDF — EquipmentPdfService calls findOrFail(id) within Tenant B scope → 404
        const eqPdf = await apiGet(page, token, `reports/equipment/${TENANT_A.equipmentId}`)
        expect(eqPdf.status, `GET reports/equipment/${TENANT_A.equipmentId} debería ser 404`).toBe(404)

        // Work order PDF — same isolation
        const woPdf = await apiGet(page, token, `reports/work-orders/${TENANT_A.workOrderId}`)
        expect(woPdf.status, `GET reports/work-orders/${TENANT_A.workOrderId} debería ser 404`).toBe(404)

        await page.waitForTimeout(300)
        expect(errors).toEqual([])
    })
})

// ══════════════════════════════════════════════════════════════════════════════
// Grupo 11F — Favoritos no rompen el aislamiento
// ══════════════════════════════════════════════════════════════════════════════

test.describe.serial('Grupo 11F — Favoritos no rompen aislamiento', () => {
    test.beforeAll(() => {
        if (! TENANT_A.equipmentId) { throw new Error('TENANT_A.equipmentId no disponible') }
        if (! TENANT_A.workOrderId) { throw new Error('TENANT_A.workOrderId no disponible') }
    })

    test.beforeEach(() => execSync('php artisan cache:clear', { stdio: 'ignore' }))

    test('UUIDs de Tenant A en favorites de Tenant B producen panel vacío', async ({ page }) => {
        const errors = setupErrorListeners(page)

        // Clear Tenant A's refresh cookie so the login form is shown
        await page.context().clearCookies()

        // Navigate to the login page first to establish the correct origin for localStorage
        await page.goto('/app/login')

        // Inject Tenant A's resource UUIDs as favorites BEFORE login so the SPA
        // reads them on first mount (localStorage persists across same-origin navigations)
        await page.evaluate(({ eqId, woId }) => {
            localStorage.setItem('fronda.favorites.equipment', JSON.stringify([eqId]))
            localStorage.setItem('fronda.favorites.workorders', JSON.stringify([woId]))
        }, { eqId: TENANT_A.equipmentId, woId: TENANT_A.workOrderId })

        // Fill and submit the Tenant B login form (already on /app/login)
        await page.locator('input[autocomplete="organization"]').fill(TENANT_B_CREDS.tenant)
        await page.locator('input[type="email"]').fill(TENANT_B_CREDS.email)
        await page.locator('input[type="password"]').fill(TENANT_B_CREDS.password)
        await page.getByRole('button', { name: /Ingresar/i }).click()
        await page.waitForURL('**/app/dashboard', { timeout: 20_000 })

        // Wait for FavoritesPanel to finish resolving UUIDs (networkidle = all API calls done)
        await page.waitForLoadState('networkidle', { timeout: 15_000 })

        // FavoritesPanel must show empty state — cross-tenant UUIDs are silently dropped
        // (FavoritesPanel comment: "ignore ones that no longer resolve (deleted, or belong
        // to a different tenant in this browser)")
        const emptyMsg = page.getByText('No hay favoritos todavía.', { exact: true })
        await expect(emptyMsg).toBeVisible({ timeout: 8_000 })

        // No Tenant A equipment name visible in the favorites panel
        const tenantAEqName = page.getByText('[E2E] Prensa Extractora Principal', { exact: false })
        await expect(tenantAEqName).not.toBeVisible()

        await page.waitForTimeout(300)
        expect(errors).toEqual([])
    })
})
