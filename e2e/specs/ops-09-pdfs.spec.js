/**
 * Grupo 9 — PDFs
 *
 * Valida el flujo completo de descarga de los 4 tipos de PDF implementados en P6.
 *
 * Mecanismo de descarga (useApi.download):
 *   1. fetch() → GET /api/v1/reports/... con Accept: application/pdf
 *   2. Backend retorna blob con Content-Type: application/pdf
 *   3. JS crea blobURL, crea <a download="filename">, click(), remove()
 *   4. Navegador inicia descarga
 *
 * Por cada PDF se verifica:
 *   - Status 200 (waitForResponse sobre el fetch interno)
 *   - Content-Type: application/pdf
 *   - Descarga correcta (waitForEvent('download'))
 *   - Nombre del archivo correcto (download.suggestedFilename())
 *   - Sin errores de consola
 *   - Sin page errors
 *
 * E2E fixtures:
 *   WO:        E2E-WO-0001 ("[E2E] OT para pruebas de inventario")
 *   Equipment: E2E-PRE-001 ("[E2E] Prensa Extractora Principal")
 *   Inventory: todos los repuestos (endpoint sin fixture específico)
 *   Reliability: todos los equipos (endpoint sin fixture específico)
 */
import { execSync } from 'child_process'
import { test, expect } from '@playwright/test'
import { loginToApp, appUrl } from '../helpers.js'

async function navTo(page, label) {
    await page.getByRole('link', { name: label, exact: true }).first().click()
}

/** Registers console-error and pageerror listeners. Returns accumulated errors array. */
function setupErrorListeners(page) {
    const errors = []
    page.on('pageerror', (e) => errors.push(`pageerror: ${e.message}`))
    page.on('console', (msg) => {
        if (msg.type() !== 'error') { return }
        const text = msg.text()
        if (/favicon|manifest|Failed to load resource|net::ERR/i.test(text)) { return }
        if (/Content Security Policy/i.test(text)) { return }
        errors.push(`console: ${text}`)
    })
    return errors
}

// ══════════════════════════════════════════════════════════════════════════════
// Grupo 9A — PDF de Orden de Trabajo
// ══════════════════════════════════════════════════════════════════════════════

test.describe.serial('Grupo 9A — PDF de Orden de Trabajo', () => {
    test.beforeEach(() => execSync('php artisan cache:clear', { stdio: 'ignore' }))

    test('descarga PDF de OT: status 200, Content-Type, filename, sin errores', async ({ page }) => {
        const errors = setupErrorListeners(page)

        await loginToApp(page)

        // Navegar a la lista de OTs y buscar la E2E
        await navTo(page, 'Órdenes de trabajo')
        await page.waitForURL('**/app/ordenes')
        await page.locator('input[placeholder*="Buscar"]').fill('E2E-WO-0001')
        await page.waitForResponse(
            (r) => r.url().includes('/api/v1/work-orders') && r.url().includes('search=') && r.request().method() === 'GET',
            { timeout: 10_000 },
        )

        // Navegar al detalle haciendo click en el título de la OT
        await page.getByText('[E2E] OT para pruebas de inventario', { exact: true }).first().click()
        await page.waitForURL('**/app/ordenes/**')
        await page.waitForLoadState('networkidle', { timeout: 15_000 })

        // El botón PDF solo se renderiza cuando el WO está cargado (v-if="wo")
        const pdfBtn = page.getByRole('button', { name: 'PDF', exact: true })
        await expect(pdfBtn).toBeVisible({ timeout: 5_000 })

        // Descargar PDF: capturar response HTTP y evento de descarga simultáneamente
        const [response, download] = await Promise.all([
            page.waitForResponse(
                (r) => r.url().includes('/api/v1/reports/work-orders/') && r.request().method() === 'GET',
                { timeout: 30_000 },
            ),
            page.waitForEvent('download', { timeout: 30_000 }),
            pdfBtn.click(),
        ])

        // Validaciones de HTTP
        expect(response.status()).toBe(200)
        expect(response.headers()['content-type']).toContain('application/pdf')

        // Validación de descarga y nombre de archivo
        expect(download.suggestedFilename()).toBe('E2E-WO-0001.pdf')

        await page.waitForTimeout(400)
        expect(errors).toEqual([])
    })
})

// ══════════════════════════════════════════════════════════════════════════════
// Grupo 9B — PDF de Equipo
// ══════════════════════════════════════════════════════════════════════════════

test.describe.serial('Grupo 9B — PDF de Equipo', () => {
    test.beforeEach(() => execSync('php artisan cache:clear', { stdio: 'ignore' }))

    test('descarga PDF de Equipo: status 200, Content-Type, filename, sin errores', async ({ page }) => {
        const errors = setupErrorListeners(page)

        await loginToApp(page)

        // Navegar a Equipos y buscar E2E-PRE-001
        await navTo(page, 'Equipos')
        await page.waitForURL('**/app/equipos')
        await page.locator('input[placeholder*="Buscar"]').fill('E2E-PRE-001')
        await page.waitForResponse(
            (r) => r.url().includes('/api/v1/equipment') && r.url().includes('search=') && r.request().method() === 'GET',
            { timeout: 10_000 },
        )

        // Navegar al detalle haciendo click en el nombre del equipo
        await page.getByText('[E2E] Prensa Extractora Principal', { exact: true }).first().click()
        await page.waitForURL('**/app/equipos/**')
        await page.waitForLoadState('networkidle', { timeout: 15_000 })

        // El botón PDF solo se renderiza cuando el equipo está cargado (v-if en toolbar)
        const pdfBtn = page.getByRole('button', { name: 'PDF', exact: true })
        await expect(pdfBtn).toBeVisible({ timeout: 5_000 })

        const [response, download] = await Promise.all([
            page.waitForResponse(
                (r) => r.url().includes('/api/v1/reports/equipment/') && r.request().method() === 'GET',
                { timeout: 30_000 },
            ),
            page.waitForEvent('download', { timeout: 30_000 }),
            pdfBtn.click(),
        ])

        expect(response.status()).toBe(200)
        expect(response.headers()['content-type']).toContain('application/pdf')
        expect(download.suggestedFilename()).toBe('E2E-PRE-001.pdf')

        await page.waitForTimeout(400)
        expect(errors).toEqual([])
    })
})

// ══════════════════════════════════════════════════════════════════════════════
// Grupo 9C — PDF de Inventario
// ══════════════════════════════════════════════════════════════════════════════

test.describe.serial('Grupo 9C — PDF de Inventario', () => {
    test.beforeEach(() => execSync('php artisan cache:clear', { stdio: 'ignore' }))

    test('descarga PDF de Inventario: status 200, Content-Type, filename, sin errores', async ({ page }) => {
        const errors = setupErrorListeners(page)

        await loginToApp(page)

        await navTo(page, 'Repuestos')
        await page.waitForURL('**/app/repuestos')
        await page.waitForLoadState('networkidle', { timeout: 10_000 })

        // El botón "PDF inventario" siempre está visible en Repuestos
        const pdfBtn = page.getByRole('button', { name: 'PDF inventario', exact: true })
        await expect(pdfBtn).toBeVisible({ timeout: 5_000 })

        const today = new Date().toISOString().slice(0, 10)

        const [response, download] = await Promise.all([
            page.waitForResponse(
                (r) => r.url().includes('/api/v1/reports/inventory') && r.request().method() === 'GET',
                { timeout: 30_000 },
            ),
            page.waitForEvent('download', { timeout: 30_000 }),
            pdfBtn.click(),
        ])

        expect(response.status()).toBe(200)
        expect(response.headers()['content-type']).toContain('application/pdf')
        expect(download.suggestedFilename()).toBe(`inventario-${today}.pdf`)

        await page.waitForTimeout(400)
        expect(errors).toEqual([])
    })
})

// ══════════════════════════════════════════════════════════════════════════════
// Grupo 9D — PDF de Confiabilidad
// ══════════════════════════════════════════════════════════════════════════════

test.describe.serial('Grupo 9D — PDF de Confiabilidad', () => {
    test.beforeEach(() => execSync('php artisan cache:clear', { stdio: 'ignore' }))

    test('descarga PDF de Confiabilidad: status 200, Content-Type, filename, sin errores', async ({ page }) => {
        const errors = setupErrorListeners(page)

        await loginToApp(page)

        await navTo(page, 'Reportes')
        await page.waitForURL('**/app/reportes')

        // Esperar que carguen los KPIs desde /api/v1/reliability/kpis (endpoint de carga, NO el PDF)
        // El botón PDF solo aparece si kpis.length > 0 (v-if="!loading && kpis.length")
        await page.waitForResponse(
            (r) => r.url().includes('/api/v1/reliability/kpis') && r.request().method() === 'GET',
            { timeout: 15_000 },
        )
        await page.waitForLoadState('networkidle', { timeout: 15_000 })

        // Si no hay datos de confiabilidad, el botón no aparece y el test falla con un mensaje claro
        const pdfBtn = page.getByRole('button', { name: 'PDF', exact: true })
        await expect(pdfBtn).toBeVisible({ timeout: 5_000 })

        const today = new Date().toISOString().slice(0, 10)

        const [response, download] = await Promise.all([
            page.waitForResponse(
                (r) => r.url().includes('/api/v1/reports/reliability') && r.request().method() === 'GET',
                { timeout: 30_000 },
            ),
            page.waitForEvent('download', { timeout: 30_000 }),
            pdfBtn.click(),
        ])

        expect(response.status()).toBe(200)
        expect(response.headers()['content-type']).toContain('application/pdf')
        expect(download.suggestedFilename()).toBe(`confiabilidad-${today}.pdf`)

        await page.waitForTimeout(400)
        expect(errors).toEqual([])
    })
})
