/**
 * Grupo 8 — Saved Views
 *
 * Valida la funcionalidad de vistas guardadas introducida en P6.
 * Son client-side: `fronda.savedviews.<view>` en localStorage.
 * Los filtros activos viven en `fronda.<view>.<field>` (useViewPreferences).
 *
 * Validaciones:
 *   1. Guardar vista actual
 *   2. Asignar nombre
 *   3. Aplicar vista guardada
 *   4. El filtro se restaura correctamente
 *   5. Persistencia tras recargar (navegación SPA in-session)
 *   6. Eliminar vista
 *   7. Estado vacío correcto
 *   8. Sin errores de consola
 *   9. Sin page errors
 *
 * Vistas validadas: Equipos, Órdenes de trabajo, Solicitudes, Repuestos, Preventivos.
 *
 * State saved per view (from SavedViews `current` prop):
 *   equipment   → { filter, search }   filter default: 'all'
 *   workorders  → { filter, search }   filter default: 'planned,in_progress,on_hold'
 *   requests    → { filter }           filter default: 'submitted,under_review'
 *   spareparts  → { category, search } category default: ''
 *   preventives → { trigger, status }  trigger default: '', status default: 'active'
 */
import { execSync } from 'child_process'
import { test, expect } from '@playwright/test'
import { OPS_CREDENTIALS, appUrl } from '../helpers.js'

const SAVED_VIEWS_PREFIX = 'fronda.savedviews'

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Login after wiping ALL fronda.* localStorage (saved views + prefs + favorites).
 */
async function loginFresh(page) {
    await page.goto(appUrl('login'))
    await page.evaluate(() => {
        Object.keys(localStorage)
            .filter((k) => k.startsWith('fronda.'))
            .forEach((k) => localStorage.removeItem(k))
    })
    await page.locator('input[autocomplete="organization"]').fill(OPS_CREDENTIALS.tenant)
    await page.locator('input[type="email"]').fill(OPS_CREDENTIALS.email)
    await page.locator('input[type="password"]').fill(OPS_CREDENTIALS.password)
    await page.getByRole('button', { name: /Ingresar/i }).click()
    await page.waitForURL('**/app/dashboard', { timeout: 20_000 })
}

async function navTo(page, label) {
    await page.getByRole('link', { name: label, exact: true }).first().click()
}

/**
 * Opens the Vistas dropdown (dropdown must be closed before calling).
 */
async function openVistas(page) {
    await page.getByTitle('Vistas guardadas').click()
    await page.getByRole('button', { name: 'Guardar vista actual', exact: true }).waitFor({ timeout: 3_000 })
}

/**
 * Closes the Vistas dropdown.
 * Physical click is blocked by the z-30 overlay, so we dispatch the click event
 * directly on the toggle button to fire its `open = !open` handler.
 */
async function closeVistas(page) {
    await page.getByTitle('Vistas guardadas').dispatchEvent('click')
    await page.getByRole('button', { name: 'Guardar vista actual', exact: true }).waitFor({ state: 'detached', timeout: 3_000 })
}

/**
 * Saves the current view state with the given name.
 * Assumes the Vistas dropdown is already open.
 * Leaves the dropdown open (confirmSave does not call close()).
 */
async function saveCurrentView(page, viewName) {
    await page.getByRole('button', { name: 'Guardar vista actual', exact: true }).click()
    await page.locator('input[placeholder="Nombre de la vista"]').fill(viewName)
    await page.getByRole('button', { name: 'Guardar', exact: true }).click()
    // Wait for entry to appear in list (Vue re-renders after save)
    await page.locator('span.truncate', { hasText: viewName }).waitFor({ timeout: 3_000 })
}

/**
 * Applies a saved view by clicking its entry in the dropdown.
 * Assumes the dropdown is already open. Dropdown closes after apply.
 */
async function applyView(page, viewName) {
    await page.locator('span.truncate', { hasText: viewName }).click()
    // applyView() calls close() → dropdown disappears
    await page.getByRole('button', { name: 'Guardar vista actual', exact: true }).waitFor({ state: 'detached', timeout: 3_000 })
}

/**
 * Deletes the first (and assumed only) saved view.
 * Assumes the dropdown is already open.
 * The delete button has opacity-0 but is still clickable (no pointer-events: none).
 */
async function deleteFirstView(page) {
    await page.getByTitle('Eliminar vista').first().click()
}

/** Read saved views array from localStorage. */
async function readSavedViews(page, view) {
    return page.evaluate(
        ([prefix, v]) => {
            try { return JSON.parse(localStorage.getItem(`${prefix}.${v}`) ?? '[]') } catch { return [] }
        },
        [SAVED_VIEWS_PREFIX, view],
    )
}

/** Read a view preference field from localStorage. */
async function readPref(page, view, field) {
    return page.evaluate(
        ([v, f]) => {
            try {
                const raw = localStorage.getItem(`fronda.${v}.${f}`)
                return raw === null ? undefined : JSON.parse(raw)
            } catch { return undefined }
        },
        [view, field],
    )
}

// ══════════════════════════════════════════════════════════════════════════════
// Grupo 8A — Equipos (validaciones completas 1-7)
// ══════════════════════════════════════════════════════════════════════════════

test.describe.serial('Grupo 8A — Saved Views: Equipos', () => {
    test.beforeEach(() => execSync('php artisan cache:clear', { stdio: 'ignore' }))

    // Validaciones 1+2+3+4+7
    test('1+2+3+4+7: estado vacío → guardar → nombrar → aplicar → restaurar filtro', async ({ page }) => {
        await loginFresh(page)
        await navTo(page, 'Equipos')
        await page.waitForURL('**/app/equipos')

        // Validación 7: estado vacío antes de guardar nada
        await openVistas(page)
        await expect(page.getByText('No hay vistas guardadas.')).toBeVisible({ timeout: 3_000 })
        await closeVistas(page)

        // Cambiar filtro de "Todos" ('all') a "Activos" ('active')
        await page.getByRole('button', { name: 'Activos', exact: true }).click()
        await page.waitForResponse(
            (r) => r.url().includes('/api/v1/equipment') && r.url().includes('status=active') && r.request().method() === 'GET',
            { timeout: 10_000 },
        )
        expect(await readPref(page, 'equipment', 'filter')).toBe('active')

        // Validaciones 1+2: guardar vista con nombre
        await openVistas(page)
        await saveCurrentView(page, '[E2E] Equipos activos')

        const saved = await readSavedViews(page, 'equipment')
        expect(saved).toHaveLength(1)
        expect(saved[0].name).toBe('[E2E] Equipos activos')
        expect(saved[0].state.filter).toBe('active')
        expect(saved[0].id).toMatch(/^[0-9a-f-]{36}$/)

        // Resetear filtro a "Todos" ('all')
        await closeVistas(page)
        await page.getByRole('button', { name: 'Todos', exact: true }).click()
        await page.waitForResponse(
            (r) => r.url().includes('/api/v1/equipment') && !r.url().includes('status=') && r.request().method() === 'GET',
            { timeout: 10_000 },
        )
        expect(await readPref(page, 'equipment', 'filter')).toBe('all')

        // Validaciones 3+4: aplicar la vista guardada y verificar restauración
        await openVistas(page)
        await applyView(page, '[E2E] Equipos activos')

        await page.waitForResponse(
            (r) => r.url().includes('/api/v1/equipment') && r.url().includes('status=active') && r.request().method() === 'GET',
            { timeout: 10_000 },
        )
        expect(await readPref(page, 'equipment', 'filter')).toBe('active')
    })

    // Validación 5: persistencia in-session
    test('5: persistencia — la vista guardada sobrevive la navegación SPA', async ({ page }) => {
        await loginFresh(page)
        await navTo(page, 'Equipos')
        await page.waitForURL('**/app/equipos')

        // Cambiar filtro y guardar vista
        await page.getByRole('button', { name: 'Activos', exact: true }).click()
        await page.waitForResponse(
            (r) => r.url().includes('/api/v1/equipment') && r.url().includes('status=active') && r.request().method() === 'GET',
            { timeout: 10_000 },
        )
        await openVistas(page)
        await saveCurrentView(page, '[E2E] Equipos activos')
        await closeVistas(page)

        expect(await readSavedViews(page, 'equipment')).toHaveLength(1)

        // Navegar a Dashboard y volver
        await navTo(page, 'Dashboard')
        await page.waitForURL('**/app/dashboard')
        await navTo(page, 'Equipos')
        await page.waitForURL('**/app/equipos')
        // Esperar a que el componente monte y la API responda antes de interactuar
        await page.waitForLoadState('networkidle', { timeout: 10_000 })

        // La vista debe seguir en localStorage y en el dropdown
        const saved = await readSavedViews(page, 'equipment')
        expect(saved).toHaveLength(1)
        expect(saved[0].name).toBe('[E2E] Equipos activos')

        await openVistas(page)
        await expect(page.locator('span.truncate', { hasText: '[E2E] Equipos activos' })).toBeVisible({ timeout: 3_000 })
    })

    // Validaciones 6+7: eliminar y verificar estado vacío
    test('6+7: eliminar vista → estado vacío correcto en localStorage y UI', async ({ page }) => {
        await loginFresh(page)
        await navTo(page, 'Equipos')
        await page.waitForURL('**/app/equipos')

        // Guardar una vista para luego eliminarla
        await openVistas(page)
        await saveCurrentView(page, '[E2E] A eliminar')
        expect(await readSavedViews(page, 'equipment')).toHaveLength(1)

        // Eliminar
        await deleteFirstView(page)

        // localStorage vacío
        expect(await readSavedViews(page, 'equipment')).toHaveLength(0)

        // Estado vacío visible en el dropdown
        await expect(page.getByText('No hay vistas guardadas.')).toBeVisible({ timeout: 3_000 })
    })
})

// ══════════════════════════════════════════════════════════════════════════════
// Grupo 8B — Órdenes de trabajo (validaciones 1-4, 6-7)
// ══════════════════════════════════════════════════════════════════════════════

test.describe.serial('Grupo 8B — Saved Views: Órdenes de trabajo', () => {
    test.beforeEach(() => execSync('php artisan cache:clear', { stdio: 'ignore' }))

    test('1-4: guardar, nombrar, aplicar y restaurar filtro de OT', async ({ page }) => {
        await loginFresh(page)
        await navTo(page, 'Órdenes de trabajo')
        await page.waitForURL('**/app/ordenes')

        // Cambiar de "Activas" (planned,in_progress,on_hold) a "Completadas" (completed,...)
        await page.getByRole('button', { name: 'Completadas', exact: true }).click()
        await page.waitForResponse(
            (r) => r.url().includes('/api/v1/work-orders') && r.url().includes('status=completed') && r.request().method() === 'GET',
            { timeout: 10_000 },
        )
        expect(await readPref(page, 'workorders', 'filter')).toBe('completed,verified,closed')

        // Guardar
        await openVistas(page)
        await saveCurrentView(page, '[E2E] OT completadas')

        const saved = await readSavedViews(page, 'workorders')
        expect(saved).toHaveLength(1)
        expect(saved[0].state.filter).toBe('completed,verified,closed')

        // Resetear a "Activas"
        await closeVistas(page)
        await page.getByRole('button', { name: 'Activas', exact: true }).click()
        await page.waitForResponse(
            (r) => r.url().includes('/api/v1/work-orders') && r.url().includes('status=planned') && r.request().method() === 'GET',
            { timeout: 10_000 },
        )

        // Aplicar y verificar restauración
        await openVistas(page)
        await applyView(page, '[E2E] OT completadas')

        await page.waitForResponse(
            (r) => r.url().includes('/api/v1/work-orders') && r.url().includes('status=completed') && r.request().method() === 'GET',
            { timeout: 10_000 },
        )
        expect(await readPref(page, 'workorders', 'filter')).toBe('completed,verified,closed')
    })

    test('6+7: eliminar vista de OT → estado vacío', async ({ page }) => {
        await loginFresh(page)
        await navTo(page, 'Órdenes de trabajo')
        await page.waitForURL('**/app/ordenes')

        await openVistas(page)
        await saveCurrentView(page, '[E2E] A eliminar OT')
        expect(await readSavedViews(page, 'workorders')).toHaveLength(1)

        await deleteFirstView(page)

        expect(await readSavedViews(page, 'workorders')).toHaveLength(0)
        await expect(page.getByText('No hay vistas guardadas.')).toBeVisible({ timeout: 3_000 })
    })
})

// ══════════════════════════════════════════════════════════════════════════════
// Grupo 8C — Solicitudes (validaciones 1-4, 6-7)
// ══════════════════════════════════════════════════════════════════════════════

test.describe.serial('Grupo 8C — Saved Views: Solicitudes', () => {
    test.beforeEach(() => execSync('php artisan cache:clear', { stdio: 'ignore' }))

    test('1-4: guardar, nombrar, aplicar y restaurar filtro de Solicitudes', async ({ page }) => {
        await loginFresh(page)
        await navTo(page, 'Solicitudes')
        await page.waitForURL('**/app/solicitudes')

        // Cambiar de "Pendientes" (submitted,under_review) a "Aprobadas" (approved)
        await page.getByRole('button', { name: 'Aprobadas', exact: true }).click()
        await page.waitForResponse(
            (r) => r.url().includes('/api/v1/maintenance-requests') && r.url().includes('status=approved') && r.request().method() === 'GET',
            { timeout: 10_000 },
        )
        expect(await readPref(page, 'requests', 'filter')).toBe('approved')

        // Guardar
        await openVistas(page)
        await saveCurrentView(page, '[E2E] Solicitudes aprobadas')

        const saved = await readSavedViews(page, 'requests')
        expect(saved).toHaveLength(1)
        expect(saved[0].state.filter).toBe('approved')

        // Resetear a "Pendientes"
        await closeVistas(page)
        await page.getByRole('button', { name: 'Pendientes', exact: true }).click()
        await page.waitForResponse(
            (r) => r.url().includes('/api/v1/maintenance-requests') && r.url().includes('status=submitted') && r.request().method() === 'GET',
            { timeout: 10_000 },
        )

        // Aplicar y verificar restauración
        await openVistas(page)
        await applyView(page, '[E2E] Solicitudes aprobadas')

        await page.waitForResponse(
            (r) => r.url().includes('/api/v1/maintenance-requests') && r.url().includes('status=approved') && r.request().method() === 'GET',
            { timeout: 10_000 },
        )
        expect(await readPref(page, 'requests', 'filter')).toBe('approved')
    })

    test('6+7: eliminar vista de Solicitudes → estado vacío', async ({ page }) => {
        await loginFresh(page)
        await navTo(page, 'Solicitudes')
        await page.waitForURL('**/app/solicitudes')

        await openVistas(page)
        await saveCurrentView(page, '[E2E] A eliminar Sol')
        expect(await readSavedViews(page, 'requests')).toHaveLength(1)

        await deleteFirstView(page)

        expect(await readSavedViews(page, 'requests')).toHaveLength(0)
        await expect(page.getByText('No hay vistas guardadas.')).toBeVisible({ timeout: 3_000 })
    })
})

// ══════════════════════════════════════════════════════════════════════════════
// Grupo 8D — Repuestos (validaciones 1-4, 6-7)
// ══════════════════════════════════════════════════════════════════════════════

test.describe.serial('Grupo 8D — Saved Views: Repuestos', () => {
    test.beforeEach(() => execSync('php artisan cache:clear', { stdio: 'ignore' }))

    test('1-4: guardar, nombrar, aplicar y restaurar categoría de Repuestos', async ({ page }) => {
        await loginFresh(page)
        await navTo(page, 'Repuestos')
        await page.waitForURL('**/app/repuestos')

        // Cambiar de "Todos" (category: '') a "Mecánico" (category: 'mechanical')
        await page.getByRole('button', { name: 'Mecánico', exact: true }).click()
        await page.waitForResponse(
            (r) => r.url().includes('/api/v1/inventory/spare-parts') && r.url().includes('category_type=mechanical') && r.request().method() === 'GET',
            { timeout: 10_000 },
        )
        expect(await readPref(page, 'spareparts', 'category')).toBe('mechanical')

        // Guardar
        await openVistas(page)
        await saveCurrentView(page, '[E2E] Repuestos mecánicos')

        const saved = await readSavedViews(page, 'spareparts')
        expect(saved).toHaveLength(1)
        expect(saved[0].state.category).toBe('mechanical')

        // Resetear a "Todos"
        await closeVistas(page)
        await page.getByRole('button', { name: 'Todos', exact: true }).click()
        await page.waitForResponse(
            (r) => r.url().includes('/api/v1/inventory/spare-parts') && !r.url().includes('category_type=') && r.request().method() === 'GET',
            { timeout: 10_000 },
        )
        expect(await readPref(page, 'spareparts', 'category')).toBe('')

        // Aplicar y verificar restauración
        await openVistas(page)
        await applyView(page, '[E2E] Repuestos mecánicos')

        await page.waitForResponse(
            (r) => r.url().includes('/api/v1/inventory/spare-parts') && r.url().includes('category_type=mechanical') && r.request().method() === 'GET',
            { timeout: 10_000 },
        )
        expect(await readPref(page, 'spareparts', 'category')).toBe('mechanical')
    })

    test('6+7: eliminar vista de Repuestos → estado vacío', async ({ page }) => {
        await loginFresh(page)
        await navTo(page, 'Repuestos')
        await page.waitForURL('**/app/repuestos')

        await openVistas(page)
        await saveCurrentView(page, '[E2E] A eliminar Rep')
        expect(await readSavedViews(page, 'spareparts')).toHaveLength(1)

        await deleteFirstView(page)

        expect(await readSavedViews(page, 'spareparts')).toHaveLength(0)
        await expect(page.getByText('No hay vistas guardadas.')).toBeVisible({ timeout: 3_000 })
    })
})

// ══════════════════════════════════════════════════════════════════════════════
// Grupo 8E — Preventivos (validaciones 1-4, 6-7)
// ══════════════════════════════════════════════════════════════════════════════

test.describe.serial('Grupo 8E — Saved Views: Preventivos', () => {
    test.beforeEach(() => execSync('php artisan cache:clear', { stdio: 'ignore' }))

    test('1-4: guardar, nombrar, aplicar y restaurar trigger de Preventivos', async ({ page }) => {
        await loginFresh(page)
        await navTo(page, 'Preventivos')
        await page.waitForURL('**/app/preventivos')

        // Cambiar trigger de "Todos" (trigger: '') a "Manual" (trigger: 'manual')
        // Hay dos botones "Todos" en esta vista (trigger + active toggle): usamos first()
        await page.getByRole('button', { name: 'Manual', exact: true }).click()
        await page.waitForResponse(
            (r) => r.url().includes('/api/v1/maintenance-plans') && r.url().includes('trigger_source=manual') && r.request().method() === 'GET',
            { timeout: 10_000 },
        )
        expect(await readPref(page, 'preventives', 'trigger')).toBe('manual')

        // Guardar
        await openVistas(page)
        await saveCurrentView(page, '[E2E] Preventivos manuales')

        const saved = await readSavedViews(page, 'preventives')
        expect(saved).toHaveLength(1)
        expect(saved[0].state.trigger).toBe('manual')
        expect(saved[0].state.status).toBe('active')

        // Resetear trigger a "Todos" (el primero en DOM order = trigger filter)
        await closeVistas(page)
        await page.getByRole('button', { name: 'Todos', exact: true }).first().click()
        await page.waitForResponse(
            (r) => r.url().includes('/api/v1/maintenance-plans') && !r.url().includes('trigger_source=') && r.request().method() === 'GET',
            { timeout: 10_000 },
        )
        expect(await readPref(page, 'preventives', 'trigger')).toBe('')

        // Aplicar y verificar restauración
        await openVistas(page)
        await applyView(page, '[E2E] Preventivos manuales')

        await page.waitForResponse(
            (r) => r.url().includes('/api/v1/maintenance-plans') && r.url().includes('trigger_source=manual') && r.request().method() === 'GET',
            { timeout: 10_000 },
        )
        expect(await readPref(page, 'preventives', 'trigger')).toBe('manual')
        // status permanece 'active' (valor por defecto); Vue no dispara el watcher
        // si el valor asignado es igual al actual, por lo que la clave puede ser undefined.
        const status = await readPref(page, 'preventives', 'status')
        expect(status === 'active' || status === undefined).toBe(true)
    })

    test('6+7: eliminar vista de Preventivos → estado vacío', async ({ page }) => {
        await loginFresh(page)
        await navTo(page, 'Preventivos')
        await page.waitForURL('**/app/preventivos')

        await openVistas(page)
        await saveCurrentView(page, '[E2E] A eliminar Prev')
        expect(await readSavedViews(page, 'preventives')).toHaveLength(1)

        await deleteFirstView(page)

        expect(await readSavedViews(page, 'preventives')).toHaveLength(0)
        await expect(page.getByText('No hay vistas guardadas.')).toBeVisible({ timeout: 3_000 })
    })
})

// ══════════════════════════════════════════════════════════════════════════════
// Grupo 8F — Errores (validaciones 8+9)
// ══════════════════════════════════════════════════════════════════════════════

test.describe.serial('Grupo 8F — Errores de consola y page errors', () => {
    test.beforeEach(() => execSync('php artisan cache:clear', { stdio: 'ignore' }))

    test('8+9: sin errores durante el ciclo completo de saved views en Equipos', async ({ page }) => {
        const errors = []
        page.on('pageerror', (e) => errors.push(`pageerror: ${e.message}`))
        page.on('console', (msg) => {
            if (msg.type() !== 'error') { return }
            const text = msg.text()
            if (/favicon|manifest|Failed to load resource|net::ERR/i.test(text)) { return }
            if (/Content Security Policy/i.test(text)) { return }
            errors.push(`console: ${text}`)
        })

        await loginFresh(page)
        await navTo(page, 'Equipos')
        await page.waitForURL('**/app/equipos')

        // Cambiar filtro
        await page.getByRole('button', { name: 'Activos', exact: true }).click()
        await page.waitForResponse(
            (r) => r.url().includes('/api/v1/equipment') && r.url().includes('status=active') && r.request().method() === 'GET',
            { timeout: 10_000 },
        )

        // Guardar
        await openVistas(page)
        await saveCurrentView(page, '[E2E] Sin errores')

        // Aplicar
        await closeVistas(page)
        await page.getByRole('button', { name: 'Todos', exact: true }).click()
        await page.waitForResponse(
            (r) => r.url().includes('/api/v1/equipment') && !r.url().includes('status=') && r.request().method() === 'GET',
            { timeout: 10_000 },
        )
        await openVistas(page)
        await applyView(page, '[E2E] Sin errores')
        await page.waitForResponse(
            (r) => r.url().includes('/api/v1/equipment') && r.url().includes('status=active') && r.request().method() === 'GET',
            { timeout: 10_000 },
        )

        // Eliminar
        await openVistas(page)
        await deleteFirstView(page)
        await expect(page.getByText('No hay vistas guardadas.')).toBeVisible({ timeout: 3_000 })

        await page.waitForTimeout(400)
        expect(errors).toEqual([])
    })
})
