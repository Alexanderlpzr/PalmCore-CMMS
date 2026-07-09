/**
 * HOME-2 Visual Review — real Playwright captures of the Inicio portal.
 *
 * Produces screenshots across viewports (desktop/laptop/tablet/mobile), section
 * close-ups, dark mode, the empty-state tenant, and a short interaction video
 * (entry → slide change → card hover → navigation).
 *
 * Uses the stored Filament admin session (e2e/.auth/admin.json) configured in
 * playwright.config.js. Demo content is seeded by scratchpad/seed_home2_demo.php
 * in beforeAll so the carousel and news render.
 *
 * Run:  npx playwright test --config=e2e/home2-visual/playwright.home2.config.js
 */
import { execSync } from 'child_process'
import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'
import { test, expect } from '@playwright/test'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const OUT = path.join(__dirname, 'shots')
const TENANT = 'el-pajuil'
const EMPTY_TENANT = 'home2-empty'
const BASE = 'http://localhost:8000'

const VIEWPORTS = {
    desktop: { width: 1440, height: 900 },
    laptop: { width: 1280, height: 800 },
    tablet: { width: 834, height: 1112 },
    mobile: { width: 390, height: 844 },
}

fs.mkdirSync(OUT, { recursive: true })

/** Force Filament into dark/light by seeding its theme preference pre-load. */
async function setTheme(context, theme) {
    await context.addInitScript((t) => {
        try { localStorage.setItem('theme', t) } catch (e) {}
    }, theme)
}

async function gotoHome(page, tenant = TENANT) {
    await page.goto(`${BASE}/admin/${tenant}`, { waitUntil: 'networkidle' })
    // Hero greeting is the first thing rendered — wait for it.
    await page.waitForLoadState('networkidle')
}

test.beforeAll(() => {
    const seed = 'C:/Users/Ale/AppData/Local/Temp/claude/c--Users-Ale-OleoIngenieria/a32602d9-4d7a-4502-8c7e-120a70421418/scratchpad/seed_home2_demo.php'
    execSync(`php artisan tinker "${seed}"`, { stdio: 'inherit' })
})

// ── Full-page captures across viewports (with data, light) ───────────────────
for (const [name, viewport] of Object.entries(VIEWPORTS)) {
    test(`full page · ${name} · light`, async ({ page }) => {
        await page.setViewportSize(viewport)
        await gotoHome(page)
        await page.screenshot({ path: path.join(OUT, `full-${name}-light.png`), fullPage: true })
    })
}

// ── Dark mode full page (desktop) ────────────────────────────────────────────
test('full page · desktop · dark', async ({ browser }) => {
    const context = await browser.newContext({
        storageState: 'e2e/.auth/admin.json',
        viewport: VIEWPORTS.desktop,
    })
    await setTheme(context, 'dark')
    const page = await context.newPage()
    await gotoHome(page)
    await page.emulateMedia({ colorScheme: 'dark' })
    await page.screenshot({ path: path.join(OUT, 'full-desktop-dark.png'), fullPage: true })
    await context.close()
})

// ── Section close-ups (desktop, light) ───────────────────────────────────────
const SECTIONS = [
    { file: 'section-hero', selector: 'section[aria-label="Bienvenida"]' },
    { file: 'section-attention', selector: 'section[aria-labelledby="attention-heading"]' },
    { file: 'section-actions', selector: 'section[aria-labelledby="actions-heading"]' },
    { file: 'section-carousel', selector: 'section[aria-label="Carrusel institucional"]' },
    { file: 'section-news', selector: 'section[aria-labelledby="news-heading"]' },
    { file: 'section-activity', selector: 'section[aria-labelledby="activity-heading"]' },
]

test('section close-ups · desktop · light', async ({ page }) => {
    await page.setViewportSize(VIEWPORTS.desktop)
    await gotoHome(page)
    for (const { file, selector } of SECTIONS) {
        const el = page.locator(selector).first()
        if (await el.count()) {
            await el.scrollIntoViewIfNeeded()
            await page.waitForTimeout(300)
            await el.screenshot({ path: path.join(OUT, `${file}.png`) })
        }
    }
})

// ── Empty-state tenant (sin datos) ───────────────────────────────────────────
test('empty state · desktop · light', async ({ page }) => {
    await page.setViewportSize(VIEWPORTS.desktop)
    await gotoHome(page, EMPTY_TENANT)
    await page.screenshot({ path: path.join(OUT, 'empty-state-desktop.png'), fullPage: true })
})

test('empty state · mobile · light', async ({ page }) => {
    await page.setViewportSize(VIEWPORTS.mobile)
    await gotoHome(page, EMPTY_TENANT)
    await page.screenshot({ path: path.join(OUT, 'empty-state-mobile.png'), fullPage: true })
})

// ── Interaction tour (recorded to video) ─────────────────────────────────────
test('interaction tour · entry · slide change · hover · navigation', async ({ browser }) => {
    const context = await browser.newContext({
        storageState: 'e2e/.auth/admin.json',
        viewport: VIEWPORTS.desktop,
        recordVideo: { dir: path.join(OUT, 'video'), size: { width: 1440, height: 900 } },
    })
    const page = await context.newPage()
    try {
        await gotoHome(page)
        await page.waitForTimeout(1200)

        // Change carousel slide via the next arrow (if present)
        const next = page.getByRole('button', { name: 'Diapositiva siguiente' })
        if (await next.count()) {
            await next.click()
            await page.waitForTimeout(1200)
            await next.click()
            await page.waitForTimeout(1200)
        }

        // Hover over attention cards
        const attentionCards = page.locator('section[aria-labelledby="attention-heading"] a')
        const n = Math.min(await attentionCards.count(), 4)
        for (let i = 0; i < n; i++) {
            await attentionCards.nth(i).hover()
            await page.waitForTimeout(500)
        }

        // Hover over quick-action tiles
        const actionTiles = page.locator('section[aria-labelledby="actions-heading"] a')
        const m = Math.min(await actionTiles.count(), 6)
        for (let i = 0; i < m; i++) {
            await actionTiles.nth(i).hover()
            await page.waitForTimeout(350)
        }

        // Scroll the narrative top → bottom
        await page.evaluate(async () => {
            for (let y = 0; y <= document.body.scrollHeight; y += 240) {
                window.scrollTo(0, y)
                await new Promise((r) => setTimeout(r, 120))
            }
        })
        await page.waitForTimeout(800)
    } finally {
        await context.close() // flushes the recorded video to disk
    }
})
