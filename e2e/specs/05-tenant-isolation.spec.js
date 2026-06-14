/**
 * Flow 5 — Multi-tenant isolation:
 * - Tenant A admin cannot access Tenant B resources
 * - Tenant B admin sees only their own data
 */
import { test, expect, chromium } from '@playwright/test'
import { adminUrl, tenantUrl, PATHS, TENANT, TENANT_B, BASE } from '../helpers.js'

test.describe('Tenant isolation', () => {
    test('01 — Tenant A admin is redirected away from Tenant B panel', async ({ page }) => {
        // Logged in as Tenant A (stored auth state)
        await page.goto(tenantUrl(TENANT_B, PATHS.maintenanceRequests))

        // After any redirect, should NOT land on Tenant B
        await page.waitForLoadState('networkidle')
        const url = page.url()
        expect(url).not.toContain(`/${TENANT_B}/maintenance`)
    })

    test('02 — Tenant B admin sees empty data (no Tenant A records)', async () => {
        const browser = await chromium.launch()
        const context = await browser.newContext()
        const page = await context.newPage()

        // Log in as Tenant B admin
        await page.goto(`${BASE}/admin/login`)
        await page.locator('input[name="email"], input[type="email"]').fill('admin@e2etenantb.test')
        await page.locator('input[name="password"], input[type="password"]').fill('password')
        await page.locator('button[type="submit"]').click()
        await page.waitForURL(`**/${TENANT_B}/**`, { timeout: 20_000 })

        expect(page.url()).toContain(`/${TENANT_B}/`)

        // Tenant B should NOT see Tenant A equipment
        await page.goto(tenantUrl(TENANT_B, PATHS.equipment))
        await page.waitForLoadState('networkidle')
        await expect(page.getByText('E2E-PRE-001')).not.toBeVisible({ timeout: 5_000 })

        // Tenant B should NOT see Tenant A maintenance requests
        await page.goto(tenantUrl(TENANT_B, PATHS.maintenanceRequests))
        await page.waitForLoadState('networkidle')
        await expect(page.getByText('[E2E] Falla en prensa extractora')).not.toBeVisible({ timeout: 5_000 })

        // Tenant B should NOT see Tenant A spare parts
        await page.goto(tenantUrl(TENANT_B, PATHS.spareParts))
        await page.waitForLoadState('networkidle')
        await expect(page.getByText('E2E-SP-001')).not.toBeVisible({ timeout: 5_000 })

        await browser.close()
    })

    test('03 — Tenant A admin sees Tenant A equipment', async ({ page }) => {
        await page.goto(adminUrl(PATHS.equipment))
        await expect(page.getByText('E2E-PRE-001')).toBeVisible({ timeout: 10_000 })
    })

    test('04 — Tenant A admin cannot directly navigate to Tenant B equipment', async ({ page }) => {
        await page.goto(tenantUrl(TENANT_B, PATHS.equipment))
        await page.waitForLoadState('networkidle')
        const url = page.url()
        // Must not be on Tenant B equipment — either redirected to login or Tenant A
        expect(url).not.toContain(`/${TENANT_B}/equipment`)
    })
})
