export const TENANT = 'el-pajuil'
export const TENANT_B = 'e2e-tenant-b'
export const BASE = 'http://localhost:8000'

/** Filament resource paths (relative to /admin/{tenant}/) */
export const PATHS = {
    maintenanceRequests: 'maintenance/maintenance-request/maintenance-requests',
    workOrders: 'maintenance/work-order/work-orders',
    spareParts: 'inventory/spare-part/spare-parts',
    warehouses: 'inventory/warehouse/warehouses',
    webhooks: 'integrations/webhooks/webhook-subscriptions',
    equipment: 'equipment',
    alerts: 'alerts',
    dashboard: 'dashboard',
}

/** Build a Filament admin URL for the given tenant and resource path. */
export function adminUrl(path = '') {
    return `${BASE}/admin/${TENANT}${path ? '/' + path.replace(/^\//, '') : ''}`
}

/** Build a URL for a given tenant slug and resource path. */
export function tenantUrl(slug, path = '') {
    return `${BASE}/admin/${slug}${path ? '/' + path.replace(/^\//, '') : ''}`
}

// ── Ops SPA (/app) — token-auth single-page app ──────────────────────────────

export const APP_BASE = `${BASE}/app`

/** Build an Ops SPA URL (e.g. appUrl('dashboard') → /app/dashboard). */
export function appUrl(path = '') {
    return `${APP_BASE}${path ? '/' + path.replace(/^\//, '') : ''}`
}

export const OPS_CREDENTIALS = {
    tenant: TENANT,
    email: 'admin@elpajuil.demo',
    password: 'password',
}

/**
 * Log in to the Ops SPA through its real login form and wait for the dashboard.
 * The SPA uses token auth (sets an HttpOnly refresh cookie), independent of the
 * Filament admin session.
 */
export async function loginToApp(page, creds = OPS_CREDENTIALS) {
    await page.goto(appUrl('login'))
    await page.locator('input[autocomplete="organization"]').fill(creds.tenant)
    await page.locator('input[type="email"]').fill(creds.email)
    await page.locator('input[type="password"]').fill(creds.password)
    await page.getByRole('button', { name: /Ingresar/i }).click()
    await page.waitForURL('**/app/dashboard', { timeout: 20_000 })
}

/**
 * Confirm a Filament modal dialog by clicking the primary action button
 * (not Cancel/Cerrar).
 */
export async function confirmModal(page) {
    const modal = page.locator('[role="dialog"]')
    await modal.waitFor({ state: 'visible' })
    await modal.getByRole('button').filter({ hasNotText: /cancelar|cerrar/i }).last().click()
    await modal.waitFor({ state: 'hidden', timeout: 10_000 })
}
