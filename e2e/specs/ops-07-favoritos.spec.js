/**
 * Grupo 7 — Favoritos
 *
 * Valida la funcionalidad de favoritos introducida en P6.
 * Los favoritos son client-side: persisten en localStorage bajo
 * `fronda.favorites.<type>` (no hay endpoints de backend).
 *
 * Validaciones:
 *   1.  Agregar favorito                   → 7A test 1
 *   2.  Quitar favorito                    → 7A test 2
 *   3.  Dashboard refleja el cambio        → 7A test 3
 *   4.  Persistencia tras recargar         → 7A test 4
 *   5.  Equipos favoritos                  → 7A tests 1–4
 *   6.  OT favoritas                       → 7B test 1
 *   7.  Repuestos favoritos                → 7C test 1
 *   8.  Preventivos favoritos              → 7D test 1
 *   9.  Estado vacío correcto              → 7E test 1
 *  10.  Sin errores de consola             → 7E test 2
 *  11.  Sin page errors                    → 7E test 2
 *
 * Datos sembrados por E2EDataSeeder:
 *   Equipment:  E2E-PRE-001 ("[E2E] Prensa Extractora Principal")
 *   WorkOrder:  E2E-WO-0001 ("[E2E] OT para pruebas de inventario", in_progress)
 *   SparePart:  E2E-SP-001  ("[E2E] Filtro Hidráulico")
 *   Plan:       E2E-PREV-001 ("[E2E] Plan Preventivo de Prueba", manual)
 *
 * Nota de aislamiento: el `storageState` de Playwright restaura localStorage
 * antes de cada test. Por eso cada test es self-contained (no encadena estado
 * entre tests). La "persistencia" (validación 4) se prueba dentro de la misma
 * sesión navegando lejos y volviendo al dashboard.
 */
import { execSync } from 'child_process'
import { test, expect } from '@playwright/test'
import { loginToApp, OPS_CREDENTIALS, appUrl } from '../helpers.js'

const LS_PREFIX = 'fronda.favorites'
const TYPES = ['equipment', 'workorders', 'spareparts', 'preventives']

/**
 * Log in after wiping all favorites from localStorage.
 * La limpieza ocurre entre el page.goto (login page cargada) y el submit del
 * formulario, por lo que el dashboard monta con localStorage limpio.
 */
async function loginFresh(page) {
    await page.goto(appUrl('login'))
    await page.evaluate(
        ([prefix, types]) => types.forEach((t) => localStorage.removeItem(`${prefix}.${t}`)),
        [LS_PREFIX, TYPES],
    )
    await page.locator('input[autocomplete="organization"]').fill(OPS_CREDENTIALS.tenant)
    await page.locator('input[type="email"]').fill(OPS_CREDENTIALS.email)
    await page.locator('input[type="password"]').fill(OPS_CREDENTIALS.password)
    await page.getByRole('button', { name: /Ingresar/i }).click()
    await page.waitForURL('**/app/dashboard', { timeout: 20_000 })
}

/** Read a favorites array from localStorage. */
async function readFavs(page, type) {
    return page.evaluate(
        ([prefix, t]) => {
            try {
                return JSON.parse(localStorage.getItem(`${prefix}.${t}`) ?? '[]')
            } catch {
                return []
            }
        },
        [LS_PREFIX, type],
    )
}

/** Navigate to a section via the sidebar link. */
async function navTo(page, label) {
    await page.getByRole('link', { name: label, exact: true }).first().click()
}

/** Navigate to Dashboard and wait for FavoritesPanel API resolution. */
async function navToDashboard(page) {
    await navTo(page, 'Dashboard')
    await page.waitForURL('**/app/dashboard')
    await page.waitForLoadState('networkidle', { timeout: 15_000 })
}

// ══════════════════════════════════════════════════════════════════════════════
// Grupo 7A — Equipos favoritos (validaciones 1, 2, 3, 4, 5)
// ══════════════════════════════════════════════════════════════════════════════

test.describe.serial('Grupo 7A — Equipos favoritos', () => {
    test.beforeEach(() => {
        execSync('php artisan cache:clear', { stdio: 'ignore' })
    })

    // ── Validaciones 1+5: agregar favorito ───────────────────────────────────
    test('1+5. agregar favorito de equipo: localStorage actualizado, estrella activa', async ({ page }) => {
        await loginFresh(page)

        await navTo(page, 'Equipos')
        await page.waitForURL('**/app/equipos')
        await page.locator('input[placeholder*="Buscar"]').fill('E2E-PRE-001')
        await page.waitForResponse(
            (r) =>
                r.url().includes('/api/v1/equipment') &&
                r.url().includes('search=') &&
                r.request().method() === 'GET',
            { timeout: 10_000 },
        )

        // La estrella debe estar inactiva antes de hacer toggle
        const star = page.getByTitle('Agregar a favoritos').first()
        await expect(star).toHaveAttribute('aria-pressed', 'false')

        await star.click()

        // Tras el click la estrella cambia a activa
        await expect(page.getByTitle('Quitar de favoritos').first()).toHaveAttribute(
            'aria-pressed',
            'true',
        )

        // localStorage debe contener un UUID
        const favs = await readFavs(page, 'equipment')
        expect(favs).toHaveLength(1)
        expect(favs[0]).toMatch(/^[0-9a-f-]{36}$/)
    })

    // ── Validación 2: quitar favorito (self-contained) ───────────────────────
    test('2. quitar favorito de equipo: localStorage limpio, estrella inactiva', async ({ page }) => {
        // Este test es self-contained: agrega primero para poder luego quitar.
        await loginFresh(page)

        await navTo(page, 'Equipos')
        await page.waitForURL('**/app/equipos')
        await page.locator('input[placeholder*="Buscar"]').fill('E2E-PRE-001')
        await page.waitForResponse(
            (r) =>
                r.url().includes('/api/v1/equipment') &&
                r.url().includes('search=') &&
                r.request().method() === 'GET',
            { timeout: 10_000 },
        )

        // Primero agregar
        await page.getByTitle('Agregar a favoritos').first().click()
        await expect(page.getByTitle('Quitar de favoritos').first()).toHaveAttribute('aria-pressed', 'true')
        expect(await readFavs(page, 'equipment')).toHaveLength(1)

        // Ahora quitar
        await page.getByTitle('Quitar de favoritos').first().click()
        await expect(page.getByTitle('Agregar a favoritos').first()).toHaveAttribute(
            'aria-pressed',
            'false',
        )

        // localStorage debe quedar vacío
        const favs = await readFavs(page, 'equipment')
        expect(favs).toHaveLength(0)
    })

    // ── Validaciones 3+5: dashboard refleja el cambio ────────────────────────
    test('3+5. dashboard refleja equipo favorito: nombre y código visibles en el panel', async ({ page }) => {
        await loginFresh(page)

        // Agregar favorito
        await navTo(page, 'Equipos')
        await page.waitForURL('**/app/equipos')
        await page.locator('input[placeholder*="Buscar"]').fill('E2E-PRE-001')
        await page.waitForResponse(
            (r) =>
                r.url().includes('/api/v1/equipment') &&
                r.url().includes('search=') &&
                r.request().method() === 'GET',
            { timeout: 10_000 },
        )
        await page.getByTitle('Agregar a favoritos').first().click()
        await expect(page.getByTitle('Quitar de favoritos').first()).toBeAttached()

        // Navegar al dashboard y esperar resolución de API desde FavoritesPanel
        await navToDashboard(page)

        // El nombre del equipo aparece en el panel (resuelto desde /api/v1/equipment/{id})
        await expect(
            page.getByText('[E2E] Prensa Extractora Principal'),
        ).toBeVisible({ timeout: 5_000 })

        // El código aparece como subtítulo (solo en FavoritesPanel, no en sidebar)
        await expect(page.getByText('E2E-PRE-001').first()).toBeVisible({ timeout: 5_000 })
    })

    // ── Validación 4: persistencia (misma sesión, navegación SPA) ────────────
    test('4. persistencia: el favorito sobrevive navegar lejos y volver al dashboard', async ({ page }) => {
        await loginFresh(page)

        // Agregar favorito
        await navTo(page, 'Equipos')
        await page.waitForURL('**/app/equipos')
        await page.locator('input[placeholder*="Buscar"]').fill('E2E-PRE-001')
        await page.waitForResponse(
            (r) =>
                r.url().includes('/api/v1/equipment') &&
                r.url().includes('search=') &&
                r.request().method() === 'GET',
            { timeout: 10_000 },
        )
        await page.getByTitle('Agregar a favoritos').first().click()
        await expect(page.getByTitle('Quitar de favoritos').first()).toBeAttached()

        // Navegar al dashboard → verificar que aparece
        await navToDashboard(page)
        await expect(
            page.getByText('[E2E] Prensa Extractora Principal'),
        ).toBeVisible({ timeout: 5_000 })

        // Navegar a otra sección (Solicitudes) y volver al dashboard
        await navTo(page, 'Solicitudes')
        await page.waitForURL('**/app/solicitudes')

        await navToDashboard(page)

        // El favorito persiste tras la navegación SPA (localStorage + reactive store)
        await expect(
            page.getByText('[E2E] Prensa Extractora Principal'),
        ).toBeVisible({ timeout: 5_000 })
    })
})

// ══════════════════════════════════════════════════════════════════════════════
// Grupo 7B — OT favoritas (validación 6)
// ══════════════════════════════════════════════════════════════════════════════

test.describe.serial('Grupo 7B — OT favoritas', () => {
    test.beforeEach(() => {
        execSync('php artisan cache:clear', { stdio: 'ignore' })
    })

    test('6. OT favorita: nombre y número de OT visibles en el panel Favoritos', async ({ page }) => {
        await loginFresh(page)

        await navTo(page, 'Órdenes de trabajo')
        await page.waitForURL('**/app/ordenes')

        // E2E-WO-0001 (in_progress) está en el filtro "Activas" por defecto
        await page.locator('input[placeholder*="Buscar"]').fill('E2E-WO-0001')
        await page.waitForResponse(
            (r) =>
                r.url().includes('/api/v1/work-orders') &&
                r.url().includes('search=') &&
                r.request().method() === 'GET',
            { timeout: 10_000 },
        )

        await page.getByTitle('Agregar a favoritos').first().click()
        await expect(page.getByTitle('Quitar de favoritos').first()).toHaveAttribute('aria-pressed', 'true')

        const favs = await readFavs(page, 'workorders')
        expect(favs).toHaveLength(1)

        // Dashboard muestra la OT resuelta desde la API
        await navToDashboard(page)

        await expect(
            page.getByText('[E2E] OT para pruebas de inventario'),
        ).toBeVisible({ timeout: 5_000 })

        // El número de OT aparece como subtítulo en FavoritesPanel
        await expect(page.getByText('E2E-WO-0001').first()).toBeVisible({ timeout: 5_000 })
    })
})

// ══════════════════════════════════════════════════════════════════════════════
// Grupo 7C — Repuestos favoritos (validación 7)
// ══════════════════════════════════════════════════════════════════════════════

test.describe.serial('Grupo 7C — Repuestos favoritos', () => {
    test.beforeEach(() => {
        execSync('php artisan cache:clear', { stdio: 'ignore' })
    })

    test('7. repuesto favorito: nombre y código del repuesto visibles en el panel', async ({ page }) => {
        await loginFresh(page)

        await navTo(page, 'Repuestos')
        await page.waitForURL('**/app/repuestos')

        await page.locator('input[placeholder*="Buscar"]').fill('E2E')
        await page.waitForResponse(
            (r) =>
                r.url().includes('/api/v1/inventory/spare-parts') &&
                r.url().includes('search=') &&
                r.request().method() === 'GET',
            { timeout: 10_000 },
        )

        await page.getByTitle('Agregar a favoritos').first().click()
        await expect(page.getByTitle('Quitar de favoritos').first()).toHaveAttribute('aria-pressed', 'true')

        const favs = await readFavs(page, 'spareparts')
        expect(favs).toHaveLength(1)

        // Dashboard muestra el repuesto resuelto desde la API
        await navToDashboard(page)

        await expect(page.getByText('[E2E] Filtro Hidráulico')).toBeVisible({ timeout: 5_000 })
        await expect(page.getByText('E2E-SP-001').first()).toBeVisible({ timeout: 5_000 })
    })
})

// ══════════════════════════════════════════════════════════════════════════════
// Grupo 7D — Preventivos favoritos (validación 8)
// ══════════════════════════════════════════════════════════════════════════════

test.describe.serial('Grupo 7D — Preventivos favoritos', () => {
    test.beforeEach(() => {
        execSync('php artisan cache:clear', { stdio: 'ignore' })
    })

    test('8. preventivo favorito: nombre y número del plan visibles en el panel', async ({ page }) => {
        await loginFresh(page)

        await navTo(page, 'Preventivos')
        await page.waitForURL('**/app/preventivos')

        await page.waitForResponse(
            (r) =>
                r.url().includes('/api/v1/maintenance-plans') &&
                r.request().method() === 'GET',
            { timeout: 10_000 },
        )

        // Localizar la estrella del plan E2E-PREV-001 por su número en el header row del card
        const planHeaderRow = page.locator('span.font-mono.text-xs', { hasText: 'E2E-PREV-001' }).locator('..')
        await planHeaderRow.getByTitle('Agregar a favoritos').click()
        await expect(planHeaderRow.getByTitle('Quitar de favoritos')).toHaveAttribute('aria-pressed', 'true')

        const favs = await readFavs(page, 'preventives')
        expect(favs).toHaveLength(1)

        // Dashboard muestra el plan resuelto desde la API
        await navToDashboard(page)

        await expect(page.getByText('[E2E] Plan Preventivo de Prueba')).toBeVisible({ timeout: 5_000 })
        await expect(page.getByText('E2E-PREV-001').first()).toBeVisible({ timeout: 5_000 })
    })
})

// ══════════════════════════════════════════════════════════════════════════════
// Grupo 7E — Estado vacío y errores (validaciones 9, 10, 11)
// ══════════════════════════════════════════════════════════════════════════════

test.describe.serial('Grupo 7E — Estado vacío y errores', () => {
    test.beforeEach(() => {
        execSync('php artisan cache:clear', { stdio: 'ignore' })
    })

    // ── Validación 9: estado vacío ────────────────────────────────────────────
    test('9. estado vacío: texto de ayuda visible cuando no hay favoritos', async ({ page }) => {
        await loginFresh(page)

        // El dashboard se carga sin favoritos
        await page.waitForURL('**/app/dashboard')
        await page.waitForLoadState('networkidle', { timeout: 10_000 })

        await expect(
            page.getByText('No hay favoritos todavía.'),
        ).toBeVisible({ timeout: 5_000 })

        await expect(
            page.getByText(/Marca equipos, OT, repuestos o preventivos/),
        ).toBeVisible({ timeout: 5_000 })
    })

    // ── Validaciones 10+11: sin errores ──────────────────────────────────────
    test('10+11. sin errores de consola ni page errors durante el ciclo de favoritos', async ({ page }) => {
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
        await page.waitForURL('**/app/dashboard')

        // Agregar favorito de equipo
        await navTo(page, 'Equipos')
        await page.waitForURL('**/app/equipos')
        await page.locator('input[placeholder*="Buscar"]').fill('E2E-PRE-001')
        await page.waitForResponse(
            (r) =>
                r.url().includes('/api/v1/equipment') &&
                r.url().includes('search=') &&
                r.request().method() === 'GET',
            { timeout: 10_000 },
        )
        await page.getByTitle('Agregar a favoritos').first().click()
        await expect(page.getByTitle('Quitar de favoritos').first()).toBeAttached()

        // Navegar al dashboard — FavoritesPanel resuelve el equipo desde API
        await navToDashboard(page)
        await expect(
            page.getByText('[E2E] Prensa Extractora Principal'),
        ).toBeVisible({ timeout: 5_000 })

        // Quitar el favorito (volver a Equipos)
        await navTo(page, 'Equipos')
        await page.waitForURL('**/app/equipos')
        await page.locator('input[placeholder*="Buscar"]').fill('E2E-PRE-001')
        await page.waitForResponse(
            (r) =>
                r.url().includes('/api/v1/equipment') &&
                r.url().includes('search=') &&
                r.request().method() === 'GET',
            { timeout: 10_000 },
        )
        await page.getByTitle('Quitar de favoritos').first().click()
        await expect(page.getByTitle('Agregar a favoritos').first()).toBeAttached()

        // Dashboard muestra estado vacío
        await navToDashboard(page)
        await expect(
            page.getByText('No hay favoritos todavía.'),
        ).toBeVisible({ timeout: 5_000 })

        await page.waitForTimeout(400)
        expect(errors).toEqual([])
    })
})
