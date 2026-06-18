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
 * Confirm a Filament modal dialog by clicking the primary action button.
 *
 * Waits for the "Confirmar" button to become visible, then clicks it. This avoids
 * the ambiguity of selecting a [role="dialog"] element when Filament pre-renders
 * hidden dialog wrappers for every page action (CSS [role="dialog"]:visible does not
 * match HTML <dialog> elements, which carry an implicit ARIA role rather than an
 * explicit attribute).
 */
export async function confirmModal(page) {
    const confirmBtn = page.getByRole('button', { name: 'Confirmar' })
    await confirmBtn.waitFor({ state: 'visible', timeout: 20_000 })
    await confirmBtn.click()
    await confirmBtn.waitFor({ state: 'hidden', timeout: 10_000 })
}
