/**
 * Grupo 22 — UX-3 Ficha 360° de la Orden de Trabajo (WorkOrderDetailView)
 *
 * Validates the redesigned 360° work-order console:
 *   A. Breadcrumbs + número de OT visibles
 *   B. Franja de KPIs (Tiempo real / Paro / Costo total)
 *   C. Navegación de pestañas (Resumen, Historial, Componentes,
 *      Tiempo & Repuestos, Evidencia)
 *   D. La sección Evidencia llega en la carga inicial de la OT — sin
 *      peticiones adicionales al activar la pestaña (WXA-2B: Evidence Zone)
 *   E. Timeline de estados (Historial) muestra al menos "Creada"
 *   F. Notas técnicas (compositor) viven dentro de la Evidence Zone
 *
 * Tolerant of empty data: asserts sections / labels / empty-states render,
 * not specific record counts.
 *
 * Seeded data (E2EDataSeeder):
 *   - WorkOrder: E2E-WO-0001 ("[E2E] OT para pruebas de inventario")
 *     associated with E2E-PRE-001
 */
import { execSync } from 'child_process'
import { test, expect } from '@playwright/test'
import { loginToApp } from '../helpers.js'

const WO_NUMBER = 'E2E-WO-0001'
const WO_TITLE = '[E2E] OT para pruebas de inventario'

/** Navigate to the WO detail page for E2E-WO-0001 from the dashboard. */
async function navigateToWoDetail(page) {
    await page.getByRole('link', { name: 'Órdenes de trabajo', exact: true }).click()
    await page.waitForURL('**/app/ordenes')
    // Switch to "Todas" to include WOs in any state (the WO may be closed after other suites run)
    await page.getByRole('button', { name: /^Todas$/i }).click()
    await page.locator('input[placeholder*="Buscar"]').fill(WO_NUMBER)
    await page.waitForResponse(
        (r) => r.url().includes('/api/v1/work-orders') && r.url().includes('search=') && r.request().method() === 'GET',
        { timeout: 10_000 },
    )
    await page.getByText(WO_TITLE, { exact: true }).first().click()
    await page.waitForURL('**/app/ordenes/**', { timeout: 15_000 })
    await page.waitForLoadState('networkidle', { timeout: 15_000 })
}

/**
 * Click a desktop anchor-nav tab by its visible label. Matches by prefix,
 * not exact text — tabs with items show a count badge next to the label
 * (e.g. "Historial 3"), which breaks an exact-name match.
 */
async function clickDesktopTab(page, label) {
    const btn = page.getByRole('button', { name: new RegExp(`^${label}`) }).filter({ visible: true }).first()
    await btn.click()
}

test.describe('Grupo 22 — Ficha 360° de la Orden de Trabajo', () => {
    test.beforeEach(() => execSync('php artisan cache:clear', { stdio: 'ignore' }))

    test('22A: breadcrumbs y número de OT visibles', async ({ page }) => {
        await loginToApp(page)
        await navigateToWoDetail(page)

        // Back/breadcrumb button + WO number both render in the sticky header.
        await expect(page.getByRole('main').getByText(WO_NUMBER).first()).toBeVisible({ timeout: 10_000 })
        await expect(page.getByRole('main').getByRole('heading', { name: WO_TITLE })).toBeVisible()
    })

    test('22B: franja de KPIs muestra Tiempo real / Paro / Costo total', async ({ page }) => {
        await loginToApp(page)
        await navigateToWoDetail(page)

        await expect(page.getByText('Tiempo real', { exact: true }).first()).toBeVisible({ timeout: 10_000 })
        await expect(page.getByText('Paro', { exact: true }).first()).toBeVisible()
        await expect(page.getByText('Costo total', { exact: true }).first()).toBeVisible()
    })

    test('22C: navegación de pestañas activa cada sección', async ({ page }) => {
        await loginToApp(page)
        await navigateToWoDetail(page)

        const tabs = [
            { label: 'Resumen', section: '#resumen' },
            { label: 'Historial', section: '#historial' },
            { label: 'Componentes', section: '#componentes' },
            { label: 'Tiempo & Repuestos', section: '#tiempo' },
            { label: 'Evidencia', section: '#evidencia' },
        ]

        for (const { label, section } of tabs) {
            await clickDesktopTab(page, label)
            await expect(page.locator(section)).toBeVisible({ timeout: 10_000 })
        }
    })

    test('22D: la Evidence Zone llega con la OT — sin petición adicional al abrir la pestaña', async ({ page }) => {
        await loginToApp(page)

        // The work order's own GET already carries attachments/signatures/checklist
        // (WXA-2B), so activating the Evidencia tab must NOT fire separate
        // /media or /signatures requests the way the old lazy tabs did.
        let mediaOrSignaturesRequested = false
        page.on('request', (req) => {
            if (/\/api\/v1\/work-orders\/[^/]+\/(media|signatures)(\?|$)/.test(req.url()) && req.method() === 'GET') {
                mediaOrSignaturesRequested = true
            }
        })

        await navigateToWoDetail(page)
        await clickDesktopTab(page, 'Evidencia')

        const evidencia = page.locator('#evidencia')
        await expect(evidencia).toBeVisible({ timeout: 10_000 })
        await expect(evidencia.getByText('Fotos y documentos')).toBeVisible()
        await expect(evidencia.getByRole('heading', { name: /^Firmas/ })).toBeVisible()
        expect(mediaOrSignaturesRequested).toBe(false)
    })

    test('22E: el Historial muestra el timeline de estados (al menos "Creada")', async ({ page }) => {
        await loginToApp(page)
        await navigateToWoDetail(page)

        await clickDesktopTab(page, 'Historial')

        const historial = page.locator('#historial')
        await expect(historial).toBeVisible({ timeout: 10_000 })
        await expect(historial.getByText('Creada', { exact: true })).toBeVisible({ timeout: 10_000 })
    })

    test('22F: notas técnicas (compositor) presente dentro de la Evidence Zone', async ({ page }) => {
        await loginToApp(page)
        await navigateToWoDetail(page)

        await clickDesktopTab(page, 'Evidencia')

        const evidencia = page.locator('#evidencia')
        await expect(evidencia).toBeVisible({ timeout: 10_000 })
        await expect(evidencia.getByRole('heading', { name: /^Notas técnicas/ })).toBeVisible()
        await expect(evidencia.locator('textarea[placeholder*="nota técnica"]')).toBeVisible()
    })
})
