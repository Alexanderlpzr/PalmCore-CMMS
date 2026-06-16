/**
 * Grupo 3 — Command Palette (Ops SPA, Ctrl/Cmd + K)
 *
 * Valida comportamiento (sin asserts visuales): apertura por atajo y botón,
 * cierre por Esc/click-fuera, búsqueda real contra /search, debounce, mínimo
 * de 2 caracteres, navegación con flechas, Enter, grupos con resultados,
 * estado vacío, cancelación de requests y ausencia de errores de consola.
 *
 * Datos sembrados (tenant el-pajuil): equipo E2E-PRE-001, repuesto E2E-SP-001
 * ([E2E] Filtro Hidráulico), OT E2E-WO-0001.
 */
import { execSync } from 'child_process'
import { test, expect } from '@playwright/test'
import { loginToApp } from '../helpers.js'

test.use({ storageState: { cookies: [], origins: [] } })

test.beforeEach(() => {
    execSync('php artisan cache:clear', { stdio: 'ignore' })
})

function dialogOf(page) {
    return page.getByRole('dialog', { name: 'Búsqueda global' })
}

async function openPaletteByShortcut(page) {
    await page.keyboard.press('Control+k')
    const dialog = dialogOf(page)
    await expect(dialog).toBeVisible()
    return dialog
}

test.describe('Grupo 3 — Command Palette (Ops SPA)', () => {
    test('abre con Ctrl + K', async ({ page }) => {
        await loginToApp(page)
        await openPaletteByShortcut(page)
        await expect(page.getByRole('combobox')).toBeFocused()
    })

    test('abre con el botón visible del sidebar', async ({ page }) => {
        await loginToApp(page)
        await page.getByRole('button', { name: /Buscar/ }).click()
        await expect(dialogOf(page)).toBeVisible()
    })

    test('cierra con Esc', async ({ page }) => {
        await loginToApp(page)
        const dialog = await openPaletteByShortcut(page)
        await page.keyboard.press('Escape')
        await expect(dialog).toBeHidden()
    })

    test('cierra con click fuera (backdrop)', async ({ page }) => {
        await loginToApp(page)
        const dialog = await openPaletteByShortcut(page)
        await page.mouse.click(20, 20) // esquina superior izquierda = backdrop
        await expect(dialog).toBeHidden()
    })

    test('busca de verdad usando /search', async ({ page }) => {
        await loginToApp(page)
        const dialog = await openPaletteByShortcut(page)

        const searchResp = page.waitForResponse(
            (r) => r.url().includes('/api/v1/search') && r.request().method() === 'GET',
        )
        await page.getByRole('combobox').fill('E2E')

        const resp = await searchResp
        expect(resp.status()).toBe(200)
        await expect(dialog.getByRole('option').first()).toBeVisible()
    })

    test('menos de 2 caracteres no dispara requests', async ({ page }) => {
        await loginToApp(page)
        await openPaletteByShortcut(page)

        const searchReqs = []
        page.on('request', (r) => { if (r.url().includes('/api/v1/search')) { searchReqs.push(r.url()) } })

        await page.getByRole('combobox').pressSequentially('E', { delay: 20 })
        await page.waitForTimeout(600)

        expect(searchReqs).toHaveLength(0)
        await expect(page.getByText('Empieza a escribir para buscar…')).toBeVisible()
    })

    test('aplica debounce (varias teclas → una sola request)', async ({ page }) => {
        await loginToApp(page)
        await openPaletteByShortcut(page)

        const searchReqs = []
        page.on('request', (r) => { if (r.url().includes('/api/v1/search')) { searchReqs.push(r.url()) } })

        await page.getByRole('combobox').pressSequentially('E2E', { delay: 20 })
        await page.waitForTimeout(700)

        expect(searchReqs).toHaveLength(1)
    })

    test('navega con flechas ↑ ↓', async ({ page }) => {
        await loginToApp(page)
        await openPaletteByShortcut(page)
        const input = page.getByRole('combobox')

        await input.fill('E2E')
        await expect(input).toHaveAttribute('aria-activedescendant', 'cmd-opt-0')

        await page.keyboard.press('ArrowDown')
        await expect(input).toHaveAttribute('aria-activedescendant', 'cmd-opt-1')

        await page.keyboard.press('ArrowUp')
        await expect(input).toHaveAttribute('aria-activedescendant', 'cmd-opt-0')
    })

    test('Enter abre el resultado activo y cierra el palette', async ({ page }) => {
        await loginToApp(page)
        const dialog = await openPaletteByShortcut(page)

        await page.getByRole('combobox').fill('E2E-PRE')
        await expect(dialog.getByRole('option').first()).toBeVisible()

        await page.keyboard.press('Enter')

        // El primer grupo es Equipos → navega al detalle del equipo y cierra.
        await expect(page).toHaveURL(/\/app\/equipos\//)
        await expect(dialog).toBeHidden()
    })

    test('solo muestra grupos con resultados', async ({ page }) => {
        await loginToApp(page)
        const dialog = await openPaletteByShortcut(page)

        // E2E-SP solo casa con el repuesto E2E-SP-001.
        await page.getByRole('combobox').fill('E2E-SP')
        await expect(dialog.getByText('Repuestos', { exact: true })).toBeVisible()
        await expect(dialog.getByText('Equipos', { exact: true })).toHaveCount(0)
        await expect(dialog.getByText('Órdenes de trabajo', { exact: true })).toHaveCount(0)
    })

    test('estado vacío: "No se encontraron resultados."', async ({ page }) => {
        await loginToApp(page)
        const dialog = await openPaletteByShortcut(page)

        await page.getByRole('combobox').fill('zzqqxnoresultados')
        await expect(dialog.getByText('No se encontraron resultados.')).toBeVisible()
    })

    test('cancelar requests anteriores no rompe la UI', async ({ page }) => {
        const pageErrors = []
        page.on('pageerror', (e) => pageErrors.push(e.message))

        await loginToApp(page)
        const dialog = await openPaletteByShortcut(page)
        const input = page.getByRole('combobox')

        // Cambios rápidos de query → la búsqueda nueva aborta la anterior.
        await input.fill('E2E')
        await input.fill('E2E-S')
        await input.fill('E2E-SP')

        // La UI sigue funcionando: muestra el repuesto y no hay excepciones.
        await expect(dialog.getByRole('option').first()).toBeVisible()
        await expect(dialog.getByText('Repuestos', { exact: true })).toBeVisible()
        expect(pageErrors).toEqual([])
    })

    test('no produce errores de consola durante el flujo', async ({ page }) => {
        const errors = []
        page.on('pageerror', (e) => errors.push(`pageerror: ${e.message}`))
        page.on('console', (msg) => {
            if (msg.type() !== 'error') { return }
            const text = msg.text()
            if (/favicon|manifest|Failed to load resource|net::ERR/i.test(text)) { return }
            if (/Content Security Policy/i.test(text)) { return } // Laravel Boost dev-only
            errors.push(`console: ${text}`)
        })

        await loginToApp(page)
        const dialog = await openPaletteByShortcut(page)
        await page.getByRole('combobox').fill('E2E')
        await expect(dialog.getByRole('option').first()).toBeVisible()
        await page.keyboard.press('Escape')
        await expect(dialog).toBeHidden()

        expect(errors).toEqual([])
    })
})
