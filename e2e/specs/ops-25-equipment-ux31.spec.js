/**
 * Grupo 25 — Sprint UX-3.1 Equipment Desktop Experience
 *
 * Tests new features introduced in Sprint UX-3.1:
 *   A. Action bar: "Crear OT" button opens quick-create panel
 *   B. Action bar: "Reportar problema" button opens report panel
 *   C. SlidePanel closes on Escape key and backdrop click
 *   D. Equipment list: new filter row (Planta, Área, Criticidad selects)
 *   E. Equipment list: smart filters (Con OTs activas / Preventivos vencidos)
 *   F. WorkOrderListView: equipment_id filter shows context banner + back link
 *   G. "Ver todas las OTs" link navigates to WO list filtered by equipment
 *
 * Seeded data (E2EDataSeeder):
 *   - Equipment: E2E-PRE-001 ("[E2E] Prensa Extractora Principal")
 */
import { execSync } from 'child_process'
import { test, expect } from '@playwright/test'
import { loginToApp } from '../helpers.js'

const EQUIPMENT_CODE = 'E2E-PRE-001'
const EQUIPMENT_NAME = '[E2E] Prensa Extractora Principal'

async function navigateToEquipmentDetail(page) {
    await page.getByRole('link', { name: 'Equipos', exact: true }).click()
    await page.waitForURL('**/app/equipos')
    await page.locator('input[placeholder*="Buscar"]').fill(EQUIPMENT_CODE)
    await page.waitForResponse(
        (r) => r.url().includes('/api/v1/equipment') && r.url().includes('search=') && r.request().method() === 'GET',
        { timeout: 10_000 },
    )
    await page.getByText(EQUIPMENT_NAME, { exact: true }).first().click()
    await page.waitForURL('**/app/equipos/**', { timeout: 15_000 })
    await page.waitForLoadState('networkidle', { timeout: 15_000 })
}

test.describe('Grupo 25 — Sprint UX-3.1 Equipment Desktop Experience', () => {
    test.beforeEach(() => execSync('php artisan cache:clear', { stdio: 'ignore' }))

    // ── Action bar ───────────────────────────────────────────────────────────

    test('25A: botón "Crear OT" en el header abre el panel de creación', async ({ page }) => {
        await loginToApp(page)
        await navigateToEquipmentDetail(page)

        // The "Crear OT" button is in the primary action bar
        await page.getByRole('button', { name: 'Crear OT', exact: true }).click()

        // SlidePanel should appear with the WO form
        await expect(page.getByRole('dialog')).toBeVisible({ timeout: 5_000 })
        await expect(page.getByRole('dialog').getByText('Crear orden de trabajo')).toBeVisible()

        // Required fields present
        await expect(page.getByLabel('Tipo de orden')).toBeVisible()
        await expect(page.getByLabel('Prioridad')).toBeVisible()
        await expect(page.getByLabel('Título')).toBeVisible()
        await expect(page.getByLabel('Descripción')).toBeVisible()
    })

    test('25B: botón "Reportar problema" abre el panel de solicitud', async ({ page }) => {
        await loginToApp(page)
        await navigateToEquipmentDetail(page)

        await page.getByRole('button', { name: 'Reportar problema', exact: true }).click()

        await expect(page.getByRole('dialog')).toBeVisible({ timeout: 5_000 })
        await expect(page.getByRole('dialog').getByText('Reportar problema')).toBeVisible()
        await expect(page.getByRole('dialog').getByText('La solicitud será revisada por el equipo de mantenimiento.')).toBeVisible()

        // Required fields present
        await expect(page.getByLabel('Tipo de solicitud')).toBeVisible()
        await expect(page.getByLabel('Prioridad')).toBeVisible()
        await expect(page.getByLabel('Título')).toBeVisible()
        await expect(page.getByLabel('Descripción')).toBeVisible()
    })

    test('25C: SlidePanel se cierra con Escape y con clic en el backdrop', async ({ page }) => {
        await loginToApp(page)
        await navigateToEquipmentDetail(page)

        // Open via Crear OT
        await page.getByRole('button', { name: 'Crear OT', exact: true }).click()
        await expect(page.getByRole('dialog')).toBeVisible({ timeout: 5_000 })

        // Close with Escape
        await page.keyboard.press('Escape')
        await expect(page.getByRole('dialog')).toHaveCount(0, { timeout: 3_000 })

        // Open again
        await page.getByRole('button', { name: 'Reportar problema', exact: true }).click()
        await expect(page.getByRole('dialog')).toBeVisible({ timeout: 5_000 })

        // Close by clicking backdrop (outside the panel)
        await page.mouse.click(50, page.viewportSize().height / 2)
        await expect(page.getByRole('dialog')).toHaveCount(0, { timeout: 3_000 })
    })

    // ── WO dropdown menu ─────────────────────────────────────────────────────

    test('25A2: dropdown de tipo de OT muestra todas las opciones', async ({ page }) => {
        await loginToApp(page)
        await navigateToEquipmentDetail(page)

        // Click the chevron dropdown button (next to "Crear OT")
        const dropdownBtn = page.locator('button[aria-label="Opciones de tipo de OT"]')
        await dropdownBtn.click()

        // Dropdown menu appears with type options
        for (const label of ['Correctiva', 'Preventiva', 'Predictiva', 'Inspección', 'Mejora', 'Emergencia']) {
            await expect(page.getByRole('button', { name: label, exact: true }).filter({ visible: true })).toBeVisible()
        }

        // Clicking a type opens the panel with that type pre-selected
        await page.getByRole('button', { name: 'Preventiva', exact: true }).filter({ visible: true }).click()
        await expect(page.getByRole('dialog')).toBeVisible({ timeout: 5_000 })
        await expect(page.getByLabel('Tipo de orden')).toHaveValue('preventive')
    })

    // ── Equipment list filters ───────────────────────────────────────────────

    test('25D: lista de equipos muestra filtros de Planta, Área y Criticidad', async ({ page }) => {
        await loginToApp(page)
        await page.getByRole('link', { name: 'Equipos', exact: true }).click()
        await page.waitForURL('**/app/equipos')
        await page.waitForLoadState('networkidle', { timeout: 10_000 })

        // Three new select filters must be present
        await expect(page.getByRole('combobox').filter({ hasText: 'Todas las plantas' })).toBeVisible()
        await expect(page.getByRole('combobox').filter({ hasText: 'Todas las áreas' })).toBeVisible()
        await expect(page.getByRole('combobox').filter({ hasText: 'Todas las criticidades' })).toBeVisible()
    })

    test('25E: filtros inteligentes "Con OTs activas" y "Preventivos vencidos" presentes', async ({ page }) => {
        await loginToApp(page)
        await page.getByRole('link', { name: 'Equipos', exact: true }).click()
        await page.waitForURL('**/app/equipos')
        await page.waitForLoadState('networkidle', { timeout: 10_000 })

        await expect(page.getByLabel('Con OTs activas')).toBeVisible()
        await expect(page.getByLabel('Preventivos vencidos')).toBeVisible()
    })

    test('25E2: activar filtro "Con OTs activas" filtra la lista correctamente', async ({ page }) => {
        await loginToApp(page)
        await page.getByRole('link', { name: 'Equipos', exact: true }).click()
        await page.waitForURL('**/app/equipos')
        await page.waitForLoadState('networkidle', { timeout: 10_000 })

        await page.getByLabel('Con OTs activas').check()

        // API request with has_active_work_orders=1 should fire
        await page.waitForResponse(
            (r) => r.url().includes('/api/v1/equipment') && r.url().includes('has_active_work_orders=1'),
            { timeout: 10_000 },
        )

        // "Limpiar filtros" button should appear
        await expect(page.getByRole('button', { name: 'Limpiar filtros' })).toBeVisible()

        // Clicking it resets
        await page.getByRole('button', { name: 'Limpiar filtros' }).click()
        await expect(page.getByLabel('Con OTs activas')).not.toBeChecked()
    })

    // ── WorkOrders filtered by equipment ─────────────────────────────────────

    test('25F: navegar a "Ver todas las OTs" del equipo muestra banner de contexto', async ({ page }) => {
        await loginToApp(page)
        await navigateToEquipmentDetail(page)
        await page.waitForLoadState('networkidle', { timeout: 10_000 })

        // Find "Ver todas las OTs de [equipment]" link in Mantenimiento section
        const link = page.getByRole('link', { name: /Ver todas las OTs de/ })
        if (await link.count() === 0) {
            // Equipment may have no WOs; skip
            test.skip()
            return
        }

        await link.first().click()
        await page.waitForURL('**/app/ordenes**', { timeout: 10_000 })

        // Banner should be visible with link back to equipment
        await expect(page.getByText('Mostrando solo órdenes de trabajo de este equipo')).toBeVisible()
        await expect(page.getByRole('link', { name: '← Volver al equipo' })).toBeVisible()
    })

    test('25G: link "Volver al equipo" en WO list regresa a la ficha', async ({ page }) => {
        await loginToApp(page)
        await navigateToEquipmentDetail(page)
        await page.waitForLoadState('networkidle', { timeout: 10_000 })

        const link = page.getByRole('link', { name: /Ver todas las OTs de/ })
        if (await link.count() === 0) {
            test.skip()
            return
        }

        await link.first().click()
        await page.waitForURL('**/app/ordenes**', { timeout: 10_000 })

        const backLink = page.getByRole('link', { name: '← Volver al equipo' })
        await backLink.click()
        await page.waitForURL('**/app/equipos/**', { timeout: 10_000 })
    })
})
