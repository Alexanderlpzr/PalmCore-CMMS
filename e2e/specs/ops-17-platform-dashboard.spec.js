/**
 * Grupo 17 — ADMIN-3 Dashboard Global de Plataforma (Super Admin)
 *
 * Validates:
 *   A. A Super Admin sees the "Dashboard Global" nav, can navigate to it, and the
 *      platform metrics endpoints load (HTTP 200, cards rendered).
 *   B. A regular tenant user does NOT see the nav and is bounced if they try to
 *      navigate to /app/plataforma directly.
 *
 * Requires the Super Admin account to exist in the E2E database:
 *   superadmin@palmcore.app / Admin123 (SuperAdminSeeder). Super admins can access
 *   any tenant, so they log in through the el-pajuil slug.
 */
import { test, expect } from '@playwright/test'
import { loginToApp, appUrl, OPS_CREDENTIALS } from '../helpers.js'

const SUPER_ADMIN = {
    tenant: 'el-pajuil',
    email: 'superadmin@palmcore.app',
    password: 'Admin123',
}

test.describe('Grupo 17 — Dashboard de Plataforma', () => {
    test('A. super admin accede, navega y las métricas cargan', async ({ page }) => {
        await loginToApp(page, SUPER_ADMIN)

        const navLink = page.getByRole('link', { name: 'Dashboard Global' })
        await expect(navLink).toBeVisible()

        const [summaryResponse] = await Promise.all([
            page.waitForResponse(
                (r) => r.url().includes('/api/v1/platform/summary') && r.request().method() === 'GET',
                { timeout: 15_000 },
            ),
            navLink.click(),
        ])

        expect(summaryResponse.status()).toBe(200)

        await page.waitForURL('**/app/plataforma', { timeout: 15_000 })
        await expect(page.getByRole('heading', { name: 'Dashboard de Plataforma' })).toBeVisible()
        await expect(page.getByText('Empresas', { exact: true })).toBeVisible()
        await expect(page.getByText('Alertas Críticas', { exact: true })).toBeVisible()
        await expect(page.getByText('Uso de almacenamiento', { exact: true })).toBeVisible()
    })

    test('B. usuario normal no ve el nav y es rechazado', async ({ page }) => {
        await loginToApp(page, OPS_CREDENTIALS)

        await expect(page.getByRole('link', { name: 'Dashboard Global' })).toHaveCount(0)

        // Direct navigation is bounced back to the operations dashboard by the guard.
        await page.goto(appUrl('plataforma'))
        await page.waitForURL('**/app/dashboard', { timeout: 15_000 })
    })
})
