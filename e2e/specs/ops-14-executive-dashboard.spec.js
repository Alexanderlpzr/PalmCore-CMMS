/**
 * Grupo 14 — Executive Dashboard (Sprint 12.5)
 *
 * Valida el Dashboard Gerencial implementado en Sprint 12.5.
 * Ruta: /app/gerencial  (nombre: ops.gerencial)
 * Sidebar: Análisis > Gerencial
 *
 * Tests:
 *   14-1  KPI cards visibles tras carga (Disponibilidad, MTBF, MTTR, etc.)
 *   14-2  Sección "Salud por Área" visible
 *   14-3  Sección "Equipos Críticos" visible
 *   14-4  Sección "Costos por Tipo" visible
 *   14-5  Sección "Tendencias 12 Meses" visible
 *   14-6  Multi-tenant: Tenant B carga sin datos de Tenant A
 *   14-7  Sidebar link navega a /app/gerencial
 *   14-8  Todos los endpoints ejecutivos responden 200
 */
import { execSync } from 'child_process'
import { test, expect } from '@playwright/test'
import { loginToApp, appUrl } from '../helpers.js'

const TENANT_B_CREDS = {
    tenant: 'e2e-tenant-b',
    email: 'admin@e2etenantb.test',
    password: 'password',
}

/** Registers console/pageerror listeners. Returns the errors array. */
function setupErrorListeners(page) {
    const errors = []
    page.on('pageerror', (e) => errors.push(`pageerror: ${e.message}`))
    page.on('console', (msg) => {
        if (msg.type() !== 'error') { return }
        const text = msg.text()
        if (/favicon|manifest|Failed to load resource|net::ERR/i.test(text)) { return }
        if (/ResizeObserver/i.test(text)) { return }
        if (/Content Security Policy/i.test(text)) { return }
        errors.push(`console: ${text}`)
    })
    return errors
}

/**
 * Login and navigate to /app/gerencial via sidebar click.
 * Using a sidebar click rather than page.goto avoids a full-page reload
 * which would lose the in-memory access token (the SPA stores it in a Pinia
 * ref, not localStorage).
 *
 * Waits for the executive summary API response before returning so that the
 * KPI cards have real data (or "—" placeholders) instead of loading skeletons.
 */
async function navToGerencial(page, creds = undefined) {
    await loginToApp(page, creds)

    // Register the response listener BEFORE clicking so we don't miss it
    const summaryResponse = page.waitForResponse(
        (r) => r.url().includes('/api/v1/executive/summary') && r.request().method() === 'GET',
        { timeout: 20_000 },
    )

    await page.getByRole('link', { name: 'Gerencial', exact: true }).click()
    await page.waitForURL('**/app/gerencial', { timeout: 15_000 })

    await summaryResponse
    await page.waitForLoadState('networkidle', { timeout: 15_000 })
}

// ══════════════════════════════════════════════════════════════════════════════
// Grupo 14-1 — KPI cards visibles
// ══════════════════════════════════════════════════════════════════════════════

test.describe.serial('Grupo 14-1 — KPI cards visibles', () => {
    test.beforeEach(() => execSync('php artisan cache:clear', { stdio: 'ignore' }))

    test('muestra el header y tarjetas KPI tras cargar', async ({ page }) => {
        const errors = setupErrorListeners(page)

        await navToGerencial(page)

        // Page title
        await expect(page.getByRole('heading', { name: 'Dashboard Gerencial' })).toBeVisible({ timeout: 10_000 })

        // Skeleton should be gone — real KPI labels appear
        await expect(page.getByText('Disponibilidad', { exact: true }).first()).toBeVisible({ timeout: 10_000 })
        await expect(page.getByText('MTBF', { exact: true }).first()).toBeVisible()
        await expect(page.getByText('MTTR', { exact: true }).first()).toBeVisible()
        await expect(page.getByText('OT Abiertas', { exact: true })).toBeVisible()
        await expect(page.getByText('Preventivos Vencidos', { exact: true })).toBeVisible()
        await expect(page.getByText('Costo Mensual', { exact: true }).first()).toBeVisible()

        await page.waitForTimeout(300)
        expect(errors).toEqual([])
    })
})

// ══════════════════════════════════════════════════════════════════════════════
// Grupo 14-2 — Sección Salud por Área
// ══════════════════════════════════════════════════════════════════════════════

test.describe.serial('Grupo 14-2 — Sección Salud por Área', () => {
    test.beforeEach(() => execSync('php artisan cache:clear', { stdio: 'ignore' }))

    test('muestra la sección Salud por Área con al menos una fila o empty state', async ({ page }) => {
        const errors = setupErrorListeners(page)

        await navToGerencial(page)

        // Section heading always renders
        await expect(page.getByText('Salud por Área', { exact: true })).toBeVisible({ timeout: 10_000 })

        // Either the table header columns OR the empty state are shown
        const tableHeader = page.getByText('Área', { exact: true })
        const emptyState = page.getByText('Sin datos de áreas')
        const visible = await Promise.race([
            tableHeader.waitFor({ state: 'visible', timeout: 8_000 }).then(() => 'table'),
            emptyState.waitFor({ state: 'visible', timeout: 8_000 }).then(() => 'empty'),
        ])
        expect(['table', 'empty']).toContain(visible)

        await page.waitForTimeout(300)
        expect(errors).toEqual([])
    })
})

// ══════════════════════════════════════════════════════════════════════════════
// Grupo 14-3 — Sección Equipos Críticos
// ══════════════════════════════════════════════════════════════════════════════

test.describe.serial('Grupo 14-3 — Sección Equipos Críticos', () => {
    test.beforeEach(() => execSync('php artisan cache:clear', { stdio: 'ignore' }))

    test('muestra la sección Equipos Críticos', async ({ page }) => {
        const errors = setupErrorListeners(page)

        await navToGerencial(page)

        await expect(page.getByText('Equipos Críticos', { exact: true })).toBeVisible({ timeout: 10_000 })

        await page.waitForTimeout(300)
        expect(errors).toEqual([])
    })
})

// ══════════════════════════════════════════════════════════════════════════════
// Grupo 14-4 — Sección Costos por Tipo
// ══════════════════════════════════════════════════════════════════════════════

test.describe.serial('Grupo 14-4 — Sección Costos por Tipo', () => {
    test.beforeEach(() => execSync('php artisan cache:clear', { stdio: 'ignore' }))

    test('muestra la sección Costos por Tipo', async ({ page }) => {
        const errors = setupErrorListeners(page)

        await navToGerencial(page)

        await expect(page.getByText('Costos por Tipo', { exact: true })).toBeVisible({ timeout: 10_000 })

        await page.waitForTimeout(300)
        expect(errors).toEqual([])
    })
})

// ══════════════════════════════════════════════════════════════════════════════
// Grupo 14-5 — Sección Tendencias
// ══════════════════════════════════════════════════════════════════════════════

test.describe.serial('Grupo 14-5 — Sección Tendencias', () => {
    test.beforeEach(() => execSync('php artisan cache:clear', { stdio: 'ignore' }))

    test('muestra la sección Tendencias 12 Meses', async ({ page }) => {
        const errors = setupErrorListeners(page)

        await navToGerencial(page)

        await expect(page.getByText('Tendencias 12 Meses', { exact: true })).toBeVisible({ timeout: 10_000 })

        await page.waitForTimeout(300)
        expect(errors).toEqual([])
    })
})

// ══════════════════════════════════════════════════════════════════════════════
// Grupo 14-6 — Multi-tenant aislamiento
// ══════════════════════════════════════════════════════════════════════════════

test.describe.serial('Grupo 14-6 — Multi-tenant aislamiento', () => {
    test.beforeEach(() => execSync('php artisan cache:clear', { stdio: 'ignore' }))

    test('Tenant B carga dashboard sin datos de Tenant A', async ({ page }) => {
        const errors = setupErrorListeners(page)

        await navToGerencial(page, TENANT_B_CREDS)

        // Dashboard always shows its heading regardless of data
        await expect(page.getByRole('heading', { name: 'Dashboard Gerencial' })).toBeVisible({ timeout: 10_000 })

        // KPI labels visible (may show "—" for zero/null values — that is expected)
        await expect(page.getByText('Disponibilidad', { exact: true }).first()).toBeVisible({ timeout: 10_000 })

        // No data from Tenant A (el-pajuil) should be visible.
        // Tenant A may have area names like "Recepción" seeded by E2EDataSeeder.
        // If those appear it is an isolation bug.
        const tenantALeak = page.getByText('Recepción')
        await expect(tenantALeak).not.toBeVisible({ timeout: 3_000 }).catch(() => {
            // getByText may throw if not found — that is fine, just means no leak
        })
        const isLeaking = await tenantALeak.isVisible().catch(() => false)
        expect(isLeaking, 'Datos de Tenant A no deben aparecer en Tenant B').toBe(false)

        await page.waitForTimeout(300)
        expect(errors).toEqual([])
    })
})

// ══════════════════════════════════════════════════════════════════════════════
// Grupo 14-7 — Sidebar link navega correctamente
// ══════════════════════════════════════════════════════════════════════════════

test.describe.serial('Grupo 14-7 — Sidebar link', () => {
    test.beforeEach(() => execSync('php artisan cache:clear', { stdio: 'ignore' }))

    test('clic en Gerencial del sidebar navega a /app/gerencial', async ({ page }) => {
        const errors = setupErrorListeners(page)

        await loginToApp(page)
        // Start at dashboard so we can exercise the sidebar link
        await page.waitForURL('**/app/dashboard', { timeout: 20_000 })

        // Wait for API responses triggered by navigation
        const summaryResponse = page.waitForResponse(
            (r) => r.url().includes('/api/v1/executive/summary') && r.request().method() === 'GET',
            { timeout: 20_000 },
        )

        await page.getByRole('link', { name: 'Gerencial', exact: true }).click()

        await page.waitForURL('**/app/gerencial', { timeout: 15_000 })
        await summaryResponse

        // Dashboard header confirms the view rendered
        await expect(page.getByRole('heading', { name: 'Dashboard Gerencial' })).toBeVisible({ timeout: 10_000 })

        expect(page.url()).toContain('/app/gerencial')

        await page.waitForTimeout(300)
        expect(errors).toEqual([])
    })
})

// ══════════════════════════════════════════════════════════════════════════════
// Grupo 14-8 — Endpoints ejecutivos responden 200
// ══════════════════════════════════════════════════════════════════════════════

test.describe.serial('Grupo 14-8 — Endpoints API responden 200', () => {
    test.beforeEach(() => execSync('php artisan cache:clear', { stdio: 'ignore' }))

    test('todos los endpoints /api/v1/executive/* responden 200', async ({ page }) => {
        // Collect API responses while navigating to the dashboard
        const responses = {}
        const endpoints = ['summary', 'areas', 'top-equipment', 'costs', 'trends']

        for (const ep of endpoints) {
            page.on('response', (r) => {
                if (r.url().includes(`/api/v1/executive/${ep}`) && r.request().method() === 'GET') {
                    responses[ep] = r.status()
                }
            })
        }

        await navToGerencial(page)

        // Give remaining endpoints time to resolve (trends / top-equipment may be last)
        await page.waitForLoadState('networkidle', { timeout: 10_000 })

        for (const ep of endpoints) {
            expect(responses[ep], `Endpoint /api/v1/executive/${ep} debe responder 200`).toBe(200)
        }
    })
})
