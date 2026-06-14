/**
 * Flow 3 — Alert lifecycle:
 * View existing open alert → Resolve it → Verify resolved status in Alert Center
 */
import { test, expect } from '@playwright/test'
import { adminUrl, PATHS } from '../helpers.js'

test.describe('Alert flow', () => {
    let alertUrl

    test('01 — open alert appears in the Alert Center list', async ({ page }) => {
        await page.goto(adminUrl(PATHS.alerts))
        await expect(
            page.getByText('[E2E] Vibración anormal en prensa E2E-PRE-001')
        ).toBeVisible({ timeout: 10_000 })
    })

    test('02 — navigate to alert view page', async ({ page }) => {
        await page.goto(adminUrl(PATHS.alerts))
        await page.getByText('[E2E] Vibración anormal en prensa E2E-PRE-001').click()
        await page.waitForURL(/alerts\/[^/]+$/)
        alertUrl = page.url()

        await expect(page.getByText(/Vibración superior a 12 mm\/s/)).toBeVisible()
        await expect(page.getByRole('button', { name: 'Resolver' })).toBeVisible()
    })

    test('03 — resolve the alert via modal confirmation', async ({ page }) => {
        await page.goto(alertUrl)

        await page.getByRole('button', { name: 'Resolver' }).click()
        const modal = page.locator('[role="dialog"]')
        await modal.waitFor({ state: 'visible' })

        await expect(modal.getByText(/corregida|resuelta/i)).toBeVisible()

        await modal.getByRole('button').filter({ hasNotText: /cancelar/i }).last().click()
        await modal.waitFor({ state: 'hidden', timeout: 10_000 })

        await expect(page.getByText(/Resuelt/i)).toBeVisible({ timeout: 10_000 })
        await expect(page.getByText('Alerta resuelta.')).toBeVisible({ timeout: 5_000 })
    })

    test('04 — resolved alert no longer shows Resolver button', async ({ page }) => {
        await page.goto(alertUrl)
        await expect(page.getByRole('button', { name: 'Resolver' })).not.toBeVisible({ timeout: 5_000 })
        await expect(page.getByRole('button', { name: 'Descartar' })).not.toBeVisible({ timeout: 5_000 })
    })
})
