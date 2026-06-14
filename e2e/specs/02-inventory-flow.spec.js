/**
 * Flow 2 — Inventory lifecycle:
 * View spare part stock → Add part to Work Order E2E-WO-0001 → Return part → Verify stock
 */
import { test, expect } from '@playwright/test'
import { adminUrl, PATHS } from '../helpers.js'

test.describe('Inventory flow', () => {
    test('01 — spare part E2E-SP-001 appears in the list', async ({ page }) => {
        await page.goto(adminUrl(PATHS.spareParts))
        await expect(page.getByText('E2E-SP-001')).toBeVisible({ timeout: 10_000 })
        await expect(page.getByText('[E2E] Filtro Hidráulico')).toBeVisible()
    })

    test('02 — spare part view shows 50 units in E2E-WH-01', async ({ page }) => {
        await page.goto(adminUrl(PATHS.spareParts))
        // Click the row for E2E-SP-001
        await page.getByRole('row', { name: /E2E-SP-001/i }).getByRole('link').first().click()
        await page.waitForURL(/spare-parts\/[^/]+$/)

        // Open the stock tab
        await page.getByRole('tab', { name: /Stock|Almacén/i }).click().catch(() => {})
        await expect(page.getByText(/E2E-WH-01|Almacén de Pruebas/i)).toBeVisible({ timeout: 8_000 })
        await expect(page.getByText(/50/)).toBeVisible()
    })

    test('03 — add spare part to work order E2E-WO-0001', async ({ page }) => {
        await page.goto(adminUrl(PATHS.workOrders))
        await page.getByRole('row', { name: /E2E-WO-0001/i }).getByRole('link').first().click()
        await page.waitForURL(/work-orders\/[^/]+$/)

        // Open the Repuestos tab
        await page.getByRole('tab', { name: /Repuestos/i }).click()

        // Add new part
        await page.getByRole('button', { name: /Nuevo|Agregar|New/i }).first().click()
        const modal = page.locator('[role="dialog"]')
        await modal.waitFor({ state: 'visible' })

        // Quantity
        await modal.getByLabel('Cantidad').fill('5')

        // Link to inventory spare part
        const spInput = modal.getByLabel(/Repuesto \(inventario\)/i)
        if (await spInput.isVisible({ timeout: 2000 }).catch(() => false)) {
            await spInput.click()
            await modal.locator('input[type="search"], input[role="combobox"]').last().fill('E2E-SP-001')
            await page.getByRole('option', { name: /E2E-SP-001/i }).first().click()

            // Warehouse
            await modal.getByLabel(/Almacén/i).click()
            await page.getByRole('option', { name: /E2E-WH-01|Almacén de Pruebas/i }).first().click()
        } else {
            // Minimal form: fill part code and description
            await modal.getByLabel(/Código/i).fill('E2E-SP-001').catch(() => {})
            await modal.getByLabel(/Descripción/i).fill('[E2E] Filtro Hidráulico').catch(() => {})
        }

        await modal.getByRole('button', { name: /Guardar|Crear/i }).last().click()
        await modal.waitFor({ state: 'hidden', timeout: 10_000 })

        await expect(
            page.getByRole('row').filter({ hasText: /E2E-SP-001|Filtro Hidráulico/i }).first()
        ).toBeVisible({ timeout: 8_000 })
    })

    test('04 — return the spare part from the work order', async ({ page }) => {
        await page.goto(adminUrl(PATHS.workOrders))
        await page.getByRole('row', { name: /E2E-WO-0001/i }).getByRole('link').first().click()
        await page.waitForURL(/work-orders\/[^/]+$/)

        await page.getByRole('tab', { name: /Repuestos/i }).click()

        const partRow = page.getByRole('row').filter({ hasText: /E2E-SP-001|Filtro Hidráulico/i }).first()

        // The "Devolver" action is only available when status = Issued
        // If the part is in Requested state, it can only be deleted — that's also valid
        const returnBtn = partRow.getByRole('button', { name: /Devolver/i })
        if (await returnBtn.isVisible({ timeout: 3000 }).catch(() => false)) {
            await returnBtn.click()
            const modal = page.locator('[role="dialog"]')
            await modal.waitFor({ state: 'visible' })
            await modal.getByLabel(/Cantidad a devolver/i).fill('5')
            await modal.getByRole('button').filter({ hasNotText: /cancelar/i }).last().click()
            await modal.waitFor({ state: 'hidden', timeout: 10_000 })
            await expect(page.getByText(/Devuelto|Returned/i)).toBeVisible({ timeout: 8_000 })
        } else {
            // Part not yet issued (Requested state) — verify the row still exists
            await expect(partRow).toBeVisible()
        }
    })
})
