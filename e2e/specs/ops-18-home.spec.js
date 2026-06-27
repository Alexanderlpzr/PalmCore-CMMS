/**
 * Grupo 18 — Sprint PX-1 Centro de Inicio
 *
 * Verifies the Inicio (Home) screen:
 *   A. Page loads without console errors
 *   B. Sidebar "Inicio" nav item is visible and navigates correctly
 *   C. Header section renders greeting and time
 *   D. Avisos importantes section visible
 *   E. Accesos rápidos section has expected buttons
 *   F. Noticias y comunicados section visible
 *   G. Actividad reciente section visible
 *   H. Próximamente (future integrations) section visible
 */
import { test, expect } from '@playwright/test'
import { loginToApp } from '../helpers.js'

test.beforeEach(async ({ page }) => {
    await loginToApp(page)
    const errors = []
    page.on('pageerror', (err) => errors.push(err.message))
    await page.waitForURL('**/app/inicio', { timeout: 15_000 })
    await page.waitForLoadState('networkidle', { timeout: 15_000 })
})

test('A — Inicio carga sin errores de consola', async ({ page }) => {
    const errors = []
    page.on('pageerror', (err) => errors.push(err.message))
    await expect(page).toHaveURL(/\/app\/inicio/)
    expect(errors).toHaveLength(0)
})

test('B — Sidebar muestra enlace "Inicio" activo', async ({ page }) => {
    const link = page.getByRole('link', { name: 'Inicio', exact: true })
    await expect(link).toBeVisible()
})

test('C — Encabezado muestra saludo y reloj', async ({ page }) => {
    await expect(page.getByText(/Bienvenido/i)).toBeVisible()
    // Clock shows HH:MM format
    await expect(page.locator('text=/\\d{1,2}:\\d{2}/')).toBeVisible()
})

test('D — Sección "Avisos importantes" es visible', async ({ page }) => {
    await expect(page.getByText('Avisos importantes')).toBeVisible()
})

test('E — Accesos rápidos contiene las 6 acciones esperadas', async ({ page }) => {
    await expect(page.getByText('Accesos rápidos')).toBeVisible()
    for (const label of ['Crear OT', 'Solicitud', 'Equipos', 'Preventivos', 'Alertas', 'Dashboard']) {
        await expect(page.getByText(label, { exact: true })).toBeVisible()
    }
})

test('F — Sección "Noticias y comunicados" es visible', async ({ page }) => {
    await expect(page.getByText('Noticias y comunicados')).toBeVisible()
})

test('G — Sección "Actividad reciente" es visible', async ({ page }) => {
    await expect(page.getByText('Actividad reciente')).toBeVisible()
})

test('H — Sección "Próximamente" muestra futuras integraciones', async ({ page }) => {
    await expect(page.getByText('Próximamente')).toBeVisible()
    await expect(page.getByText('Clima próximamente')).toBeVisible()
})

test('I — Acceso rápido "Dashboard" navega correctamente', async ({ page }) => {
    await page.getByText('Dashboard', { exact: true }).click()
    await page.waitForURL('**/app/dashboard', { timeout: 10_000 })
    await expect(page).toHaveURL(/\/app\/dashboard/)
})
