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
