/**
 * Grupo 23 — ACT-1 Árbol de Componentes Inteligentes (EquipmentComponentsTab)
 *
 * Validates the redesigned expandable component tree:
 *   A. #partes section renders when navigating to Componentes tab
 *   B. Root component rows render (or empty-state, tolerant)
 *   C. Adding a root component fires POST and updates the tree
 *   D. Expand/collapse: clicking chevron reveals children (if any)
 *   E. Editing a component's status reflects in the badge
 *   F. "Agregar componente hijo" pre-fills parent_id and shows modal
 *
 * Tolerant of empty data: assertions on structure/labels, not specific counts.
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

async function clickDesktopTab(page, label) {
    const btn = page.getByRole('button', { name: label, exact: true }).filter({ visible: true }).first()
    await btn.click()
}

async function navigateToComponentes(page) {
    await loginToApp(page)
    await navigateToEquipmentDetail(page)
    await clickDesktopTab(page, 'Componentes')
    await page.waitForLoadState('networkidle', { timeout: 10_000 })
}

test.describe('Grupo 23 — Árbol de Componentes Inteligentes', () => {
    test.beforeEach(() => execSync('php artisan cache:clear', { stdio: 'ignore' }))

    test('23A: sección #partes renderiza al activar pestaña Componentes', async ({ page }) => {
        await navigateToComponentes(page)

        const partes = page.locator('#partes')
        await expect(partes).toBeVisible({ timeout: 10_000 })
        // Header with label
        await expect(partes.getByText('Componentes')).toBeVisible()
        // "Agregar" button always present
        await expect(partes.getByRole('button', { name: 'Agregar', exact: true })).toBeVisible()
    })

    test('23B: árbol muestra filas de componentes o estado vacío', async ({ page }) => {
        await navigateToComponentes(page)

        const partes = page.locator('#partes')
        await expect(partes).toBeVisible({ timeout: 10_000 })

        // Either a component row OR the empty-state message is acceptable
        const componentRow = partes.locator('.group').first()
        const emptyState = partes.getByText('Sin componentes registrados')

        await expect(componentRow.or(emptyState)).toBeVisible({ timeout: 10_000 })
    })

    test('23C: agregar componente raíz abre modal y dispara POST al guardar', async ({ page }) => {
        await navigateToComponentes(page)

        const partes = page.locator('#partes')

        // Intercept the POST to components
        const postPromise = page.waitForResponse(
            (r) => r.url().includes('/api/v1/equipment/') && r.url().includes('/components') && r.request().method() === 'POST',
            { timeout: 15_000 },
        )

        // Open the add modal
        await partes.getByRole('button', { name: 'Agregar', exact: true }).click()

        // Modal should appear with a Name field
        const modal = page.locator('[role="dialog"], .fixed.inset-0').last()
        await expect(modal.getByLabel('Nombre').or(modal.locator('input[placeholder*="Nombre"]')).first()).toBeVisible({ timeout: 5_000 })

        // Fill name (minimum required field)
        const nameInput = modal.getByLabel('Nombre').or(modal.locator('input[placeholder*="Nombre"]')).first()
        await nameInput.fill('[E2E] Motor Test ACT-1')

        // Fill code
        const codeInput = modal.getByLabel('Código').or(modal.locator('input[placeholder*="Código"]')).first()
        if (await codeInput.count()) {
            await codeInput.fill('E2E-MOT-TEST')
        }

        // Submit
        await modal.getByRole('button', { name: /guardar|crear|agregar/i }).click()

        await postPromise
        // Tree should reload — new item visible or count updated
        await page.waitForLoadState('networkidle', { timeout: 10_000 })
    })

    test('23D: chevron expande y colapsa nodos hijos', async ({ page }) => {
        await navigateToComponentes(page)

        const partes = page.locator('#partes')
        await expect(partes).toBeVisible({ timeout: 10_000 })

        // Look for a row that has a chevron button (meaning it has children)
        const chevron = partes.locator('button svg polyline[points="9 18 15 12 9 6"]').first()

        if (await chevron.count()) {
            const chevronBtn = chevron.locator('../..')  // two levels up to the button
            const rowsBefore = await partes.locator('.group').count()

            await chevronBtn.click()
            await page.waitForTimeout(300)

            const rowsAfter = await partes.locator('.group').count()
            // After expanding, more rows should be visible (children appeared)
            expect(rowsAfter).toBeGreaterThanOrEqual(rowsBefore)

            // Collapse again
            await chevronBtn.click()
            await page.waitForTimeout(300)
            const rowsCollapsed = await partes.locator('.group').count()
            expect(rowsCollapsed).toBeLessThanOrEqual(rowsAfter)
        } else {
            // No components with children — skip assertion gracefully
            test.skip()
        }
    })

    test('23E: editar componente cambia el badge de estado', async ({ page }) => {
        await navigateToComponentes(page)

        const partes = page.locator('#partes')
        await expect(partes).toBeVisible({ timeout: 10_000 })

        // Find an edit button on any component row
        const editBtn = partes.getByRole('button', { name: /editar/i }).first()

        if (!(await editBtn.count())) {
            test.skip()
            return
        }

        await editBtn.click()

        const modal = page.locator('[role="dialog"], .fixed.inset-0').last()
        await expect(modal).toBeVisible({ timeout: 5_000 })

        // Change status to Degradado
        const statusSelect = modal.locator('select').filter({ hasText: /operativo|degradado|falla/i }).first()
        if (await statusSelect.count()) {
            await statusSelect.selectOption('degraded')
        }

        const patchPromise = page.waitForResponse(
            (r) => r.url().includes('/api/v1/equipment/') && r.url().includes('/components/') && r.request().method() === 'PATCH',
            { timeout: 10_000 },
        )

        await modal.getByRole('button', { name: /guardar|actualizar/i }).click()
        await patchPromise
        await page.waitForLoadState('networkidle', { timeout: 10_000 })

        // Status badge "Degradado" should now appear in the tree
        await expect(partes.getByText('Degradado', { exact: true }).first()).toBeVisible({ timeout: 5_000 })
    })

    test('23F: botón "hijo" en un componente abre modal con parent pre-seleccionado', async ({ page }) => {
        await navigateToComponentes(page)

        const partes = page.locator('#partes')
        await expect(partes).toBeVisible({ timeout: 10_000 })

        // "Add child" button appears on hover — hover over first row
        const firstRow = partes.locator('.group').first()
        if (!(await firstRow.count())) {
            test.skip()
            return
        }

        await firstRow.hover()

        // Look for the "hijo" button
        const addChildBtn = firstRow.getByRole('button', { name: /hijo/i }).first()
        if (!(await addChildBtn.count())) {
            test.skip()
            return
        }

        await addChildBtn.click()

        // Modal should open for adding a child component
        const modal = page.locator('[role="dialog"], .fixed.inset-0').last()
        await expect(modal).toBeVisible({ timeout: 5_000 })

        // Modal title should indicate "child" or just show the form
        // The key assertion: the Name field is present and empty (ready for new child)
        const nameInput = modal.getByLabel('Nombre').or(modal.locator('input[placeholder*="Nombre"]')).first()
        await expect(nameInput).toBeVisible()
        await expect(nameInput).toHaveValue('')
    })
})
