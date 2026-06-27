/**
 * Grupo 19 — Sprint PX-1.1 Contenido Institucional CMS
 *
 * Verifies the institutional content section on the Inicio screen:
 *   A. The CMS section is present on page load
 *   B. No console errors occur when the section renders
 *
 * Note: No seeded institutional content exists, so tests only verify
 * the section container mounts without error, not specific content items.
 */
import { test, expect } from '@playwright/test'
import { loginToApp } from '../helpers.js'

test.beforeEach(async ({ page }) => {
    await loginToApp(page)
    await page.waitForURL('**/app/inicio', { timeout: 15_000 })
    await page.waitForLoadState('networkidle', { timeout: 15_000 })
})

test('A — contenido global visible en Inicio', async ({ page }) => {
    await expect(page).toHaveURL(/\/app\/inicio/)

    // The institutional content section should be present in the DOM
    await expect(page.locator('[aria-label="Contenido institucional"]')).toBeVisible({ timeout: 10_000 })
})

test('B — sin errores de consola en Inicio con CMS section', async ({ page }) => {
    const errors = []
    page.on('pageerror', (err) => errors.push(err.message))

    await expect(page).toHaveURL(/\/app\/inicio/)

    // Wait briefly for any async rendering to settle
    await page.waitForLoadState('networkidle', { timeout: 10_000 })

    expect(errors).toHaveLength(0)
})
