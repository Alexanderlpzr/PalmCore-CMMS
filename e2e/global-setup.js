import { chromium } from '@playwright/test'
import { execSync } from 'child_process'

const BASE_URL = 'http://localhost:8000'
const TENANT = 'el-pajuil'

export default async function globalSetup() {
    console.log('[E2E] Seeding E2E fixtures...')
    execSync('php artisan db:seed --class=E2EDataSeeder --force', {
        stdio: 'inherit',
    })

    console.log('[E2E] Saving auth state for admin@elpajuil.demo...')
    const browser = await chromium.launch()
    const page = await browser.newPage()

    await page.goto(`${BASE_URL}/admin/login`)
    await page.locator('input[name="email"], input[type="email"]').fill('admin@elpajuil.demo')
    await page.locator('input[name="password"], input[type="password"]').fill('password')
    await page.locator('button[type="submit"]').click()

    // Filament lands on the tenant dashboard root (/admin/<tenant>), with or
    // without a trailing path — match both.
    await page.waitForURL(`**/admin/${TENANT}**`, { timeout: 20_000 })

    await page.context().storageState({ path: 'e2e/.auth/admin.json' })
    await browser.close()

    console.log('[E2E] Auth state saved.')
}
