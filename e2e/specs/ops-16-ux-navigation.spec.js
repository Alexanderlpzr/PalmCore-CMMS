/**
 * Grupo 16 — UX-1 Navigation Changes
 *
 * Tests the UX-1 sprint changes:
 *   A. Breadcrumb in WO detail shows "Órdenes de trabajo" as a clickeable link
 *   B. "Equipo asociado" card visible in WO detail with equipment
 *   C. Anchor nav bar in WO detail shows 4 sections
 *   D. Sidebar renamed: "Mantenimiento Programado", "Indicadores", "Resumen Ejecutivo"
 *   E. "Última orden de trabajo" card visible in equipment detail with WOs
 *
 * Seeded data:
 *   - Equipment: E2E-PRE-001 ("[E2E] Prensa Extractora Principal")
 *   - WorkOrder:  E2E-WO-0001 ("[E2E] OT para pruebas de inventario")
 *     associated with E2E-PRE-001
 */
import { execSync } from 'child_process'
import { test, expect } from '@playwright/test'
import { loginToApp } from '../helpers.js'

const WO_NUMBER = 'E2E-WO-0001'
const WO_TITLE = '[E2E] OT para pruebas de inventario'
const EQUIPMENT_CODE = 'E2E-PRE-001'
const EQUIPMENT_NAME = '[E2E] Prensa Extractora Principal'

/** Navigate to the WO detail page for E2E-WO-0001 from the dashboard. */
async function navigateToWoDetail(page) {
    await page.getByRole('link', { name: 'Órdenes de trabajo', exact: true }).click()
    await page.waitForURL('**/app/ordenes')
    // Switch to "Todas" to include WOs in any state (the WO may be closed after other test suites run)
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

// ══════════════════════════════════════════════════════════════════════════════
// Grupo 16A — Breadcrumb en OT
// ══════════════════════════════════════════════════════════════════════════════

test.describe('Grupo 16A — Breadcrumb en OT', () => {
    test.beforeEach(() => execSync('php artisan cache:clear', { stdio: 'ignore' }))

    test('16A-1: breadcrumb en OT muestra "Órdenes de trabajo" clickeable', async ({ page }) => {
        await loginToApp(page)
        await navigateToWoDetail(page)

        // The breadcrumb link is inside the top bar <nav>, not the sidebar.
        // Scope to <main> to avoid the strict-mode ambiguity with the sidebar link.
        const breadcrumb = page.getByRole('main').getByRole('link', { name: 'Órdenes de trabajo' })
        await expect(breadcrumb).toBeVisible({ timeout: 10_000 })

        // Verify it links to /app/ordenes
        const href = await breadcrumb.getAttribute('href')
        expect(href).toMatch(/\/app\/ordenes$|\/ordenes$/)
    })
})

// ══════════════════════════════════════════════════════════════════════════════
// Grupo 16B — Tarjeta Equipo asociado en OT
// ══════════════════════════════════════════════════════════════════════════════

test.describe('Grupo 16B — Tarjeta Equipo asociado en OT', () => {
    test.beforeEach(() => execSync('php artisan cache:clear', { stdio: 'ignore' }))

    test('16B-1: tarjeta "Equipo asociado" visible en OT con equipo', async ({ page }) => {
        await loginToApp(page)
        await navigateToWoDetail(page)

        await expect(page.getByText('Equipo asociado')).toBeVisible({ timeout: 10_000 })
        await expect(page.getByRole('link', { name: /Ver equipo/i })).toBeVisible({ timeout: 10_000 })
    })

    test('16B-2: "Ver equipo" navega al perfil del equipo', async ({ page }) => {
        await loginToApp(page)
        await navigateToWoDetail(page)

        await page.getByRole('link', { name: /Ver equipo/i }).click()
        await page.waitForURL('**/app/equipos/**', { timeout: 10_000 })

        // Verify we landed on the equipment detail page (equipment name is visible)
        await expect(page.getByText(EQUIPMENT_NAME, { exact: true })).toBeVisible({ timeout: 10_000 })
    })
})

// ══════════════════════════════════════════════════════════════════════════════
// Grupo 16C — Barra de anclas en OT
// ══════════════════════════════════════════════════════════════════════════════

test.describe('Grupo 16C — Barra de anclas en OT', () => {
    test.beforeEach(() => execSync('php artisan cache:clear', { stdio: 'ignore' }))

    test('16C-1: barra de anclas visible en OT con secciones Detalles/Técnicos/Partes/Comentarios', async ({ page }) => {
        await loginToApp(page)
        await navigateToWoDetail(page)

        await expect(page.getByRole('button', { name: 'Detalles' })).toBeVisible({ timeout: 10_000 })
        await expect(page.getByRole('button', { name: 'Técnicos' })).toBeVisible({ timeout: 10_000 })
        await expect(page.getByRole('button', { name: 'Partes' })).toBeVisible({ timeout: 10_000 })
        await expect(page.getByRole('button', { name: 'Comentarios' })).toBeVisible({ timeout: 10_000 })
    })
})

// ══════════════════════════════════════════════════════════════════════════════
// Grupo 16D — Sidebar renombrado
// ══════════════════════════════════════════════════════════════════════════════

test.describe('Grupo 16D — Sidebar renombrado', () => {
    test.beforeEach(() => execSync('php artisan cache:clear', { stdio: 'ignore' }))

    test('16D-1: sidebar muestra "Mantenimiento Programado" en lugar de "Preventivos"', async ({ page }) => {
        await loginToApp(page)

        await expect(page.getByRole('link', { name: 'Mantenimiento Programado' })).toBeVisible({ timeout: 10_000 })
        // "Preventivos" should not appear as a standalone nav link
        await expect(page.getByRole('link', { name: 'Preventivos', exact: true })).not.toBeVisible()
    })

    test('16D-2: sidebar muestra "Indicadores" en lugar de "KPIs"', async ({ page }) => {
        await loginToApp(page)

        await expect(page.getByRole('link', { name: 'Indicadores' })).toBeVisible({ timeout: 10_000 })
        await expect(page.getByRole('link', { name: 'KPIs', exact: true })).not.toBeVisible()
    })

    test('16D-3: sidebar muestra "Resumen Ejecutivo" en lugar de "Gerencial"', async ({ page }) => {
        await loginToApp(page)

        await expect(page.getByRole('link', { name: 'Resumen Ejecutivo' })).toBeVisible({ timeout: 10_000 })
        await expect(page.getByRole('link', { name: 'Gerencial', exact: true })).not.toBeVisible()
    })
})

// ══════════════════════════════════════════════════════════════════════════════
// Grupo 16E — Tarjeta "Última OT" en equipo
// ══════════════════════════════════════════════════════════════════════════════

test.describe('Grupo 16E — Tarjeta Última OT en equipo', () => {
    test.beforeEach(() => execSync('php artisan cache:clear', { stdio: 'ignore' }))

    test('16E-1: tarjeta "Última orden de trabajo" visible en equipo con OTs', async ({ page }) => {
        await loginToApp(page)
        await navigateToEquipmentDetail(page)

        await expect(page.getByText('Última orden de trabajo')).toBeVisible({ timeout: 10_000 })
        await expect(page.getByRole('link', { name: /Ver OT/i })).toBeVisible({ timeout: 10_000 })
    })
})
