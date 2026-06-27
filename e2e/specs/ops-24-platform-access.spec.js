/**
 * Grupo 24 — PLATFORM-1 Control de Acceso: Panel de Plataforma
 *
 * Validates strict separation between the platform panel (/platform)
 * and the tenant admin panel (/admin):
 *   A. Super Admin can access /platform (sees tenant list)
 *   B. Tenant admin is denied /platform (redirected to login)
 *   C. Unauthenticated user is denied /platform (redirected to login)
 *   D. Super Admin sees "→ Panel de Plataforma" link in /admin sidebar
 *   E. Platform panel shows Empresas, Suscripciones, Sistema nav groups
 *   F. Impersonation log resource is accessible from platform panel
 *
 * Uses E2EDataSeeder identities:
 *   - Super Admin: credentials set up in E2EDataSeeder (admin@example.com or similar)
 *   - Tenant Admin: a regular tenant user without is_super_admin
 *
 * Note: these tests use the Filament admin panels directly (not the Vue SPA).
 */
import { execSync } from 'child_process'
import { test, expect } from '@playwright/test'

// ── Helpers ──────────────────────────────────────────────────────────────────

async function loginAsAdmin(page, email, password) {
    await page.goto('/admin/login')
    await page.waitForLoadState('networkidle')
    await page.getByLabel('Email').fill(email)
    await page.getByLabel('Password').fill(password)
    await page.getByRole('button', { name: /entrar|log in|sign in/i }).click()
    await page.waitForURL(/\/admin/, { timeout: 15_000 })
}

async function getSuperAdminCredentials() {
    // Read from E2E seeder — super admin uses fixed credentials
    // The seeder creates a super admin with these credentials:
    return { email: 'superadmin@e2e.test', password: 'Admin123' }
}

async function getTenantAdminCredentials() {
    // Regular tenant user from the E2E seeder
    return { email: 'admin@e2e.test', password: 'Admin123' }
}

// ── Tests ─────────────────────────────────────────────────────────────────────

test.describe('Grupo 24 — Control de Acceso: Panel de Plataforma', () => {
    test.beforeEach(() => execSync('php artisan cache:clear', { stdio: 'ignore' }))

    test('24A: Super Admin puede acceder a /platform y ve lista de Empresas', async ({ page }) => {
        const { email, password } = await getSuperAdminCredentials()

        // Login via the platform panel's own login page
        await page.goto('/platform/login')
        await page.waitForLoadState('networkidle', { timeout: 15_000 })

        // Must show a login form
        await expect(page.getByLabel(/email/i)).toBeVisible({ timeout: 10_000 })
        await page.getByLabel(/email/i).fill(email)
        await page.getByLabel(/password|contraseña/i).fill(password)
        await page.getByRole('button', { name: /entrar|log in|sign in/i }).click()

        // Should land on platform home (dashboard or tenants)
        await page.waitForURL(/\/platform/, { timeout: 15_000 })
        await expect(page).toHaveURL(/\/platform/)

        // Navigate to tenants
        await page.goto('/platform/tenants')
        await page.waitForLoadState('networkidle', { timeout: 15_000 })
        await expect(page).toHaveURL(/\/platform\/tenants/)

        // The page title or heading should mention "Empresas" or "Tenants"
        await expect(
            page.getByRole('heading', { name: /empresas|tenants/i }).first()
        ).toBeVisible({ timeout: 10_000 })
    })

    test('24B: Usuario de tenant sin is_super_admin recibe 403 al acceder a /platform', async ({ page }) => {
        const { email, password } = await getTenantAdminCredentials()

        // Login to the admin panel first (as tenant user)
        await page.goto('/admin/login')
        await page.waitForLoadState('networkidle')
        await page.getByLabel(/email/i).fill(email)
        await page.getByLabel(/password|contraseña/i).fill(password)
        await page.getByRole('button', { name: /entrar|log in|sign in/i }).click()
        await page.waitForURL(/\/admin/, { timeout: 15_000 })

        // Now try to navigate to platform
        const response = await page.goto('/platform/tenants')
        // Either 403 status or redirected to /platform/login (because EnsureSuperAdmin → 403 → Filament catches it)
        const url = page.url()
        const status = response?.status() ?? 0

        const isBlocked =
            status === 403 ||
            url.includes('/platform/login') ||
            url.includes('/login') ||
            url.includes('/admin/login')

        expect(isBlocked).toBeTruthy()
    })

    test('24C: Usuario no autenticado es redirigido a login al acceder a /platform', async ({ page }) => {
        await page.goto('/platform/tenants')
        await page.waitForLoadState('networkidle', { timeout: 10_000 })

        // Should redirect to platform login (not admin login)
        await expect(page).toHaveURL(/\/platform\/login|\/login/)
    })

    test('24D: Super Admin ve el enlace "Panel de Plataforma" en el sidebar del panel admin', async ({ page }) => {
        const { email, password } = await getSuperAdminCredentials()

        await page.goto('/admin/login')
        await page.waitForLoadState('networkidle')
        await page.getByLabel(/email/i).fill(email)
        await page.getByLabel(/password|contraseña/i).fill(password)
        await page.getByRole('button', { name: /entrar|log in|sign in/i }).click()
        await page.waitForURL(/\/admin/, { timeout: 15_000 })

        // The platform link should appear somewhere in the sidebar
        await expect(
            page.getByRole('link', { name: /panel de plataforma/i })
                .or(page.getByText(/panel de plataforma/i).first())
        ).toBeVisible({ timeout: 10_000 })
    })

    test('24E: Panel de plataforma muestra grupos de navegación correctos', async ({ page }) => {
        const { email, password } = await getSuperAdminCredentials()

        await page.goto('/platform/login')
        await page.waitForLoadState('networkidle')
        await page.getByLabel(/email/i).fill(email)
        await page.getByLabel(/password|contraseña/i).fill(password)
        await page.getByRole('button', { name: /entrar|log in|sign in/i }).click()
        await page.waitForURL(/\/platform/, { timeout: 15_000 })

        // Navigate to tenants to get into a real platform page
        await page.goto('/platform/tenants')
        await page.waitForLoadState('networkidle', { timeout: 15_000 })

        // Check nav groups appear in the sidebar
        const sidebar = page.locator('nav, aside').first()

        for (const group of ['Empresas', 'Suscripciones', 'Sistema']) {
            await expect(
                sidebar.getByText(group, { exact: true }).or(page.getByText(group, { exact: true }).first())
            ).toBeVisible({ timeout: 10_000 })
        }
    })

    test('24F: ImpersonationLogResource es accesible desde el panel de plataforma', async ({ page }) => {
        const { email, password } = await getSuperAdminCredentials()

        await page.goto('/platform/login')
        await page.waitForLoadState('networkidle')
        await page.getByLabel(/email/i).fill(email)
        await page.getByLabel(/password|contraseña/i).fill(password)
        await page.getByRole('button', { name: /entrar|log in|sign in/i }).click()
        await page.waitForURL(/\/platform/, { timeout: 15_000 })

        // Navigate directly to impersonation logs
        const response = await page.goto('/platform/impersonation-logs')
        await page.waitForLoadState('networkidle', { timeout: 15_000 })

        // Should not redirect to login (super admin has access)
        await expect(page).not.toHaveURL(/\/login/)

        // Should render the resource page (either empty table or log entries)
        await expect(
            page.getByRole('table')
                .or(page.getByText(/impersonac/i).first())
                .or(page.getByText(/no records/i).first())
                .or(page.getByText(/sin registros/i).first())
        ).toBeVisible({ timeout: 10_000 })
    })
})
