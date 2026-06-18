/**
 * Grupo 10 — Alertas
 *
 * Valida el Alert Center implementado en Sprint 11.
 *
 * Fixtures (todos definidos en E2EDataSeeder, siempre resetados a status=open):
 *   10A/10B  [E2E] Alerta crítica de prueba     — critical, se usa solo para lectura
 *   10C      [E2E] Alerta para resolver         — warning, se resuelve en el test
 *   10D      [E2E] Alerta para descartar        — warning, se descarta en el test
 *   10E      [E2E] Alerta para persistencia     — warning, se resuelve y verifica persistencia
 *
 * Comportamiento validado:
 *   - Visualización de alertas abiertas con severidad y acciones correctas
 *   - Alerta crítica: botón "Descartar" ausente (solo "Resolver")
 *   - Resolver alerta: API PATCH 200, desaparece de "Abiertas"
 *   - Descartar alerta: API PATCH 200, desaparece de "Abiertas"
 *   - Alerta resuelta aparece en pestaña "Cerradas" con badge "Resuelta"
 *   - Persistencia tras recarga de página (verificación server-side vía API)
 *   - Sin errores de consola ni page errors
 */
import { execSync } from 'child_process'
import { test, expect } from '@playwright/test'
import { loginToApp } from '../helpers.js'

function setupErrorListeners(page) {
    const errors = []
    page.on('pageerror', (e) => errors.push(`pageerror: ${e.message}`))
    page.on('console', (msg) => {
        if (msg.type() !== 'error') return
        const text = msg.text()
        if (/favicon|manifest|Failed to load resource|net::ERR/i.test(text)) return
        if (/Content Security Policy/i.test(text)) return
        errors.push(`console: ${text}`)
    })
    return errors
}

async function navToAlertas(page) {
    await page.getByRole('link', { name: 'Alertas', exact: true }).first().click()
    await page.waitForURL('**/app/alertas')
}

async function waitForAlertsLoad(page) {
    await page.waitForResponse(
        (r) => r.url().includes('/api/v1/alerts') && !r.url().includes('/count') && r.request().method() === 'GET',
        { timeout: 15_000 },
    )
    await page.waitForLoadState('networkidle', { timeout: 10_000 })
}

/** Returns the scoped locator for the alert card containing the given title. */
function alertCard(page, title) {
    return page.locator('.space-y-3 > div').filter({ hasText: title })
}

// ══════════════════════════════════════════════════════════════════════════════
// Grupo 10A — Visualización de alertas abiertas
// ══════════════════════════════════════════════════════════════════════════════

test.describe.serial('Grupo 10A — Visualización de alertas', () => {
    test.beforeEach(() => execSync('php artisan cache:clear', { stdio: 'ignore' }))

    test('lista alertas abiertas y muestra acciones correctas según severidad', async ({ page }) => {
        const errors = setupErrorListeners(page)

        await loginToApp(page)
        await navToAlertas(page)
        await waitForAlertsLoad(page)

        // La lista carga al menos una alerta E2E crítica (nunca modificada por otros tests del grupo)
        const criticalCard = alertCard(page, '[E2E] Alerta crítica de prueba')
        await expect(criticalCard).toBeVisible({ timeout: 5_000 })

        // El total de alertas se muestra en el header
        const totalText = page.locator('p.text-sm.text-gray-500').filter({ hasText: /alerta/ })
        await expect(totalText).toBeVisible()

        // Alerta crítica: botón "Resolver" visible, botón "Descartar" NO visible
        await expect(criticalCard.getByRole('button', { name: 'Resolver', exact: true })).toBeVisible()
        await expect(criticalCard.getByRole('button', { name: 'Descartar', exact: true })).not.toBeVisible()

        // Alerta warning: ambos botones visibles
        const warningCard = alertCard(page, '[E2E] Alerta para persistencia')
        await expect(warningCard).toBeVisible()
        await expect(warningCard.getByRole('button', { name: 'Resolver', exact: true })).toBeVisible()
        await expect(warningCard.getByRole('button', { name: 'Descartar', exact: true })).toBeVisible()

        await page.waitForTimeout(300)
        expect(errors).toEqual([])
    })
})

// ══════════════════════════════════════════════════════════════════════════════
// Grupo 10B — Alerta crítica: botón Descartar ausente
// ══════════════════════════════════════════════════════════════════════════════

test.describe.serial('Grupo 10B — Alerta crítica sin botón Descartar', () => {
    test.beforeEach(() => execSync('php artisan cache:clear', { stdio: 'ignore' }))

    test('alerta crítica tiene Resolver pero no Descartar en la UI', async ({ page }) => {
        const errors = setupErrorListeners(page)

        await loginToApp(page)
        await navToAlertas(page)
        await waitForAlertsLoad(page)

        const card = alertCard(page, '[E2E] Alerta crítica de prueba')
        await expect(card).toBeVisible({ timeout: 5_000 })

        // "Resolver" siempre presente en alertas abiertas
        await expect(card.getByRole('button', { name: 'Resolver', exact: true })).toBeVisible()

        // "Descartar" ausente cuando severity === 'critical' (v-if en el template)
        await expect(card.getByRole('button', { name: 'Descartar', exact: true })).not.toBeVisible()

        await page.waitForTimeout(300)
        expect(errors).toEqual([])
    })
})

// ══════════════════════════════════════════════════════════════════════════════
// Grupo 10C — Resolver alerta
// ══════════════════════════════════════════════════════════════════════════════

test.describe.serial('Grupo 10C — Resolver alerta', () => {
    test.beforeEach(() => execSync('php artisan cache:clear', { stdio: 'ignore' }))

    test('resolver alerta: PATCH 200, desaparece de Abiertas', async ({ page }) => {
        const errors = setupErrorListeners(page)

        await loginToApp(page)
        await navToAlertas(page)
        await waitForAlertsLoad(page)

        const card = alertCard(page, '[E2E] Alerta para resolver')
        await expect(card).toBeVisible({ timeout: 5_000 })
        const resolveBtn = card.getByRole('button', { name: 'Resolver', exact: true })
        await expect(resolveBtn).toBeVisible()

        const [response] = await Promise.all([
            page.waitForResponse(
                (r) => r.url().includes('/api/v1/alerts/') && r.url().includes('/resolve') && r.request().method() === 'PATCH',
                { timeout: 15_000 },
            ),
            resolveBtn.click(),
        ])

        // API: status 200
        expect(response.status()).toBe(200)
        const body = await response.json()
        expect(body.status).toBe('resolved')

        // UI: la tarjeta desaparece de "Abiertas" (Vue filtra el array optimistamente)
        await expect(card).not.toBeVisible({ timeout: 5_000 })

        await page.waitForTimeout(300)
        expect(errors).toEqual([])
    })
})

// ══════════════════════════════════════════════════════════════════════════════
// Grupo 10D — Descartar alerta
// ══════════════════════════════════════════════════════════════════════════════

test.describe.serial('Grupo 10D — Descartar alerta', () => {
    test.beforeEach(() => execSync('php artisan cache:clear', { stdio: 'ignore' }))

    test('descartar alerta: PATCH 200, desaparece de Abiertas', async ({ page }) => {
        const errors = setupErrorListeners(page)

        await loginToApp(page)
        await navToAlertas(page)
        await waitForAlertsLoad(page)

        const card = alertCard(page, '[E2E] Alerta para descartar')
        await expect(card).toBeVisible({ timeout: 5_000 })
        const dismissBtn = card.getByRole('button', { name: 'Descartar', exact: true })
        await expect(dismissBtn).toBeVisible()

        const [response] = await Promise.all([
            page.waitForResponse(
                (r) => r.url().includes('/api/v1/alerts/') && r.url().includes('/dismiss') && r.request().method() === 'PATCH',
                { timeout: 15_000 },
            ),
            dismissBtn.click(),
        ])

        // API: status 200
        expect(response.status()).toBe(200)
        const body = await response.json()
        expect(body.status).toBe('dismissed')

        // UI: la tarjeta desaparece de "Abiertas"
        await expect(card).not.toBeVisible({ timeout: 5_000 })

        await page.waitForTimeout(300)
        expect(errors).toEqual([])
    })
})

// ══════════════════════════════════════════════════════════════════════════════
// Grupo 10E — Persistencia: alerta resuelta aparece en "Cerradas"
// ══════════════════════════════════════════════════════════════════════════════

test.describe.serial('Grupo 10E — Persistencia tras recargar', () => {
    test.beforeEach(() => execSync('php artisan cache:clear', { stdio: 'ignore' }))

    test('alerta resuelta aparece en Cerradas con badge "Resuelta"', async ({ page }) => {
        const errors = setupErrorListeners(page)

        await loginToApp(page)
        await navToAlertas(page)
        await waitForAlertsLoad(page)

        // Resolver la alerta de persistencia
        const card = alertCard(page, '[E2E] Alerta para persistencia')
        await expect(card).toBeVisible({ timeout: 5_000 })
        const resolveBtn = card.getByRole('button', { name: 'Resolver', exact: true })

        await Promise.all([
            page.waitForResponse(
                (r) => r.url().includes('/api/v1/alerts/') && r.url().includes('/resolve') && r.request().method() === 'PATCH',
                { timeout: 15_000 },
            ),
            resolveBtn.click(),
        ])

        // La alerta desaparece de "Abiertas"
        await expect(card).not.toBeVisible({ timeout: 5_000 })

        // Cambiar a pestaña "Cerradas" (status=resolved)
        const cerradasBtn = page.getByRole('button', { name: 'Cerradas', exact: true })
        await Promise.all([
            page.waitForResponse(
                (r) => r.url().includes('/api/v1/alerts') && r.url().includes('status=resolved') && r.request().method() === 'GET',
                { timeout: 10_000 },
            ),
            cerradasBtn.click(),
        ])

        // La alerta aparece en "Cerradas" con el badge "Resuelta"
        const resolvedCard = alertCard(page, '[E2E] Alerta para persistencia')
        await expect(resolvedCard).toBeVisible({ timeout: 5_000 })
        await expect(resolvedCard.getByText('Resuelta', { exact: true })).toBeVisible()

        await page.waitForTimeout(300)
        expect(errors).toEqual([])
    })

    test('persistencia tras recargar: alerta resuelta sigue en Cerradas', async ({ page }) => {
        const errors = setupErrorListeners(page)

        // Este test depende de que el anterior haya resuelto [E2E] Alerta para persistencia.
        // Hace una navegación fresca (loginToApp) y verifica desde la API.
        await loginToApp(page)
        await navToAlertas(page)

        // Ir directamente a "Cerradas" para verificar persistencia server-side
        const cerradasBtn = page.getByRole('button', { name: 'Cerradas', exact: true })
        await Promise.all([
            page.waitForResponse(
                (r) => r.url().includes('/api/v1/alerts') && r.url().includes('status=resolved') && r.request().method() === 'GET',
                { timeout: 15_000 },
            ),
            cerradasBtn.click(),
        ])

        await page.waitForLoadState('networkidle', { timeout: 10_000 })

        // La alerta sigue visible en "Cerradas" (persistida en DB)
        const resolvedCard = alertCard(page, '[E2E] Alerta para persistencia')
        await expect(resolvedCard).toBeVisible({ timeout: 5_000 })
        await expect(resolvedCard.getByText('Resuelta', { exact: true })).toBeVisible()

        await page.waitForTimeout(300)
        expect(errors).toEqual([])
    })
})
