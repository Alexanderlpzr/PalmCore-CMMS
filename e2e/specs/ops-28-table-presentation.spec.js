import { test, expect } from '@playwright/test'
import { adminUrl, PATHS } from '../helpers.js'

/*
 * Captura las tablas de mayor tráfico para revisar su presentación: alineación
 * de cifras, texto recortado y densidad de píldoras. Los tests unitarios
 * (tests/Unit/TablePresentationTest.php) fijan la convención leyendo el código;
 * esto comprueba lo que el navegador acaba pintando.
 */

const SHOTS = 'e2e/table-presentation/shots'

const TABLES = [
    { name: 'equipos', path: PATHS.equipment },
    { name: 'ordenes-de-trabajo', path: PATHS.workOrders },
    { name: 'repuestos', path: PATHS.spareParts },
]

for (const { name, path } of TABLES) {
    test(`${name}: las cifras se alinean a la derecha y las píldoras no se amontonan`, async ({ page }) => {
        await page.goto(adminUrl(path))
        await expect(page.locator('table').first()).toBeVisible()
        await page.waitForLoadState('networkidle')

        // Ninguna fila debería llevar más de dos píldoras: era el «muro» que hacía
        // que ningún estado destacara.
        const maxBadges = await page.evaluate(() => {
            const rows = [...document.querySelectorAll('table tbody tr')]

            return rows.reduce(
                (max, row) => Math.max(max, row.querySelectorAll('.fi-badge').length),
                0,
            )
        })

        expect(maxBadges, `${name}: ${maxBadges} píldoras en una misma fila`).toBeLessThanOrEqual(2)

        await page.screenshot({ path: `${SHOTS}/${name}.png`, fullPage: false })
    })
}

test('las filas alternan sombreado en todas las tablas', async ({ page }) => {
    await page.goto(adminUrl(PATHS.equipment))
    await expect(page.locator('table').first()).toBeVisible()

    // ->striped() estaba puesto en 3 de 69 tablas; ahora es un valor por defecto
    // global, así que la clase tiene que estar presente sin declararla por tabla.
    await expect(page.locator('.fi-ta-row-striped, table.fi-ta-table').first()).toBeVisible()
})
