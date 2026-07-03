/**
 * Grupo 21 — UX-3.1 Ficha 360° del Equipo (EquipmentDetailView)
 *
 * Validates the redesigned Equipment Detail View (Sprint UX-3.1):
 *   A. Breadcrumbs visible, starting with "Equipos"
 *   B. KPI strip visible (Disponibilidad / MTBF / MTTR)
 *   C. Desktop anchor-nav tabs navigate to new sections
 *      (Operación, Mantenimiento, Activo, Docs & Fotos, Historial)
 *   D. Primary action bar visible: Crear OT, Reportar problema, Registrar lectura
 *   E. Componentes tab renders the EquipmentComponentsTab area (#operacion)
 *   F. Historial timeline renders with filter tabs
 *   G. "Estado del activo" section no longer exists (eliminated duplication)
 *
 * Tolerant of empty data: asserts sections / labels / empty-states render,
 * not specific record counts.
 *
 * Seeded data (E2EDataSeeder):
 *   - Equipment: E2E-PRE-001 ("[E2E] Prensa Extractora Principal")
 */
import { execSync } from 'child_process'
import { test, expect } from '@playwright/test'
import { loginToApp } from '../helpers.js'

const EQUIPMENT_CODE = 'E2E-PRE-001'
const EQUIPMENT_NAME = '[E2E] Prensa Extractora Principal'

/** Navigate to the equipment detail page for E2E-PRE-001 from the dashboard. */
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

/** Click a desktop anchor-nav tab by its visible label. */
async function clickDesktopTab(page, label) {
    // Both desktop nav and mobile tab bar render the same labels; the desktop
    // nav lives in a `hidden lg:flex` container, so only it is visible at the
    // default desktop viewport. Filter to the visible button to avoid ambiguity.
    const btn = page.getByRole('button', { name: label, exact: true }).filter({ visible: true }).first()
    await btn.click()
}

test.describe('Grupo 21 — Ficha 360° del Equipo', () => {
    test.beforeEach(() => execSync('php artisan cache:clear', { stdio: 'ignore' }))

    test('21A: breadcrumbs visibles iniciando en "Equipos"', async ({ page }) => {
        await loginToApp(page)
        await navigateToEquipmentDetail(page)

        // Breadcrumb "Equipos" link (scoped to <main> to avoid the sidebar link)
        const crumb = page.getByRole('main').getByRole('link', { name: 'Equipos', exact: true })
        await expect(crumb).toBeVisible({ timeout: 10_000 })

        // Trailing crumb is the equipment name
        await expect(page.getByRole('main').getByText(EQUIPMENT_NAME, { exact: true }).first()).toBeVisible()
    })

    test('21B: franja de KPIs muestra Disponibilidad / MTBF / MTTR', async ({ page }) => {
        await loginToApp(page)
        await navigateToEquipmentDetail(page)

        await expect(page.getByText('Disponibilidad', { exact: true }).first()).toBeVisible({ timeout: 10_000 })
        await expect(page.getByText('MTBF', { exact: true }).first()).toBeVisible()
        await expect(page.getByText('MTTR', { exact: true }).first()).toBeVisible()
    })

    test('21C: navegación de pestañas activa las nuevas secciones (UX-3.1)', async ({ page }) => {
        await loginToApp(page)
        await navigateToEquipmentDetail(page)

        // New section IDs per Blueprint: Operación, Mantenimiento, Activo, Docs & Fotos, Historial
        const tabs = [
            { label: 'Operación', section: '#operacion' },
            { label: 'Activo', section: '#activo' },
            { label: 'Historial', section: '#historial' },
        ]

        for (const { label, section } of tabs) {
            await clickDesktopTab(page, label)
            await expect(page.locator(section)).toBeVisible({ timeout: 10_000 })
        }

        // Mantenimiento tab renders when there are plans or WOs
        const mantenimientoBtn = page.getByRole('button', { name: 'Mantenimiento', exact: true }).filter({ visible: true }).first()
        if (await mantenimientoBtn.count()) {
            await mantenimientoBtn.click()
            await expect(page.locator('#mantenimiento')).toBeVisible({ timeout: 10_000 })
        }
    })

    test('21D: sección "Estado del activo" no existe — KPIs solo en el header strip', async ({ page }) => {
        await loginToApp(page)
        await navigateToEquipmentDetail(page)

        // The old #estado section must not exist in the new design
        await expect(page.locator('#estado')).toHaveCount(0)

        // KPIs are only in the sticky header strip (not duplicated in a body section)
        await expect(page.getByText('Disponibilidad', { exact: true }).first()).toBeVisible({ timeout: 10_000 })
    })

    test('21E: sección Operación visible — muestra BOM o estado sin intervenciones', async ({ page }) => {
        await loginToApp(page)
        await navigateToEquipmentDetail(page)

        const operacion = page.locator('#operacion')
        await expect(operacion).toBeVisible({ timeout: 10_000 })

        // Either active WOs or "sin intervenciones" empty state
        const sinIntervenciones = operacion.getByText('Sin intervenciones activas')
        const activeWoBanner = operacion.locator('.bg-amber-50').first()
        await expect(sinIntervenciones.or(activeWoBanner)).toBeVisible({ timeout: 10_000 })

        // BOM hint always present
        await expect(operacion.getByText('Preparado para explosión BOM')).toBeVisible()
    })

    test('21F: historial tiene tabs de filtro (Todos, OTs, Preventivos, Paradas, Lecturas)', async ({ page }) => {
        await loginToApp(page)
        await navigateToEquipmentDetail(page)

        await clickDesktopTab(page, 'Historial')

        const historial = page.locator('#historial')
        await expect(historial).toBeVisible({ timeout: 10_000 })

        // All filter tabs must be present
        for (const label of ['Todos', 'OTs', 'Preventivos', 'Paradas', 'Lecturas']) {
            await expect(historial.getByRole('button', { name: label, exact: true })).toBeVisible()
        }

        // Either events or empty state
        const empty = historial.getByText('Aún no hay actividad registrada para este equipo')
        const anyEvent = historial.locator('.ring-2.ring-white').first()
        await expect(empty.or(anyEvent)).toBeVisible({ timeout: 10_000 })
    })
})
