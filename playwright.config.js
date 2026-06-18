import { defineConfig, devices } from '@playwright/test'

export default defineConfig({
    testDir: 'e2e/specs',
    fullyParallel: false,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    workers: 1,
    reporter: [
        ['html', { outputFolder: 'e2e/report', open: 'never' }],
        ['list'],
    ],
    use: {
        baseURL: 'http://localhost:8000',
        storageState: 'e2e/.auth/admin.json',
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
        actionTimeout: 20_000,
        navigationTimeout: 30_000,
        locale: 'es-CO',
    },
    globalSetup: './e2e/global-setup.js',
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
    webServer: {
        command: 'php artisan serve --port=8000',
        port: 8000,
        reuseExistingServer: !process.env.CI,
        timeout: 30_000,
        env: {
            QUEUE_CONNECTION: 'sync',
            FAKE_WEBHOOK_RESPONSES: 'true',
        },
    },
})
