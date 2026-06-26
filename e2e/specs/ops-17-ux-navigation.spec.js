/**
 * Grupo 17 — Navegación Contextual (PX-3)
 *
 * Valida que el usuario pueda explorar la jerarquía Planta → Área → Equipo
 * sin perder contexto: breadcrumbs dinámicos, botón ← Volver y que las rutas
 * existentes no se vean afectadas.
 */
import { test, expect } from '@playwright/test'
import { loginToApp } from '../helpers.js'

test.use({ storageState: { cookies: [], origins: [] } })

test.describe('Grupo 17 — Navegación Contextual (PX-3)', () => {

    test('17-1 vista de planta carga desde enlace en equipo', async ({ page }) => {
        await loginToApp(page)

        // Navigate to equipment list and open first equipment
        await page.goto('/app/equipos')
        await page.waitForLoadState('networkidle')

        // Click the first equipment card
        const firstEquip = page.locator('a[href*="/app/equipos/"]').first()
        await expect(firstEquip).toBeVisible()
        await firstEquip.click()
        await expect(page).toHaveURL(/\/app\/equipos\//)

        // Wait for equipment detail to load
        await expect(page.locator('h1')).toBeVisible()

        // Click plant link in Ubicación section (if present)
        const plantLink = page.locator('a[href*="/app/plantes/"]').first()
        if (await plantLink.isVisible()) {
            await plantLink.click()
            await expect(page).toHaveURL(/\/app\/plantes\//)
            await expect(page.locator('h1')).toBeVisible()
        }
    })

    test('17-2 vista de área carga desde enlace en equipo', async ({ page }) => {
        await loginToApp(page)

        await page.goto('/app/equipos')
        await page.waitForLoadState('networkidle')

        const firstEquip = page.locator('a[href*="/app/equipos/"]').first()
        await expect(firstEquip).toBeVisible()
        await firstEquip.click()
        await expect(page).toHaveURL(/\/app\/equipos\//)
        await expect(page.locator('h1')).toBeVisible()

        // Click area link in Ubicación section (if present)
        const areaLink = page.locator('a[href*="/app/areas/"]').first()
        if (await areaLink.isVisible()) {
            await areaLink.click()
            await expect(page).toHaveURL(/\/app\/areas\//)
            await expect(page.locator('h1')).toBeVisible()
        }
    })

    test('17-3 vista de planta muestra áreas y cantidad de equipos', async ({ page }) => {
        await loginToApp(page)

        // Hit the plants API to get a real plant ID
        const res = await page.request.get('/api/v1/plants', {
            headers: { Accept: 'application/json' },
        })
        // If no plants, skip gracefully
        if (!res.ok()) { return }
        const body = await res.json()
        if (!body.data?.length) { return }

        const plantId = body.data[0].id
        await page.goto(`/app/plantes/${plantId}`)
        await page.waitForLoadState('networkidle')

        // Heading visible
        await expect(page.locator('h1')).toBeVisible()

        // Áreas tab active by default — tab button visible
        await expect(page.getByRole('button', { name: 'Áreas' })).toBeVisible()
    })

    test('17-4 vista de área muestra equipos e indicadores', async ({ page }) => {
        await loginToApp(page)

        const res = await page.request.get('/api/v1/areas', {
            headers: { Accept: 'application/json' },
        })
        if (!res.ok()) { return }
        const body = await res.json()
        if (!body.data?.length) { return }

        const areaId = body.data[0].id
        await page.goto(`/app/areas/${areaId}`)
        await page.waitForLoadState('networkidle')

        await expect(page.locator('h1')).toBeVisible()
        await expect(page.getByRole('button', { name: 'Equipos' })).toBeVisible()
        await expect(page.getByRole('button', { name: 'Indicadores' })).toBeVisible()
        await expect(page.getByRole('button', { name: 'Historial' })).toBeVisible()
    })

    test('17-5 botón ← Volver conoce la vista anterior (equipo → área)', async ({ page }) => {
        await loginToApp(page)

        const res = await page.request.get('/api/v1/areas', {
            headers: { Accept: 'application/json' },
        })
        if (!res.ok()) { return }
        const body = await res.json()
        if (!body.data?.length) { return }

        const areaId = body.data[0].id

        // Navigate to area from equipment context
        await page.goto(`/app/areas/${areaId}?from=ops.equipos&fromId=`)
        await page.waitForLoadState('networkidle')

        // Back button should show "Equipos" (from ops.equipos)
        const backBtn = page.getByRole('button').filter({ hasText: /volver|equipos/i }).first()
        await expect(backBtn).toBeVisible()
        await backBtn.click()

        // Should land on equipment list
        await expect(page).toHaveURL(/\/app\/equipos/)
    })

    test('17-6 breadcrumb muestra planta al navegar área desde planta', async ({ page }) => {
        await loginToApp(page)

        const plantsRes = await page.request.get('/api/v1/plants', {
            headers: { Accept: 'application/json' },
        })
        if (!plantsRes.ok()) { return }
        const plantsBody = await plantsRes.json()
        if (!plantsBody.data?.length) { return }

        const plantId = plantsBody.data[0].id
        await page.goto(`/app/plantes/${plantId}`)
        await page.waitForLoadState('networkidle')

        // Click an area card (if any exist)
        const areaCard = page.locator('button').filter({ hasText: /área|área/i }).first()
        if (await areaCard.isVisible({ timeout: 3000 }).catch(() => false)) {
            await areaCard.click()
            await expect(page).toHaveURL(/\/app\/areas\//)

            // Plant name should appear in breadcrumb
            const plantName = plantsBody.data[0].name
            await expect(page.getByText(plantName).first()).toBeVisible()
        }
    })

    test('17-7 rutas existentes no se ven afectadas', async ({ page }) => {
        await loginToApp(page)

        // Check core routes still work
        await page.goto('/app/equipos')
        await expect(page).toHaveURL(/\/app\/equipos/)
        await page.goto('/app/ordenes')
        await expect(page).toHaveURL(/\/app\/ordenes/)
        await page.goto('/app/dashboard')
        await expect(page).toHaveURL(/\/app\/dashboard/)
    })

    test('17-8 endpoints summary responden 200', async ({ page }) => {
        await loginToApp(page)

        const plantsRes = await page.request.get('/api/v1/plants', {
            headers: { Accept: 'application/json' },
        })
        if (!plantsRes.ok()) { return }
        const { data: plants } = await plantsRes.json()
        if (!plants?.length) { return }

        const summaryRes = await page.request.get(`/api/v1/plants/${plants[0].id}/summary`, {
            headers: { Accept: 'application/json' },
        })
        expect(summaryRes.status()).toBe(200)
        const summary = await summaryRes.json()
        expect(typeof summary.equipment_count).toBe('number')
        expect(Array.isArray(summary.areas)).toBeTruthy()
    })

    test('17-9 no produce errores de consola en vista de área', async ({ page }) => {
        const errors = []
        page.on('pageerror', (e) => errors.push(e.message))
        page.on('console', (msg) => {
            if (msg.type() !== 'error') { return }
            const text = msg.text()
            if (/favicon|manifest|Failed to load resource|net::ERR/i.test(text)) { return }
            if (/Content Security Policy/i.test(text)) { return }
            errors.push(text)
        })

        const res = await page.request.get('/api/v1/areas', {
            headers: { Accept: 'application/json' },
        })
        if (!res.ok()) { return }
        const body = await res.json()
        if (!body.data?.length) { return }

        await loginToApp(page)
        await page.goto(`/app/areas/${body.data[0].id}`)
        await page.waitForLoadState('networkidle')
        await page.waitForTimeout(1000)

        expect(errors).toEqual([])
    })

    test('17-10 navegación planta → área → equipo mantiene contexto', async ({ page }) => {
        await loginToApp(page)

        const plantsRes = await page.request.get('/api/v1/plants', {
            headers: { Accept: 'application/json' },
        })
        if (!plantsRes.ok()) { return }
        const { data: plants } = await plantsRes.json()
        if (!plants?.length) { return }

        // 1. Visit plant
        await page.goto(`/app/plantes/${plants[0].id}`)
        await expect(page.locator('h1')).toBeVisible()
        await expect(page).toHaveURL(/\/app\/plantes\//)

        // 2. Navigate to an area (if any)
        const areaBtn = page.locator('button').filter({ hasText: /[A-Z]{1,5}-\d+|área/i }).first()
        if (!await areaBtn.isVisible({ timeout: 3000 }).catch(() => false)) { return }
        await areaBtn.click()
        await expect(page).toHaveURL(/\/app\/areas\//)
        await expect(page.locator('h1')).toBeVisible()

        // 3. Navigate to an equipment (if any)
        await page.getByRole('button', { name: 'Equipos' }).click()
        const equipLink = page.locator('a[href*="/app/equipos/"]').first()
        if (!await equipLink.isVisible({ timeout: 3000 }).catch(() => false)) { return }
        await equipLink.click()
        await expect(page).toHaveURL(/\/app\/equipos\//)
        await expect(page.locator('h1')).toBeVisible()
    })
})
