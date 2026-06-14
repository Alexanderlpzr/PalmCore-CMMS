/**
 * Flow 4 — Webhook lifecycle:
 * Create webhook subscription → Create MR (fires event) → Verify delivery log entry
 *
 * Requirements:
 * - QUEUE_CONNECTION=sync  (set via webServer.env in playwright.config.js)
 * - Internet access to https://httpbin.org/post (or tests will still pass if delivery logs a failure)
 */
import { test, expect } from '@playwright/test'
import { adminUrl, PATHS } from '../helpers.js'

const WEBHOOK_URL = 'https://httpbin.org/post'

test.describe('Webhook flow', () => {
    let webhookViewUrl

    test('01 — create a webhook subscription via the UI', async ({ page }) => {
        await page.goto(adminUrl(`${PATHS.webhooks}/create`))

        await page.getByLabel(/URL de destino/i).fill(WEBHOOK_URL)

        // Subscribe to "Solicitud de mantenimiento creada"
        await page.getByLabel(/Solicitud de mantenimiento creada/i).check()

        await page.getByRole('button', { name: /Guardar|Crear/i }).click()
        await page.waitForURL(/webhook-subscriptions\/[^/]+$/)
        webhookViewUrl = page.url()

        await expect(page.getByText(WEBHOOK_URL)).toBeVisible()
        await expect(page.getByText(/mantenimiento creada/i)).toBeVisible()
    })

    test('02 — create a maintenance request to trigger the webhook event', async ({ page }) => {
        await page.goto(adminUrl(`${PATHS.maintenanceRequests}/create`))

        await page.getByLabel('Equipo').click()
        await page.getByLabel('Equipo').fill('E2E-PRE-001')
        await page.getByRole('option', { name: /E2E-PRE-001/i }).first().click()

        await page.getByLabel('Tipo').click()
        await page.getByRole('option', { name: /Correctiv/i }).first().click()

        await page.getByLabel('Prioridad').click()
        await page.getByRole('option').first().click()

        await page.getByLabel('Título').fill('[E2E] Solicitud para prueba de webhook')

        await page.getByRole('button', { name: /Guardar|Crear/i }).click()
        await page.waitForURL(/maintenance-requests\/[^/]+$/)

        await expect(page.getByText('[E2E] Solicitud para prueba de webhook')).toBeVisible()
    })

    test('03 — delivery log shows an entry for maintenance_request.created', async ({ page }) => {
        await page.goto(webhookViewUrl)

        // The "Historial de entregas recientes" section must contain an entry
        // Format: "[DD/MM HH:mm] maintenance_request.created — HTTP NNN ..."
        await expect(
            page.getByText(/maintenance_request\.created/i)
        ).toBeVisible({ timeout: 15_000 })
    })

    test('04 — webhook subscription shows updated stats', async ({ page }) => {
        await page.goto(webhookViewUrl)

        // After a delivery, either last_triggered_at was updated (success)
        // or last_error is populated (failure). Both are acceptable — we just
        // verify the delivery was attempted.
        const lastTriggered = page.getByText(/\d{2}\/\d{2}\/\d{4}/)
        const lastError = page.getByLabel(/Último error/i)

        await expect(lastTriggered.or(lastError)).toBeVisible({ timeout: 8_000 })
    })
})
