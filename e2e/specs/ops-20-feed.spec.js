/**
 * Grupo 20 — Sprint PX-1.2 Feed Empresarial
 *
 * Verifies the Feed Empresarial section on the Inicio screen:
 *   A. Feed section loads and displays filter tabs
 *   B. Clicking the OT filter tab activates it
 *   C. Empty state when no activity (skipped — requires seeded data control)
 */
import { test, expect } from '@playwright/test'
import { loginToApp } from '../helpers.js'

test.beforeEach(async ({ page }) => {
    await loginToApp(page)
    await page.goto('http://localhost:8000/app/inicio')
    await page.waitForURL('**/app/inicio', { timeout: 15_000 })
    await page.waitForLoadState('networkidle', { timeout: 15_000 })
})

test('A — Feed Empresarial se carga y muestra items', async ({ page }) => {
    const feedSection = page.locator('[aria-label="Feed empresarial"]')
    await expect(feedSection).toBeVisible({ timeout: 10_000 })

    // All filter tabs should be visible
    for (const label of ['Todo', 'OT', 'Equipos', 'Solicitudes', 'Mantenimiento']) {
        await expect(feedSection.getByRole('button', { name: label, exact: true })).toBeVisible()
    }
})

test('B — Feed filtra al hacer click en tab OT', async ({ page }) => {
    const feedSection = page.locator('[aria-label="Feed empresarial"]')
    await expect(feedSection).toBeVisible({ timeout: 10_000 })

    const otButton = feedSection.getByRole('button', { name: 'OT', exact: true })
    await otButton.click()

    // After clicking, the OT tab should have the active class (bg-emerald-600)
    await expect(otButton).toHaveClass(/bg-emerald-600/)

    // Wait for feed to reload after filter change
    await page.waitForLoadState('networkidle', { timeout: 10_000 })
})

test.skip('C — Feed muestra estado vacío si no hay actividad', async ({ page }) => {
    // Skipped: E2E environment has seeded data and we cannot guarantee an empty feed
    // without full control over the database state per test run.
    // This case is covered by the unit/feature test layer instead.
})
