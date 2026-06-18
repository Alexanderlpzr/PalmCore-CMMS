/**
 * Grupo 6 — Acciones masivas (Bulk Actions)
 *
 * Valida las acciones masivas introducidas en P6 para:
 *   Grupo 6A — Work Orders
 *   Grupo 6B — Solicitudes de Mantenimiento
 *   Grupo 6C — Equipos
 *
 * Validaciones por entidad (10 en total):
 *   1. Selección múltiple
 *   2. Contador visible en la barra de acciones
 *   3. "Seleccionar todo" selecciona todos los elementos visibles
 *   4. Acción masiva exitosa (toast de éxito)
 *   5. Éxito parcial (toast de advertencia)
 *   6. Toast con el resumen correcto
 *   7. El listado se refresca después de la acción
 *   8. La selección se limpia después de la acción
 *   9. Sin errores de consola
 *  10. Sin page errors
 *
 * Datos sembrados por E2EDataSeeder:
 *   WOs (success):  E2E-WO-0004 + E2E-WO-0005 (ambas planned, título "[E2E success]")
 *   WOs (partial):  E2E-WO-0002 (planned, cancellable) + E2E-WO-0003 (closed, no cancellable)
 *   MRs (Pendientes, orden created_at DESC):
 *     nth(1) = E2E-MR-003 (submitted, más nuevo)
 *     nth(2) = E2E-MR-002 (submitted)
 *     nth(3) = E2E-MR-001 (under_review, más antiguo)
 *   Equip: E2E-PRE-001 + E2E-PRE-002 (ambos activos)
 *
 * Nota sobre equipos: las acciones masivas de equipo (set_status, set_criticality) no tienen
 * lógica de negocio que falle. El éxito parcial se valida mediante llamada directa a la API
 * con un UUID inexistente (imposible de producir desde la UI).
 */
import { execSync } from 'child_process'
import { test, expect } from '@playwright/test'
import { loginToApp } from '../helpers.js'

/** Extract the Pinia auth token from the mounted Vue app. */
async function extractToken(page) {
    return page.evaluate(
        () =>
            document
                .querySelector('#ops-app')
                ?.__vue_app__?.config?.globalProperties?.$pinia?.state?.value?.auth
                ?.token ?? null,
    )
}

// ══════════════════════════════════════════════════════════════════════════════
// Grupo 6A — Work Orders
// ══════════════════════════════════════════════════════════════════════════════

test.describe.serial('Grupo 6A — Work Orders bulk actions', () => {
    test.beforeEach(() => {
        execSync('php artisan cache:clear', { stdio: 'ignore' })
    })

    // ── Validaciones 1+2: selección múltiple y contador ──────────────────────
    test('1. la selección múltiple muestra el contador en la BulkActionBar', async ({ page }) => {
        await loginToApp(page)
        await page.getByRole('link', { name: 'Órdenes de trabajo', exact: true }).click()
        await page.waitForURL('**/app/ordenes')

        // "Todas" muestra WOs cerradas (E2E-WO-0003) además de las activas
        await page.getByRole('button', { name: 'Todas', exact: true }).click()

        // Buscar fixtures de bulk para aislar exactamente 2 elementos
        await page.locator('input[placeholder*="Buscar"]').fill('E2E bulk')
        await page.waitForResponse(
            (r) =>
                r.url().includes('/api/v1/work-orders') &&
                r.url().includes('search=') &&
                r.request().method() === 'GET',
            { timeout: 10_000 },
        )

        // Seleccionar primer elemento (índice 0 = "Seleccionar todo", 1 = primera fila)
        const checkboxes = page.locator('input[type="checkbox"]')
        await checkboxes.nth(1).check()
        await expect(page.getByText('1 seleccionado')).toBeVisible({ timeout: 5_000 })

        // Seleccionar segundo elemento
        await checkboxes.nth(2).check()
        await expect(page.getByText('2 seleccionados')).toBeVisible({ timeout: 5_000 })
    })

    // ── Validación 3: Seleccionar todo ────────────────────────────────────────
    test('2. "Seleccionar todo" selecciona todos los elementos visibles', async ({ page }) => {
        await loginToApp(page)
        await page.getByRole('link', { name: 'Órdenes de trabajo', exact: true }).click()
        await page.waitForURL('**/app/ordenes')

        await page.getByRole('button', { name: 'Todas', exact: true }).click()
        await page.locator('input[placeholder*="Buscar"]').fill('E2E bulk')
        const listResp = await page.waitForResponse(
            (r) =>
                r.url().includes('/api/v1/work-orders') &&
                r.url().includes('search=') &&
                r.request().method() === 'GET',
            { timeout: 10_000 },
        )
        const listBody = await listResp.json()
        const totalVisible = (listBody.data ?? []).length

        await page
            .locator('label')
            .filter({ hasText: 'Seleccionar todo' })
            .locator('input[type="checkbox"]')
            .check()

        // El contador debe igualar el número de elementos visibles
        await expect(
            page.getByText(`${totalVisible} seleccionado${totalVisible !== 1 ? 's' : ''}`),
        ).toBeVisible({ timeout: 5_000 })
    })

    // ── Validaciones 4+6+7+8: acción exitosa ─────────────────────────────────
    test('3. set_priority exitoso: toast de éxito, lista refresca, selección limpia', async ({ page }) => {
        await loginToApp(page)
        await page.getByRole('link', { name: 'Órdenes de trabajo', exact: true }).click()
        await page.waitForURL('**/app/ordenes')

        await page.getByRole('button', { name: 'Todas', exact: true }).click()
        // "[E2E success]" aísla E2E-WO-0004 y E2E-WO-0005 (ambas planned = editables).
        // "E2E bulk" incluye E2E-WO-0003 (closed, no editable) y fallaría el gate update.
        await page.locator('input[placeholder*="Buscar"]').fill('E2E success')
        await page.waitForResponse(
            (r) =>
                r.url().includes('/api/v1/work-orders') &&
                r.url().includes('search=') &&
                r.request().method() === 'GET',
            { timeout: 10_000 },
        )

        const checkboxes = page.locator('input[type="checkbox"]')
        await checkboxes.nth(1).check()
        await checkboxes.nth(2).check()
        await expect(page.getByText('2 seleccionados')).toBeVisible({ timeout: 5_000 })

        // Abrir menú Prioridad → elegir Alta.
        // dispatchEvent bypasses Playwright's pointer-event routing for absolute children
        // of fixed ancestors; force:true was insufficient because the event was captured
        // by an intermediate stacking context before reaching the Vue @click handler.
        await page.getByRole('button', { name: 'Prioridad' }).click()
        await page.getByRole('button', { name: 'Alta', exact: true }).waitFor({ state: 'attached' })
        await page.getByRole('button', { name: 'Alta', exact: true }).dispatchEvent('click')
        await expect(page.getByText(/Prioridad: Alta en/)).toBeVisible({ timeout: 5_000 })

        // Click Confirmar → esperar PATCH bulk
        const [bulkResp] = await Promise.all([
            page.waitForResponse(
                (r) =>
                    r.url().includes('/work-orders/bulk') &&
                    r.request().method() === 'PATCH',
                { timeout: 15_000 },
            ),
            page.getByRole('button', { name: 'Confirmar', exact: true }).click(),
        ])

        // Validación 4: acción exitosa → succeeded = 2, sin fallos
        const bulkBody = await bulkResp.json()
        expect(bulkBody.succeeded).toBe(2)
        expect(bulkBody.failed).toHaveLength(0)

        // Validación 6: toast de éxito con el mensaje correcto
        await expect(page.getByText('2 órdenes actualizadas.')).toBeVisible({ timeout: 5_000 })

        // Validación 7: el listado se refresca (Vue llama a load() tras la acción)
        const refreshResp = await page.waitForResponse(
            (r) =>
                r.url().includes('/api/v1/work-orders') &&
                r.url().includes('search=') &&
                r.request().method() === 'GET',
            { timeout: 10_000 },
        )
        expect(refreshResp.status()).toBe(200)

        // Validación 8: selección limpia (BulkActionBar desaparece cuando count = 0)
        await expect(page.getByText(/\d+ seleccionados?/)).toHaveCount(0, { timeout: 5_000 })
    })

    // ── Validaciones 5+6: éxito parcial ──────────────────────────────────────
    test('4-5. cancel parcial: E2E-WO-0002 cancela, E2E-WO-0003 falla → toast advertencia', async ({ page }) => {
        await loginToApp(page)
        await page.getByRole('link', { name: 'Órdenes de trabajo', exact: true }).click()
        await page.waitForURL('**/app/ordenes')

        await page.getByRole('button', { name: 'Todas', exact: true }).click()
        await page.locator('input[placeholder*="Buscar"]').fill('E2E bulk')
        await page.waitForResponse(
            (r) =>
                r.url().includes('/api/v1/work-orders') &&
                r.url().includes('search=') &&
                r.request().method() === 'GET',
            { timeout: 10_000 },
        )

        // Seleccionar ambas WOs de bulk (una planned=cancelable, otra closed=no cancelable)
        const checkboxes = page.locator('input[type="checkbox"]')
        await checkboxes.nth(1).check()
        await checkboxes.nth(2).check()
        await expect(page.getByText('2 seleccionados')).toBeVisible({ timeout: 5_000 })

        // Aplicar acción "Cancelar" (danger)
        await page.getByRole('button', { name: 'Cancelar', exact: true }).click()
        await expect(page.getByText(/Cancelar.*elemento/)).toBeVisible({ timeout: 5_000 })

        const [bulkResp] = await Promise.all([
            page.waitForResponse(
                (r) =>
                    r.url().includes('/work-orders/bulk') &&
                    r.request().method() === 'PATCH',
                { timeout: 15_000 },
            ),
            page.getByRole('button', { name: 'Confirmar', exact: true }).click(),
        ])

        // Validación 5: éxito parcial — 1 cancelada (planned), 1 falla (closed→cancelled inválido)
        const bulkBody = await bulkResp.json()
        expect(bulkBody.succeeded).toBe(1)
        expect(bulkBody.failed).toHaveLength(1)

        // Validación 6: toast de advertencia con resumen parcial
        await expect(
            page.getByText(/1 órdenes actualizadas\. 1 no pudieron modificarse/),
        ).toBeVisible({ timeout: 5_000 })
    })

    // ── Validaciones 9+10: sin errores ────────────────────────────────────────
    test('6. sin errores de consola ni page errors en el flujo de bulk WOs', async ({ page }) => {
        const errors = []
        page.on('pageerror', (e) => errors.push(`pageerror: ${e.message}`))
        page.on('console', (msg) => {
            if (msg.type() !== 'error') return
            const text = msg.text()
            if (/favicon|manifest|Failed to load resource|net::ERR/i.test(text)) return
            if (/Content Security Policy/i.test(text)) return
            errors.push(`console: ${text}`)
        })

        await loginToApp(page)
        await page.getByRole('link', { name: 'Órdenes de trabajo', exact: true }).click()
        await page.waitForURL('**/app/ordenes')
        await page.getByRole('button', { name: 'Todas', exact: true }).click()
        await page.locator('input[placeholder*="Buscar"]').fill('E2E bulk')
        await page.waitForResponse(
            (r) =>
                r.url().includes('/api/v1/work-orders') &&
                r.url().includes('search=') &&
                r.request().method() === 'GET',
            { timeout: 10_000 },
        )

        // Seleccionar y limpiar sin ejecutar acción
        await page.locator('input[type="checkbox"]').nth(1).check()
        await expect(page.getByText('1 seleccionado')).toBeVisible({ timeout: 5_000 })
        await page.getByRole('button', { name: 'Limpiar', exact: true }).click()
        await page.waitForTimeout(400)

        expect(errors).toEqual([])
    })
})

// ══════════════════════════════════════════════════════════════════════════════
// Grupo 6B — Solicitudes de Mantenimiento
// ══════════════════════════════════════════════════════════════════════════════

test.describe.serial('Grupo 6B — Solicitudes bulk actions', () => {
    test.beforeEach(() => {
        execSync('php artisan cache:clear', { stdio: 'ignore' })
    })

    // ── Validaciones 1+2 ─────────────────────────────────────────────────────
    test('1. la selección múltiple muestra el contador en la BulkActionBar', async ({ page }) => {
        await loginToApp(page)
        await page.getByRole('link', { name: 'Solicitudes', exact: true }).click()
        await page.waitForURL('**/app/solicitudes')

        // Filtro "Pendientes" (submitted,under_review) muestra E2E-MR-001 y E2E-MR-002
        // El filtro "Pendientes" es el por defecto → ya está activo
        await page.waitForResponse(
            (r) =>
                r.url().includes('/api/v1/maintenance-requests') &&
                r.request().method() === 'GET',
            { timeout: 10_000 },
        )

        const checkboxes = page.locator('input[type="checkbox"]')
        await checkboxes.nth(1).check()
        await expect(page.getByText('1 seleccionado')).toBeVisible({ timeout: 5_000 })

        await checkboxes.nth(2).check()
        await expect(page.getByText('2 seleccionados')).toBeVisible({ timeout: 5_000 })
    })

    // ── Validación 3 ─────────────────────────────────────────────────────────
    test('2. "Seleccionar todo" selecciona todos los elementos visibles', async ({ page }) => {
        await loginToApp(page)
        await page.getByRole('link', { name: 'Solicitudes', exact: true }).click()
        await page.waitForURL('**/app/solicitudes')

        const listResp = await page.waitForResponse(
            (r) =>
                r.url().includes('/api/v1/maintenance-requests') &&
                r.request().method() === 'GET',
            { timeout: 10_000 },
        )
        const listBody = await listResp.json()
        const totalVisible = (listBody.data ?? []).length

        await page
            .locator('label')
            .filter({ hasText: 'Seleccionar todo' })
            .locator('input[type="checkbox"]')
            .check()

        await expect(
            page.getByText(`${totalVisible} seleccionado${totalVisible !== 1 ? 's' : ''}`),
        ).toBeVisible({ timeout: 5_000 })
    })

    // ── Validaciones 4+6+7+8 ─────────────────────────────────────────────────
    test('3. set_priority exitoso: toast de éxito, lista refresca, selección limpia', async ({ page }) => {
        await loginToApp(page)
        await page.getByRole('link', { name: 'Solicitudes', exact: true }).click()
        await page.waitForURL('**/app/solicitudes')

        await page.waitForResponse(
            (r) =>
                r.url().includes('/api/v1/maintenance-requests') &&
                r.request().method() === 'GET',
            { timeout: 10_000 },
        )

        const checkboxes = page.locator('input[type="checkbox"]')
        await checkboxes.nth(1).check()
        await checkboxes.nth(2).check()
        await expect(page.getByText('2 seleccionados')).toBeVisible({ timeout: 5_000 })

        // Abrir menú Prioridad → elegir Alta.
        // dispatchEvent bypasses stacking-context pointer-event routing (same fix as WOs y Equipos).
        await page.getByRole('button', { name: 'Prioridad' }).click()
        await page.getByRole('button', { name: 'Alta', exact: true }).waitFor({ state: 'attached' })
        await page.getByRole('button', { name: 'Alta', exact: true }).dispatchEvent('click')
        await expect(page.getByText(/Prioridad: Alta en/)).toBeVisible({ timeout: 5_000 })

        const [bulkResp] = await Promise.all([
            page.waitForResponse(
                (r) =>
                    r.url().includes('/maintenance-requests/bulk') &&
                    r.request().method() === 'PATCH',
                { timeout: 15_000 },
            ),
            page.getByRole('button', { name: 'Confirmar', exact: true }).click(),
        ])

        const bulkBody = await bulkResp.json()
        expect(bulkBody.succeeded).toBe(2)
        expect(bulkBody.failed).toHaveLength(0)

        await expect(page.getByText('2 solicitudes actualizadas.')).toBeVisible({ timeout: 5_000 })

        const refreshResp = await page.waitForResponse(
            (r) =>
                r.url().includes('/api/v1/maintenance-requests') &&
                r.request().method() === 'GET',
            { timeout: 10_000 },
        )
        expect(refreshResp.status()).toBe(200)

        await expect(page.getByText(/\d+ seleccionados?/)).toHaveCount(0, { timeout: 5_000 })
    })

    // ── Validaciones 5+6 ─────────────────────────────────────────────────────
    test('4-5. approve parcial: E2E-MR-001 aprobada, E2E-MR-002 falla → toast advertencia', async ({ page }) => {
        await loginToApp(page)
        await page.getByRole('link', { name: 'Solicitudes', exact: true }).click()
        await page.waitForURL('**/app/solicitudes')

        // "Pendientes" (submitted,under_review) — orden created_at DESC:
        //   nth(1) = E2E-MR-003 (submitted, más nuevo)
        //   nth(2) = E2E-MR-002 (submitted)
        //   nth(3) = E2E-MR-001 (under_review, más antiguo)
        // Seleccionamos nth(2)+nth(3): submitted + under_review → approve parcial.
        await page.waitForResponse(
            (r) =>
                r.url().includes('/api/v1/maintenance-requests') &&
                r.request().method() === 'GET',
            { timeout: 10_000 },
        )

        const checkboxes = page.locator('input[type="checkbox"]')
        await checkboxes.nth(2).check()
        await checkboxes.nth(3).check()
        await expect(page.getByText('2 seleccionados')).toBeVisible({ timeout: 5_000 })

        // Aplicar "Aprobar" → MR under_review→approved (éxito), MR submitted→approved (fallo)
        await page.getByRole('button', { name: 'Aprobar', exact: true }).click()
        await expect(page.getByText(/Aprobar.*elemento/)).toBeVisible({ timeout: 5_000 })

        const [bulkResp] = await Promise.all([
            page.waitForResponse(
                (r) =>
                    r.url().includes('/maintenance-requests/bulk') &&
                    r.request().method() === 'PATCH',
                { timeout: 15_000 },
            ),
            page.getByRole('button', { name: 'Confirmar', exact: true }).click(),
        ])

        const bulkBody = await bulkResp.json()
        // E2E-MR-001 nth(3) (under_review → approved): succeeds
        // E2E-MR-002 nth(2) (submitted → approved): canTransitionTo(Approved) = false → fails
        expect(bulkBody.succeeded).toBe(1)
        expect(bulkBody.failed).toHaveLength(1)

        await expect(
            page.getByText(/1 solicitudes actualizadas\. 1 no pudieron modificarse/),
        ).toBeVisible({ timeout: 5_000 })
    })

    // ── Validaciones 9+10 ────────────────────────────────────────────────────
    test('6. sin errores de consola ni page errors en el flujo de bulk solicitudes', async ({ page }) => {
        const errors = []
        page.on('pageerror', (e) => errors.push(`pageerror: ${e.message}`))
        page.on('console', (msg) => {
            if (msg.type() !== 'error') return
            const text = msg.text()
            if (/favicon|manifest|Failed to load resource|net::ERR/i.test(text)) return
            if (/Content Security Policy/i.test(text)) return
            errors.push(`console: ${text}`)
        })

        await loginToApp(page)
        await page.getByRole('link', { name: 'Solicitudes', exact: true }).click()
        await page.waitForURL('**/app/solicitudes')
        await page.waitForResponse(
            (r) =>
                r.url().includes('/api/v1/maintenance-requests') &&
                r.request().method() === 'GET',
            { timeout: 10_000 },
        )
        await page.waitForTimeout(400)

        expect(errors).toEqual([])
    })
})

// ══════════════════════════════════════════════════════════════════════════════
// Grupo 6C — Equipos
// ══════════════════════════════════════════════════════════════════════════════

test.describe.serial('Grupo 6C — Equipos bulk actions', () => {
    test.beforeEach(() => {
        execSync('php artisan cache:clear', { stdio: 'ignore' })
    })

    // ── Validaciones 1+2 ─────────────────────────────────────────────────────
    test('1. la selección múltiple muestra el contador en la BulkActionBar', async ({ page }) => {
        await loginToApp(page)
        await page.getByRole('link', { name: 'Equipos', exact: true }).click()
        await page.waitForURL('**/app/equipos')

        // Buscar "[E2E]" para aislar E2E-PRE-001 y E2E-PRE-002
        await page.locator('input[placeholder*="Buscar"]').fill('[E2E]')
        await page.waitForResponse(
            (r) =>
                r.url().includes('/api/v1/equipment') &&
                r.url().includes('search=') &&
                r.request().method() === 'GET',
            { timeout: 10_000 },
        )

        const checkboxes = page.locator('input[type="checkbox"]')
        await checkboxes.nth(1).check()
        await expect(page.getByText('1 seleccionado')).toBeVisible({ timeout: 5_000 })

        await checkboxes.nth(2).check()
        await expect(page.getByText('2 seleccionados')).toBeVisible({ timeout: 5_000 })
    })

    // ── Validación 3 ─────────────────────────────────────────────────────────
    test('2. "Seleccionar todo" selecciona todos los elementos visibles', async ({ page }) => {
        await loginToApp(page)
        await page.getByRole('link', { name: 'Equipos', exact: true }).click()
        await page.waitForURL('**/app/equipos')

        await page.locator('input[placeholder*="Buscar"]').fill('[E2E]')
        const listResp = await page.waitForResponse(
            (r) =>
                r.url().includes('/api/v1/equipment') &&
                r.url().includes('search=') &&
                r.request().method() === 'GET',
            { timeout: 10_000 },
        )
        const listBody = await listResp.json()
        const totalVisible = (listBody.data ?? []).length

        await page
            .locator('label')
            .filter({ hasText: 'Seleccionar todo' })
            .locator('input[type="checkbox"]')
            .check()

        await expect(
            page.getByText(`${totalVisible} seleccionado${totalVisible !== 1 ? 's' : ''}`),
        ).toBeVisible({ timeout: 5_000 })
    })

    // ── Validaciones 4+6+7+8 ─────────────────────────────────────────────────
    test('3. set_criticality exitoso: toast de éxito, lista refresca, selección limpia', async ({ page }) => {
        await loginToApp(page)
        await page.getByRole('link', { name: 'Equipos', exact: true }).click()
        await page.waitForURL('**/app/equipos')

        await page.locator('input[placeholder*="Buscar"]').fill('[E2E]')
        await page.waitForResponse(
            (r) =>
                r.url().includes('/api/v1/equipment') &&
                r.url().includes('search=') &&
                r.request().method() === 'GET',
            { timeout: 10_000 },
        )

        const checkboxes = page.locator('input[type="checkbox"]')
        await checkboxes.nth(1).check()
        await checkboxes.nth(2).check()
        await expect(page.getByText('2 seleccionados')).toBeVisible({ timeout: 5_000 })

        // Abrir menú Criticidad → elegir Medio
        // dispatchEvent bypasses stacking-context pointer-event routing;
        // getByRole('button') avoids strict-mode clash with 'Medio' badge on equipment rows.
        await page.getByRole('button', { name: 'Criticidad' }).click()
        await page.getByRole('button', { name: 'Medio', exact: true }).waitFor({ state: 'attached' })
        await page.getByRole('button', { name: 'Medio', exact: true }).dispatchEvent('click')
        await expect(page.getByText(/Criticidad: Medio en/)).toBeVisible({ timeout: 5_000 })

        const [bulkResp] = await Promise.all([
            page.waitForResponse(
                (r) =>
                    r.url().includes('/equipment/bulk') &&
                    r.request().method() === 'PATCH',
                { timeout: 15_000 },
            ),
            page.getByRole('button', { name: 'Confirmar', exact: true }).click(),
        ])

        const bulkBody = await bulkResp.json()
        expect(bulkBody.succeeded).toBe(2)
        expect(bulkBody.failed).toHaveLength(0)

        await expect(page.getByText('2 equipos actualizados.')).toBeVisible({ timeout: 5_000 })

        const refreshResp = await page.waitForResponse(
            (r) =>
                r.url().includes('/api/v1/equipment') &&
                r.url().includes('search=') &&
                r.request().method() === 'GET',
            { timeout: 10_000 },
        )
        expect(refreshResp.status()).toBe(200)

        await expect(page.getByText(/\d+ seleccionados?/)).toHaveCount(0, { timeout: 5_000 })
    })

    // ── Validaciones 5+6: éxito parcial vía API ──────────────────────────────
    test('4-5. éxito parcial en equipment bulk: API devuelve succeeded+failed correctamente', async ({ page }) => {
        // Las acciones masivas de equipos no tienen lógica de negocio que falle en la UI
        // (set_status y set_criticality son actualizaciones directas). El éxito parcial
        // se produce cuando un ID no pertenece al tenant (ModelNotFoundException).
        // Este escenario es imposible de reproducir desde la UI pero se valida aquí
        // mediante llamada directa a la API con un UUID inexistente.
        await loginToApp(page)

        const tok = await extractToken(page)
        expect(tok).toBeTruthy()

        // Obtener un equipo válido del tenant para la parte "exitosa"
        const listResp = await page.request.get('/api/v1/equipment', {
            headers: { Authorization: `Bearer ${tok}`, Accept: 'application/json' },
        })
        const listBody = await listResp.json()
        const validId = listBody.data?.[0]?.id
        expect(validId).toBeTruthy()

        // UUID que no existe en el tenant → ModelNotFoundException → fallo
        const fakeId = '00000000-0000-0000-0000-000000000000'

        const bulkResp = await page.request.patch('/api/v1/equipment/bulk', {
            headers: { Authorization: `Bearer ${tok}`, Accept: 'application/json' },
            data: { ids: [validId, fakeId], action: 'set_criticality', value: 'medium' },
        })

        expect(bulkResp.status()).toBe(200)
        const bulkBody = await bulkResp.json()

        // Validación 5: éxito parcial — 1 válido + 1 UUID inexistente
        expect(bulkBody.succeeded).toBe(1)
        expect(bulkBody.failed).toHaveLength(1)
        expect(bulkBody.failed[0].id).toBe(fakeId)
        // Validación 6: formato del error de ítem fallido
        expect(bulkBody.failed[0].error).toBeTruthy()
    })

    // ── Validaciones 9+10 ────────────────────────────────────────────────────
    test('6. sin errores de consola ni page errors en el flujo de bulk equipos', async ({ page }) => {
        const errors = []
        page.on('pageerror', (e) => errors.push(`pageerror: ${e.message}`))
        page.on('console', (msg) => {
            if (msg.type() !== 'error') return
            const text = msg.text()
            if (/favicon|manifest|Failed to load resource|net::ERR/i.test(text)) return
            if (/Content Security Policy/i.test(text)) return
            errors.push(`console: ${text}`)
        })

        await loginToApp(page)
        await page.getByRole('link', { name: 'Equipos', exact: true }).click()
        await page.waitForURL('**/app/equipos')
        await page.locator('input[placeholder*="Buscar"]').fill('[E2E]')
        await page.waitForResponse(
            (r) =>
                r.url().includes('/api/v1/equipment') &&
                r.url().includes('search=') &&
                r.request().method() === 'GET',
            { timeout: 10_000 },
        )
        await page.waitForTimeout(400)

        expect(errors).toEqual([])
    })
})
