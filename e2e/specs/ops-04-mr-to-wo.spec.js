/**
 * Grupo 4 — Maintenance Request → Work Order (ciclo de negocio completo)
 *
 * Flujo: Crear MR (Filament) → Enviar → Revisar → Aprobar → Convertir a OT
 *        → Verificar OT creada en Filament y en el Ops SPA.
 *
 * Datos sembrados: equipo E2E-PRE-001 ([E2E] Prensa Extractora Principal)
 * Tenant: el-pajuil / admin@elpajuil.demo
 *
 * Validaciones:
 *   1. La solicitud se crea correctamente
 *   2. La solicitud aparece en la lista del Ops SPA
 *   3. La aprobación cambia el estado esperado
 *   4. La conversión genera una OT
 *   5. La OT pertenece al mismo tenant (API tenant-scoped)
 *   6. La OT aparece en el listado del Ops SPA
 *   7. Existe trazabilidad MR ↔ OT
 *   8-9. Sin errores de consola ni page errors
 *
 * Pasos Filament (1-3) usan el storageState global (admin.json).
 * Pasos del Ops SPA (4-8) llaman a loginToApp(page) para obtener token.
 * Los tests corren en orden serial porque comparten estado entre pasos.
 */
import { execSync } from 'child_process'
import { test, expect } from '@playwright/test'
import { loginToApp, adminUrl, appUrl, PATHS } from '../helpers.js'

// Timestamp makes the title unique across test runs even if cleanup is skipped.
const TITLE = `[E2E-G4] Falla hidráulica ${Date.now()}`

// State shared between serial tests
let mrUrl = ''    // Filament view URL for the created MR
let mrNumber = '' // e.g. MR-2026-00003
let woUrl = ''    // Filament view URL for the converted WO
let woNumber = '' // e.g. OT-2026-E2E-PRE-001-000002

test.describe.serial('Grupo 4 — Maintenance Request → Work Order', () => {
    test.beforeEach(() => {
        // Reset the api-tokens rate limiter (5/min/IP) so loginToApp() steps
        // never trip on the rapid-execution cadence of the test suite.
        execSync('php artisan cache:clear', { stdio: 'ignore' })
    })

    // ── Validación 1: La solicitud se crea correctamente ──────────────────────
    test('1. la solicitud se crea correctamente', async ({ page }) => {
        await page.goto(adminUrl(`${PATHS.maintenanceRequests}/create`))

        // Equipment — searchable select; rendered as a button in Filament v5 (not
        // a labelled input). Filament searches by the displayed name, not by code.
        // Equipment name: "[E2E] Prensa Extractora Principal"
        await page.getByRole('button', { name: /Seleccione una opción/ }).click()
        await page.keyboard.type('Extractora')
        await page.getByRole('option', { name: /Prensa Extractora Principal/i }).first().click()

        // Tipo — non-searchable; rendered as a native <select> in Filament v5.
        await page.getByLabel('Tipo').selectOption({ label: 'Correctivo' })

        // Prioridad — native <select> with default P3 — Medio; accept the default.

        // Required text fields
        await page.getByLabel('Título').fill(TITLE)
        await page.getByLabel('Descripción').fill(
            'Falla hidráulica detectada durante el ciclo de extracción. Creado por E2E.',
        )

        await page.getByRole('button', { name: 'Crear', exact: true }).click()
        // waitForURL with /[^/]+$/ matches the current /create URL immediately.
        // Wait specifically for the UUID-based view URL (36-char UUID with hyphens).
        await page.waitForURL(/maintenance-requests\/[0-9a-f-]{36}$/, { timeout: 20_000 })

        mrUrl = page.url()

        // Title visible on the view page
        await expect(page.getByText(TITLE)).toBeVisible({ timeout: 10_000 })

        // MR number is generated (MR-YEAR-NNNNN)
        const mrEl = page.getByText(/MR-\d{4}-\d{5}/).first()
        await expect(mrEl).toBeVisible({ timeout: 10_000 })
        mrNumber = (await mrEl.textContent() ?? '').trim()

        expect(mrNumber).toMatch(/^MR-\d{4}-\d{5}$/)
    })

    // ── Validación 3: La aprobación cambia el estado esperado ─────────────────
    // NOTE: Filament v5 navigation sidebar fires ~28 JS errors on admin pages
    // (null.includes() in navigation isActive() checks). These are Filament
    // internals — validations 8-9 (no page errors) apply to the Ops SPA only.
    test('2. la aprobación cambia el estado a Aprobado (draft → aprobado)', async ({ page }) => {

        // Filament renders ALL action modals in the DOM (all hidden via x-show).
        // After clicking an action button, Livewire processes the request (~10s in
        // test environments) and sets isOpen=true for that modal. Targeting the
        // [role="dialog"] container is unreliable because the first match in DOM
        // order may not be the active modal. Instead, click "Confirmar" directly —
        // actionTimeout (20s) provides enough headroom for Livewire processing.

        // Draft → Submitted
        // Validate each transition by checking which action buttons appear — they
        // are state-gated (Filament visibility conditions). This avoids matching
        // "Aprobado por" / "Aprobado el" field labels in the infolist.
        await page.goto(mrUrl)
        await page.getByRole('button', { name: /Enviar para revisión/i }).click()
        await page.getByRole('button', { name: 'Confirmar' }).click()
        // "Tomar para revisión" appears only when status === submitted
        await expect(page.getByRole('button', { name: /Tomar para revisión/i })).toBeVisible({ timeout: 15_000 })

        // Submitted → UnderReview
        await page.getByRole('button', { name: /Tomar para revisión/i }).click()
        await page.getByRole('button', { name: 'Confirmar' }).click()
        // "Aprobar" appears only when status === under_review
        await expect(page.getByRole('button', { name: /Aprobar/i })).toBeVisible({ timeout: 15_000 })

        // UnderReview → Approved
        await page.getByRole('button', { name: /Aprobar/i }).click()
        await page.getByRole('button', { name: 'Confirmar' }).click()
        // "Convertir a OT" appears only when status === approved
        await expect(page.getByRole('button', { name: /Convertir a OT/i })).toBeVisible({ timeout: 15_000 })
    })

    // ── Validación 4: La conversión genera una OT ─────────────────────────────
    test('3. la conversión genera una OT', async ({ page }) => {

        await page.goto(mrUrl)
        // Pre-condition: MR must be in 'approved' state — "Convertir a OT" is the
        // state-gated action that only appears when approved.
        await expect(page.getByRole('button', { name: /Convertir a OT/i })).toBeVisible({ timeout: 10_000 })

        await page.getByRole('button', { name: /Convertir a OT/i }).click()

        // "Crear Orden de Trabajo" modal — Livewire takes ~20s to open it in test
        // environments, same as the confirmation modals. Target "Enviar" directly
        // (actionTimeout 20s). Tipo de OT defaults to "Corrective" from the MR type.
        await page.getByRole('button', { name: 'Enviar' }).click()

        // ViewMaintenanceRequest::convertToWo redirects to the new WO view on success.
        // Use UUID-specific pattern to avoid matching /work-orders/create prematurely.
        await page.waitForURL(/work-orders\/[0-9a-f-]{36}$/, { timeout: 20_000 })
        woUrl = page.url()

        // WO number follows the pattern OT-YEAR-EQUIPMENT_CODE-SEQUENCE
        const woEl = page.getByText(/OT-\d{4}-E2E-PRE-001-\d+/).first()
        await expect(woEl).toBeVisible({ timeout: 10_000 })
        woNumber = (await woEl.textContent() ?? '').trim()

        expect(woNumber).toMatch(/^OT-\d{4}-E2E-PRE-001-\d+$/)
    })

    // ── Validación 2: La solicitud aparece en la lista del Ops SPA ───────────
    test('4. la solicitud aparece en la lista del Ops SPA', async ({ page }) => {
        await loginToApp(page)
        // loginToApp lands on /app/dashboard. Navigate via sidebar (client-side Vue
        // Router) to preserve the in-memory Pinia auth token. page.goto() causes a
        // hard reload that wipes Vue store state, redirecting back to /app/login.
        await page.getByRole('link', { name: 'Solicitudes', exact: true }).click()
        await page.waitForURL('**/app/solicitudes')

        // Switch to "Todas" — converted MRs have status "converted" which is not
        // in the default "submitted,under_review" filter.
        await page.getByRole('button', { name: /^Todas$/i }).click()

        await page.waitForResponse(
            (r) => r.url().includes('/api/v1/maintenance-requests') && r.request().method() === 'GET',
            { timeout: 10_000 },
        )

        await expect(page.getByText(TITLE)).toBeVisible({ timeout: 10_000 })
    })

    // ── Validación 5 + 6: OT pertenece al tenant y aparece en el listado ──────
    test('5-6. la OT pertenece al tenant y aparece en el listado del Ops SPA', async ({ page }) => {
        await loginToApp(page)
        await page.getByRole('link', { name: 'Órdenes de trabajo', exact: true }).click()
        await page.waitForURL('**/app/ordenes')

        // Newly converted WOs start as "draft" — switch to "Todas" to include them.
        await page.getByRole('button', { name: /^Todas$/i }).click()

        // Tenant scope validation: the API only returns WOs for the logged-in tenant.
        // Finding the WO here confirms it belongs to el-pajuil.
        await page.locator('input[placeholder*="Buscar"]').fill(woNumber)

        const resp = await page.waitForResponse(
            (r) =>
                r.url().includes('/api/v1/work-orders') &&
                !r.url().includes('/mine') &&
                r.url().includes('search=') &&
                r.request().method() === 'GET',
            { timeout: 10_000 },
        )
        expect(resp.status()).toBe(200)

        await expect(page.getByText(woNumber)).toBeVisible({ timeout: 10_000 })
    })

    // ── Validación 7: Trazabilidad MR ↔ OT ──────────────────────────────────
    test('7. existe trazabilidad entre solicitud y OT', async ({ page }) => {
        // ── 7a: Ops SPA — MR detail shows the linked WO number ────────────────
        await loginToApp(page)
        await page.getByRole('link', { name: 'Solicitudes', exact: true }).click()
        await page.waitForURL('**/app/solicitudes')

        await page.getByRole('button', { name: /^Todas$/i }).click()
        await page.waitForResponse(
            (r) => r.url().includes('/api/v1/maintenance-requests') && r.request().method() === 'GET',
            { timeout: 10_000 },
        )
        await page.getByText(TITLE).click()
        await page.waitForURL(/\/app\/solicitudes\/[^/]+$/, { timeout: 10_000 })

        // MaintenanceRequestDetailView renders `mr.work_order.work_order_number` as a link
        await expect(page.getByText(woNumber)).toBeVisible({ timeout: 10_000 })

        // ── 7b: Filament WO view — "Solicitud origen" shows the MR number ──────
        // Hard-navigate to Filament. The global storageState provides admin cookies,
        // and sessions use file driver (not cache), so cache:clear doesn't affect them.
        await page.goto(woUrl)
        await page.waitForURL(/work-orders\/[^/]+$/, { timeout: 10_000 })

        // WorkOrderInfolist has TextEntry::make('maintenanceRequest.request_number')
        // labeled "Solicitud origen"
        await expect(page.getByText('Solicitud origen')).toBeVisible({ timeout: 10_000 })
        await expect(page.getByText(mrNumber)).toBeVisible({ timeout: 10_000 })
    })

    // ── Validaciones 8-9: Sin errores de consola ni page errors ──────────────
    test('8-9. sin errores de consola ni page errors en el flujo Ops SPA', async ({ page }) => {
        const errors = []
        page.on('pageerror', (e) => errors.push(`pageerror: ${e.message}`))
        page.on('console', (msg) => {
            if (msg.type() !== 'error') return
            const text = msg.text()
            if (/favicon|manifest|Failed to load resource|net::ERR/i.test(text)) return
            if (/Content Security Policy/i.test(text)) return // Laravel Boost dev-only injection
            errors.push(`console: ${text}`)
        })

        await loginToApp(page)

        // Sidebar navigation throughout to preserve Vue auth state
        await page.getByRole('link', { name: 'Solicitudes', exact: true }).click()
        await page.waitForURL('**/app/solicitudes')

        await page.getByRole('button', { name: /^Todas$/i }).click()
        await page.waitForResponse(
            (r) => r.url().includes('/api/v1/maintenance-requests') && r.request().method() === 'GET',
            { timeout: 10_000 },
        )
        await expect(page.getByText(TITLE)).toBeVisible({ timeout: 10_000 })
        await page.getByText(TITLE).click()
        await page.waitForURL(/\/app\/solicitudes\//, { timeout: 10_000 })
        await page.waitForTimeout(500)

        await page.getByRole('link', { name: 'Órdenes de trabajo', exact: true }).click()
        await page.waitForURL('**/app/ordenes')
        await page.getByRole('button', { name: /^Todas$/i }).click()
        await page.waitForResponse(
            (r) =>
                r.url().includes('/api/v1/work-orders') &&
                !r.url().includes('/mine') &&
                r.request().method() === 'GET',
            { timeout: 10_000 },
        )
        await page.locator('input[placeholder*="Buscar"]').fill(woNumber)
        await page.waitForTimeout(500)
        await expect(page.getByText(woNumber)).toBeVisible({ timeout: 10_000 })

        expect(errors).toEqual([])
    })
})
