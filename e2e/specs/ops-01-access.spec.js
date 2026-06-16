/**
 * Grupo 1 — Acceso (Ops SPA, /app)
 *
 * Cubre el login token-based del Panel de Operaciones: entrada exitosa,
 * credenciales inválidas y logout. Estos tests arrancan SIN sesión (sobre-
 * escriben el storageState global de Filament) porque prueban el login mismo.
 *
 * El endpoint de tokens está rate-limited (5/min por IP) y el SPA gasta cupo
 * en cada arranque (restoreSession → /auth/refresh). Limpiamos el limitador
 * antes de cada test para que la ejecución rápida no choque con esa protección.
 */
import { execSync } from 'child_process'
import { test, expect } from '@playwright/test'
import { appUrl, OPS_CREDENTIALS, loginToApp } from '../helpers.js'

test.use({ storageState: { cookies: [], origins: [] } })

test.beforeEach(() => {
    execSync('php artisan cache:clear', { stdio: 'ignore' })
})

test.describe('Grupo 1 — Acceso (Ops SPA)', () => {
    test('login exitoso entra al dashboard', async ({ page }) => {
        await loginToApp(page)

        await expect(page).toHaveURL(/\/app\/dashboard/)
        await expect(page.getByRole('heading', { name: /Bienvenido/i })).toBeVisible()
    })

    test('credenciales inválidas muestran error y no redirigen', async ({ page }) => {
        await page.goto(appUrl('login'))
        await page.locator('input[autocomplete="organization"]').fill(OPS_CREDENTIALS.tenant)
        await page.locator('input[type="email"]').fill(OPS_CREDENTIALS.email)
        await page.locator('input[type="password"]').fill('contrasena-incorrecta')
        await page.getByRole('button', { name: /Ingresar/i }).click()

        // Error inline visible y sin redirección al dashboard.
        await expect(page.locator('form p.text-red-600')).toBeVisible()
        await expect(page).toHaveURL(/\/app\/login/)
    })

    test('logout vuelve a la pantalla de login', async ({ page }) => {
        await loginToApp(page)

        await page.getByRole('button', { name: 'Cerrar sesión' }).click()

        await page.waitForURL('**/app/login', { timeout: 20_000 })
        await expect(page.getByRole('button', { name: /Ingresar/i })).toBeVisible()
    })
})
