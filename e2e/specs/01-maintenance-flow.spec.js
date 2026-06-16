/**
 * Flow 1 — Full maintenance lifecycle:
 * Create MR → Submit → Take for Review → Approve → Convert to WO
 *          → Plan → Start → Complete → Verify → Close OT
 */
import { test, expect } from '@playwright/test'
import { adminUrl, PATHS } from '../helpers.js'

test.describe('Maintenance flow', () => {
    let mrUrl
    let woUrl

    test('01 — create a maintenance request', async ({ page }) => {
        await page.goto(adminUrl(`${PATHS.maintenanceRequests}/create`))

        // Equipment — searchable custom select rendered as a button in Filament v5.
        // getByLabel finds the hidden native <select>; click the visible trigger instead.
        // Filament searches by displayed name, not by code.
        // Equipment name: "[E2E] Prensa Extractora Principal"
        await page.getByRole('button', { name: /Seleccione una opción/ }).click()
        await page.keyboard.type('Extractora')
        await page.getByRole('option', { name: /Prensa Extractora Principal/i }).first().click()

        // Tipo — non-searchable; rendered as a native <select> in Filament v5.
        await page.getByLabel('Tipo').selectOption({ label: 'Correctivo' })

        // Prioridad — native <select> with default P3 — Medio; accept the default.

        // Title
        await page.getByLabel('Título').fill('[E2E] Falla en prensa extractora')

        // Description
        await page.getByLabel('Descripción').fill('La prensa presenta ruido anormal al inicio del ciclo.')

        await page.getByRole('button', { name: 'Crear', exact: true }).click()
        // waitForURL with /[^/]+$/ matches the current /create URL immediately.
        // Wait specifically for the UUID-based view URL.
        await page.waitForURL(/maintenance-requests\/[0-9a-f-]{36}$/)
        mrUrl = page.url()

        await expect(page.getByText('[E2E] Falla en prensa extractora')).toBeVisible()
    })

    test('02 — submit the request for review', async ({ page }) => {
        await page.goto(mrUrl)
        await page.getByRole('button', { name: /Enviar para revisión/i }).click()
        const modal = page.locator('[role="dialog"]')
        if (await modal.isVisible({ timeout: 2000 }).catch(() => false)) {
            await modal.getByRole('button').filter({ hasNotText: /cancelar/i }).last().click()
            await modal.waitFor({ state: 'hidden', timeout: 10_000 })
        }
        await expect(page.getByText(/Enviado/i)).toBeVisible({ timeout: 10_000 })
    })

    test('03 — take the request for review', async ({ page }) => {
        await page.goto(mrUrl)
        await page.getByRole('button', { name: /Tomar para revisión/i }).click()
        const modal = page.locator('[role="dialog"]')
        if (await modal.isVisible({ timeout: 2000 }).catch(() => false)) {
            await modal.getByRole('button').filter({ hasNotText: /cancelar/i }).last().click()
            await modal.waitFor({ state: 'hidden', timeout: 10_000 })
        }
        await expect(page.getByText(/En Revisión/i)).toBeVisible({ timeout: 10_000 })
    })

    test('04 — approve the request', async ({ page }) => {
        await page.goto(mrUrl)
        await page.getByRole('button', { name: /Aprobar/i }).click()
        const modal = page.locator('[role="dialog"]')
        await modal.waitFor({ state: 'visible' })
        await modal.getByRole('button').filter({ hasNotText: /cancelar/i }).last().click()
        await modal.waitFor({ state: 'hidden', timeout: 10_000 })
        await expect(page.getByText(/Aprobad/i)).toBeVisible({ timeout: 10_000 })
    })

    test('05 — convert to work order', async ({ page }) => {
        await page.goto(mrUrl)
        await page.getByRole('button', { name: /Convertir a OT/i }).click()
        const modal = page.locator('[role="dialog"]')
        await modal.waitFor({ state: 'visible' })

        // Work order type (required in the conversion modal)
        const typeSelect = modal.getByLabel(/Tipo de OT|Tipo/i).first()
        if (await typeSelect.isVisible({ timeout: 2000 }).catch(() => false)) {
            await typeSelect.click()
            await page.getByRole('option', { name: /Correctiv/i }).first().click()
        }

        const saveBtn = modal.getByRole('button', { name: /Convertir|Crear OT|Guardar/i })
        if (await saveBtn.isVisible({ timeout: 1000 }).catch(() => false)) {
            await saveBtn.click()
        } else {
            await modal.getByRole('button').filter({ hasNotText: /cancelar/i }).last().click()
        }

        await modal.waitFor({ state: 'hidden', timeout: 15_000 })
        await expect(page.getByText(/Convertido a OT/i)).toBeVisible({ timeout: 10_000 })
    })

    test('06 — plan the work order', async ({ page }) => {
        await page.goto(mrUrl)

        // Find the link to the converted WO
        const woLink = page.getByRole('link', { name: /OT|Orden de Trabajo|E2E/i }).first()
        if (await woLink.isVisible({ timeout: 5000 }).catch(() => false)) {
            await woLink.click()
        } else {
            // Fall back to searching in the WO list
            await page.goto(adminUrl(PATHS.workOrders))
            await page.getByText('[E2E] Falla en prensa extractora').first().click()
        }

        await page.waitForURL(/work-orders\/[^/]+$/)
        woUrl = page.url()

        await page.getByRole('button', { name: 'Planificar' }).click()
        const modal = page.locator('[role="dialog"]')
        await modal.waitFor({ state: 'visible' })
        await modal.getByRole('button').filter({ hasNotText: /cancelar/i }).last().click()
        await modal.waitFor({ state: 'hidden', timeout: 10_000 })
        await expect(page.getByText(/Planificad/i)).toBeVisible({ timeout: 10_000 })
    })

    test('07 — start the work order', async ({ page }) => {
        await page.goto(woUrl)
        await page.getByRole('button', { name: /Iniciar trabajo/i }).click()
        const modal = page.locator('[role="dialog"]')
        await modal.waitFor({ state: 'visible' })
        await modal.getByRole('button').filter({ hasNotText: /cancelar/i }).last().click()
        await modal.waitFor({ state: 'hidden', timeout: 10_000 })
        await expect(page.getByText(/En Ejecución/i)).toBeVisible({ timeout: 10_000 })
    })

    test('08 — complete the work order', async ({ page }) => {
        await page.goto(woUrl)
        await page.getByRole('button', { name: 'Completar' }).click()
        const modal = page.locator('[role="dialog"]')
        await modal.waitFor({ state: 'visible' })
        await modal.getByLabel(/Trabajo realizado/i).fill('Se revisaron y reemplazaron los rodamientos de la prensa. Vibración corregida.')
        await modal.getByRole('button').filter({ hasNotText: /cancelar/i }).last().click()
        await modal.waitFor({ state: 'hidden', timeout: 10_000 })
        await expect(page.getByText(/Completad/i)).toBeVisible({ timeout: 10_000 })
    })

    test('09 — verify the work order', async ({ page }) => {
        await page.goto(woUrl)
        await page.getByRole('button', { name: 'Verificar' }).click()
        const modal = page.locator('[role="dialog"]')
        await modal.waitFor({ state: 'visible' })
        await modal.getByRole('button').filter({ hasNotText: /cancelar/i }).last().click()
        await modal.waitFor({ state: 'hidden', timeout: 10_000 })
        await expect(page.getByText(/Verificad/i)).toBeVisible({ timeout: 10_000 })
    })

    test('10 — close the work order', async ({ page }) => {
        await page.goto(woUrl)
        await page.getByRole('button', { name: /Cerrar OT/i }).click()
        const modal = page.locator('[role="dialog"]')
        await modal.waitFor({ state: 'visible' })
        await modal.getByRole('button').filter({ hasNotText: /cancelar/i }).last().click()
        await modal.waitFor({ state: 'hidden', timeout: 10_000 })
        await expect(page.getByText(/Cerrad/i)).toBeVisible({ timeout: 10_000 })
    })
})
