import path from 'path'
import { fileURLToPath } from 'url'
import { defineConfig, devices } from '@playwright/test'

const repoRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..')

/**
 * Dedicated config for the HOME-2 visual review. Reuses the base globalSetup
 * (seeds E2EDataSeeder + saves the Filament admin session) and the auto-started
 * artisan server, but points at the home2-visual spec and enables video output.
 */
export default defineConfig({
    testDir: '.',
    fullyParallel: false,
    workers: 1,
    timeout: 90_000,
    reporter: [['list']],
    outputDir: './videos',
    use: {
        baseURL: 'http://localhost:8000',
        storageState: 'e2e/.auth/admin.json',
        actionTimeout: 20_000,
        navigationTimeout: 30_000,
        locale: 'es-CO',
    },
    globalSetup: '../global-setup.js',
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
    webServer: {
        command: 'php -d memory_limit=1G -d max_execution_time=0 -d realpath_cache_size=4096k artisan serve --host=127.0.0.1 --port=8000 --no-reload',
        cwd: repoRoot,
        port: 8000,
        reuseExistingServer: true,
        timeout: 60_000,
        env: {
            QUEUE_CONNECTION: 'sync',
            FAKE_WEBHOOK_RESPONSES: 'true',
        },
    },
})
