/**
 * Grupo 2 — Dashboard (Ops SPA, /app/dashboard)
 *
 * Valida comportamiento (no estilos/píxeles): carga, ausencia de errores de
 * consola, widgets visibles, datos del backend real, navegación de enlaces,
 * ausencia de textos de desarrollo y un estado vacío que no rompe.
 */
import { execSync } from 'child_process'
import { test, expect } from '@playwright/test'
import { loginToApp } from '../helpers.js'

test.use({ storageState: { cookies: [], origins: [] } })

// El endpoint de tokens es 5/min por IP y el SPA gasta cupo al arrancar; un
// reset por test evita que la ejecución rápida choque con esa protección.
test.beforeEach(() => {
    execSync('php artisan cache:clear', { stdio: 'ignore' })
})

const STAT_LABELS = ['OTs activas', 'Solicitudes pendientes', 'Alertas críticas', 'Equipos en mantenimiento']

test.describe('Grupo 2 — Dashboard (Ops SPA)', () => {
    test('carga correctamente y muestra los widgets principales', async ({ page }) => {
        await loginToApp(page)

        await expect(page).toHaveURL(/\/app\/dashboard/)
        await expect(page.getByRole('heading', { name: /Bienvenido/i })).toBeVisible()

        for (const label of STAT_LABELS) {
            await expect(page.getByText(label).first()).toBeVisible()
        }

        await expect(page.getByRole('heading', { name: /Mis órdenes de trabajo/i })).toBeVisible()
        await expect(page.getByRole('heading', { name: /^Favoritos$/i })).toBeVisible()
    })

    test('no produce errores de consola ni excepciones de página', async ({ page }) => {
        const errors = []
        page.on('pageerror', (e) => errors.push(`pageerror: ${e.message}`))
        page.on('console', (msg) => {
            if (msg.type() !== 'error') { return }
            const text = msg.text()
            // Ruido de entorno, no fallos de JS del producto:
            //  - carga de recursos (favicon/manifest/404);
            //  - violación de CSP del script inline `browser-logger-active` que
            //    inyecta Laravel Boost SOLO en desarrollo (no existe en producción).
            if (/favicon|manifest|Failed to load resource|net::ERR/i.test(text)) { return }
            if (/Content Security Policy/i.test(text)) { return }
            errors.push(`console: ${text}`)
        })

        await loginToApp(page)
        await expect(page.getByRole('heading', { name: /Bienvenido/i })).toBeVisible()
        await page.waitForTimeout(1200) // dejar asentar las llamadas async del dashboard

        expect(errors).toEqual([])
    })

    test('los datos provienen del backend real', async ({ page }) => {
        // Capturamos una de las llamadas reales del dashboard antes de que ocurra.
        const minePromise = page.waitForResponse(
            (r) => r.url().includes('/api/v1/work-orders/mine') && r.request().method() === 'GET',
        )

        await loginToApp(page)

        const mine = await minePromise
        expect(mine.status()).toBe(200)

        // Las stat cards muestran valores numéricos (ya no skeletons).
        const firstStatValue = page.locator('p.text-2xl.font-bold').first()
        await expect(firstStatValue).toBeVisible()
        await expect(firstStatValue).toHaveText(/^\d+$/)
    })

    test('los enlaces principales navegan', async ({ page }) => {
        await loginToApp(page)

        // "Ver todas" → lista de órdenes de trabajo.
        await page.getByRole('link', { name: /Ver todas/i }).click()
        await expect(page).toHaveURL(/\/app\/ordenes/)

        // Volver y navegar desde una stat card.
        await page.goBack()
        await expect(page).toHaveURL(/\/app\/dashboard/)
        await page.getByRole('link').filter({ hasText: 'Equipos en mantenimiento' }).click()
        await expect(page).toHaveURL(/\/app\/equipos/)
    })

    test('no muestra placeholders ni textos de desarrollo', async ({ page }) => {
        await loginToApp(page)
        await expect(page.getByRole('heading', { name: /Bienvenido/i })).toBeVisible()

        await expect(
            page.getByText(/próximamente|en desarrollo|placeholder|lorem ipsum|siguiente sprint|TODO\b/i),
        ).toHaveCount(0)
    })

    test('el estado vacío de Favoritos se renderiza sin romper', async ({ page }) => {
        // Contexto limpio → sin favoritos en localStorage → estado vacío determinista.
        await loginToApp(page)
        await expect(page.getByText('No hay favoritos todavía.')).toBeVisible()
    })
})
