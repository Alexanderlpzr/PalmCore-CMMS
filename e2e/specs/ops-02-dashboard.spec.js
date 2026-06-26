/**
 * Grupo 2 — Dashboard Inteligente (PX-1)
 *
 * Valida el centro operativo rediseñado: encabezado, indicadores,
 * actividad reciente, novedades y accesos rápidos.
 * No valida estilos/píxeles — valida comportamiento y contenido.
 */
import { execSync } from 'child_process'
import { test, expect } from '@playwright/test'
import { loginToApp } from '../helpers.js'

test.use({ storageState: { cookies: [], origins: [] } })

test.beforeEach(() => {
    execSync('php artisan cache:clear', { stdio: 'ignore' })
})

const STAT_LABELS = [
    'OTs activas',
    'Solicitudes pendientes',
    'Alertas críticas',
    'Equipos en mantenimiento',
]

const QUICK_ACTIONS = [
    'Crear OT',
    'Registrar solicitud',
    'Escanear QR',
    'Nuevo equipo',
]

test.describe('Grupo 2 — Dashboard Inteligente (PX-1)', () => {
    test('2-1 carga correctamente y muestra encabezado', async ({ page }) => {
        await loginToApp(page)

        await expect(page).toHaveURL(/\/app\/dashboard/)
        await expect(page.getByRole('heading', { name: /Bienvenido/i })).toBeVisible()

        // Reloj visible (formato HH:MM)
        await expect(page.locator('p.text-2xl.font-bold.tabular-nums')).toBeVisible()
        await expect(page.locator('p.text-2xl.font-bold.tabular-nums')).toHaveText(/^\d{1,2}:\d{2}$/)
    })

    test('2-2 indicadores principales visibles con valores numéricos', async ({ page }) => {
        await loginToApp(page)

        for (const label of STAT_LABELS) {
            await expect(page.getByText(label).first()).toBeVisible()
        }

        // Stat values load (skeletons resolve to numbers)
        const statValues = page.locator('p.text-2xl.font-bold.tabular-nums')
        await expect(statValues.first()).toBeVisible()
        await expect(statValues.first()).toHaveText(/^\d+$/)
    })

    test('2-3 sección actividad reciente visible', async ({ page }) => {
        await loginToApp(page)

        await expect(page.getByRole('heading', { name: 'Actividad reciente' })).toBeVisible()
    })

    test('2-4 sección novedades visible', async ({ page }) => {
        await loginToApp(page)

        await expect(page.getByRole('heading', { name: 'Novedades' })).toBeVisible()
    })

    test('2-5 accesos rápidos visibles', async ({ page }) => {
        await loginToApp(page)

        for (const label of QUICK_ACTIONS) {
            await expect(page.getByText(label).first()).toBeVisible()
        }
    })

    test('2-6 datos provienen del backend real (endpoint consolidado)', async ({ page }) => {
        const summaryPromise = page.waitForResponse(
            (r) => r.url().includes('/api/v1/dashboard/summary') && r.request().method() === 'GET',
        )
        const activityPromise = page.waitForResponse(
            (r) => r.url().includes('/api/v1/dashboard/activity') && r.request().method() === 'GET',
        )

        await loginToApp(page)

        const [summary, activity] = await Promise.all([summaryPromise, activityPromise])
        expect(summary.status()).toBe(200)
        expect(activity.status()).toBe(200)

        const summaryBody = await summary.json()
        expect(typeof summaryBody.activeWOs).toBe('number')
        expect(typeof summaryBody.pendingMRs).toBe('number')
    })

    test('2-7 los indicadores navegan a sus secciones', async ({ page }) => {
        await loginToApp(page)

        // Click the "Equipos en mantenimiento" stat card
        await page.getByRole('link').filter({ hasText: 'Equipos en mantenimiento' }).click()
        await expect(page).toHaveURL(/\/app\/equipos/)

        await page.goBack()
        await expect(page).toHaveURL(/\/app\/dashboard/)

        // Click "Alertas críticas"
        await page.getByRole('link').filter({ hasText: 'Alertas críticas' }).click()
        await expect(page).toHaveURL(/\/app\/alertas/)
    })

    test('2-8 no produce errores de consola', async ({ page }) => {
        const errors = []
        page.on('pageerror', (e) => errors.push(`pageerror: ${e.message}`))
        page.on('console', (msg) => {
            if (msg.type() !== 'error') { return }
            const text = msg.text()
            if (/favicon|manifest|Failed to load resource|net::ERR/i.test(text)) { return }
            if (/Content Security Policy/i.test(text)) { return }
            errors.push(`console: ${text}`)
        })

        await loginToApp(page)
        await expect(page.getByRole('heading', { name: /Bienvenido/i })).toBeVisible()
        await page.waitForTimeout(1500)

        expect(errors).toEqual([])
    })

    test('2-9 no muestra textos de desarrollo ni placeholders', async ({ page }) => {
        await loginToApp(page)
        await expect(page.getByRole('heading', { name: /Bienvenido/i })).toBeVisible()

        await expect(
            page.getByText(/próximamente|en desarrollo|placeholder|lorem ipsum|siguiente sprint|TODO\b/i),
        ).toHaveCount(0)
    })

    test('2-10 acceso rápido Crear OT navega correctamente', async ({ page }) => {
        await loginToApp(page)

        await page.getByText('Crear OT').click()
        await expect(page).toHaveURL(/\/app\/ordenes/)
    })
})
