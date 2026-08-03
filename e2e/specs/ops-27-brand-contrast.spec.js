import { test, expect } from '@playwright/test'
import { BASE, adminUrl } from '../helpers.js'

/*
 * El login se veía roto en modo oscuro: la tarjeta estaba fijada en crema
 * (bg-[rgba(251,250,246,0.94)]) mientras los campos, etiquetas y mensajes de
 * error de adentro son componentes de Filament, que sí siguen el tema. Con el
 * sistema en oscuro quedaban texto claro sobre crema — ilegible.
 *
 * Los tests unitarios de paleta (tests/Unit/BrandPaletteTest.php) comprueban que
 * los colores elegidos contrastan; lo que no pueden ver es qué color termina
 * pintando el navegador después de la cascada. Eso es lo que mide este spec:
 * lee los colores computados del DOM real, en los dos temas.
 */

// Bajo .../shots para que .gitignore las excluya, igual que el resto de capturas.
const SHOTS = 'e2e/brand-contrast/shots'

/** Convierte 'rgb(r, g, b)' o 'rgba(r, g, b, a)' a [r, g, b]. */
function parseRgb(value) {
    const [r, g, b] = value.match(/\d+(\.\d+)?/g).map(Number)

    return [r, g, b]
}

/*
 * Chrome devuelve los colores de Filament tal cual se declararon, y Filament los
 * declara en oklch() — 'oklch(1 0 0)' es blanco, pero leído como si fuera rgb()
 * da negro. Se normaliza pintando el color en un canvas de 1×1 y leyendo el píxel,
 * que resuelve cualquier formato CSS al rgb real que ve el usuario.
 */
const NORMALIZE = `(color) => {
    const canvas = document.createElement('canvas')
    canvas.width = canvas.height = 1
    const ctx = canvas.getContext('2d')
    ctx.fillStyle = color
    ctx.fillRect(0, 0, 1, 1)
    const [r, g, b] = ctx.getImageData(0, 0, 1, 1).data

    return 'rgb(' + r + ', ' + g + ', ' + b + ')'
}`

/** Color CSS resuelto a 'rgb(r, g, b)' real, sea cual sea su formato de origen. */
async function normalize(page, color) {
    return page.evaluate(`(${NORMALIZE})(${JSON.stringify(color)})`)
}

/** Luminancia relativa WCAG 2.1. */
function luminance([r, g, b]) {
    const channel = (c) => {
        c /= 255

        return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4
    }

    return 0.2126 * channel(r) + 0.7152 * channel(g) + 0.0722 * channel(b)
}

/** Razón de contraste WCAG 2.1 entre dos colores CSS computados. */
function contrast(foreground, background) {
    const a = luminance(parseRgb(foreground))
    const b = luminance(parseRgb(background))
    const [hi, lo] = a > b ? [a, b] : [b, a]

    return (hi + 0.05) / (lo + 0.05)
}

/**
 * Color de fondo efectivo de un elemento: sube por los ancestros hasta encontrar
 * uno que no sea transparente, que es lo que el ojo ve de verdad detrás del texto.
 */
async function effectiveBackground(locator) {
    return locator.evaluate((node) => {
        let current = node

        while (current) {
            const bg = getComputedStyle(current).backgroundColor
            const alpha = bg.startsWith('rgba') ? parseFloat(bg.split(',')[3]) : 1

            if (bg !== 'transparent' && alpha > 0.5) {
                return bg
            }

            current = current.parentElement
        }

        return 'rgb(255, 255, 255)'
    })
}

for (const scheme of ['light', 'dark']) {
    test.describe(`login en modo ${scheme}`, () => {
        test.use({ colorScheme: scheme, storageState: { cookies: [], origins: [] } })

        test(`los textos de la tarjeta contrastan con su fondo (${scheme})`, async ({ page }) => {
            await page.goto(`${BASE}/admin/login`)

            const heading = page.getByRole('heading', { name: 'Entra a tu cuenta' })
            await expect(heading).toBeVisible()

            // El título y la etiqueta del campo de correo son exactamente los
            // elementos que quedaban blancos sobre crema.
            const targets = [
                { name: 'título', locator: heading },
                {
                    name: 'etiqueta de correo',
                    locator: page.locator('label').filter({ hasText: /correo|email/i }).first(),
                },
            ]

            for (const { name, locator } of targets) {
                const color = await normalize(page, await locator.evaluate((n) => getComputedStyle(n).color))
                const background = await normalize(page, await effectiveBackground(locator))
                const ratio = contrast(color, background)

                expect(
                    ratio,
                    `${name} en modo ${scheme}: ${color} sobre ${background} = ${ratio.toFixed(2)}:1`,
                ).toBeGreaterThanOrEqual(4.5)
            }

            await page.screenshot({ path: `${SHOTS}/login-${scheme}.png`, fullPage: true })
        })

        test(`el botón de entrar usa el verde del logotipo (${scheme})`, async ({ page }) => {
            await page.goto(`${BASE}/admin/login`)

            const submit = page.locator('button[type="submit"]')
            await expect(submit).toBeVisible()

            const background = await normalize(page, await submit.evaluate((n) => getComputedStyle(n).backgroundColor))
            const color = await normalize(page, await submit.evaluate((n) => getComputedStyle(n).color))
            const ratio = contrast(color, background)

            expect(
                ratio,
                `botón primario en modo ${scheme}: ${color} sobre ${background} = ${ratio.toFixed(2)}:1`,
            ).toBeGreaterThanOrEqual(4.5)

            // Verde, no el emerald anterior ni un azul: el canal verde domina.
            const [r, g, b] = parseRgb(background)
            expect(g, `el botón debería ser verde, es rgb(${r}, ${g}, ${b})`).toBeGreaterThan(r)
            expect(g).toBeGreaterThan(b)

            // Y tiene que ser el verde FUERTE del logotipo con texto claro, no el
            // tono 400 pálido al que Filament cae por defecto con las paletas verdes
            // y que hace parecer el botón deshabilitado. Se comprueba por
            // luminancia: el fondo oscuro y el texto claro, no al revés.
            expect(
                luminance(parseRgb(background)),
                `el botón debería ser verde intenso, no pálido: ${background}`,
            ).toBeLessThan(luminance(parseRgb(color)))
        })
    })
}

for (const scheme of ['light', 'dark']) {
    test.describe(`panel admin en modo ${scheme}`, () => {
        test.use({ colorScheme: scheme })

        test(`el panel se pinta con la marca (${scheme})`, async ({ page }) => {
            await page.goto(adminUrl())
            await page.waitForLoadState('networkidle')

            await page.screenshot({ path: `${SHOTS}/admin-${scheme}.png`, fullPage: false })
        })

        /*
         * El desplegable de un <select> nativo lo dibuja el sistema operativo y no
         * sale en una captura de pantalla, así que la única forma de verlo es leer
         * el color computado de las <option>. En oscuro heredaban `dark:text-white`
         * sobre el fondo por defecto del popup —blanco sobre blanco— y la lista de
         * secciones no se leía.
         */
        test(`las opciones del filtro de sección contrastan con su fondo (${scheme})`, async ({ page }) => {
            await page.goto(adminUrl('/equipos'))
            await page.waitForLoadState('networkidle')

            const option = page.locator('select[aria-label="Filtrar por sección"] option').nth(1)
            await expect(option).toHaveCount(1)

            const color = await normalize(page, await option.evaluate((n) => getComputedStyle(n).color))
            const background = await normalize(page, await option.evaluate((n) => getComputedStyle(n).backgroundColor))
            const ratio = contrast(color, background)

            expect(
                ratio,
                `opción de sección en modo ${scheme}: ${color} sobre ${background} = ${ratio.toFixed(2)}:1`,
            ).toBeGreaterThanOrEqual(4.5)
        })
    })
}
