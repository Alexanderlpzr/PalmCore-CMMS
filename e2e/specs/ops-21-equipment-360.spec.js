/**
 * Grupo 21 — UX-3 Ficha 360° del Equipo (EquipmentDetailView)
 *
 * Validates the redesigned 360° equipment detail view:
 *   A. Breadcrumbs visible, starting with "Equipos"
 *   B. KPI strip visible (Disponibilidad / MTBF / MTTR)
 *   C. Desktop anchor-nav tabs navigate to each section
 *      (Información, Estado, Componentes, OTs, Mantenimiento, Documentos, Historial)
 *   D. "Estado del activo" section shows its 6 management metrics
 *   E. Componentes tab renders the EquipmentComponentsTab area (#partes)
 *   F. Historial timeline renders (events or empty-state — either accepted)
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

    test('21C: navegación de pestañas activa cada sección', async ({ page }) => {
        await loginToApp(page)
        await navigateToEquipmentDetail(page)

        // section id rendered for each desktop nav label
        const tabs = [
            { label: 'Información', section: '#info' },
            { label: 'Estado', section: '#estado' },
            { label: 'Componentes', section: '#partes' },
            { label: 'OTs', section: '#work-orders' },
            { label: 'Historial', section: '#timeline' },
        ]

        for (const { label, section } of tabs) {
            await clickDesktopTab(page, label)
            // Sections scroll into view on desktop; assert the anchor exists & is visible.
            await expect(page.locator(section)).toBeVisible({ timeout: 10_000 })
        }

        // Mantenimiento and Documentos nav tabs only render when there is data.
        // Assert they navigate when present, but tolerate their absence (empty demo data).
        for (const { label, section } of [
            { label: 'Mantenimiento', section: '#preventives' },
            { label: 'Documentos', section: '#documents' },
        ]) {
            const btn = page.getByRole('button', { name: label, exact: true }).filter({ visible: true }).first()
            if (await btn.count()) {
                await btn.click()
                await expect(page.locator(section)).toBeVisible({ timeout: 10_000 })
            }
        }
    })

    test('21D: sección "Estado del activo" muestra sus 6 métricas', async ({ page }) => {
        await loginToApp(page)
        await navigateToEquipmentDetail(page)

        await clickDesktopTab(page, 'Estado')

        const estado = page.locator('#estado')
        await expect(estado).toBeVisible({ timeout: 10_000 })
        await expect(estado.getByText('Estado del activo')).toBeVisible()

        for (const label of [
            'Disponibilidad',
            'MTTR',
            'MTBF',
            'Costo acumulado',
            'Horas de parada',
            'Última intervención',
        ]) {
            await expect(estado.getByText(label, { exact: true })).toBeVisible()
        }
    })

    test('21E: pestaña Componentes renderiza el área de componentes', async ({ page }) => {
        await loginToApp(page)
        await navigateToEquipmentDetail(page)

        await clickDesktopTab(page, 'Componentes')

        const partes = page.locator('#partes')
        await expect(partes).toBeVisible({ timeout: 10_000 })
        // EquipmentComponentsTab is mounted here, with the BOM hint underneath.
        await expect(partes.getByText('Preparado para explosión BOM')).toBeVisible()
    })

    test('21F: el Historial renderiza (timeline o estado vacío)', async ({ page }) => {
        await loginToApp(page)
        await navigateToEquipmentDetail(page)

        await clickDesktopTab(page, 'Historial')

        const timeline = page.locator('#timeline')
        await expect(timeline).toBeVisible({ timeout: 10_000 })
        await expect(timeline.getByText('Historial', { exact: true })).toBeVisible()

        // Either the empty-state message OR at least one activity entry is acceptable.
        const empty = timeline.getByText('Aún no hay actividad registrada para este equipo')
        const anyEvent = timeline.locator('.ring-2.ring-white').first()
        await expect(empty.or(anyEvent)).toBeVisible({ timeout: 10_000 })
    })
})
