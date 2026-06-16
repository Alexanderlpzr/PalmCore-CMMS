/**
 * Grupo 5 — Work Order ciclo operativo completo
 *
 * Flujo: OT existente (E2E-WO-0001, in_progress)
 *        → Registrar tiempo → Agregar comentario → Subir foto → Firma
 *        → Cerrar OT → Verificar OT cerrada
 *
 * Validaciones:
 *   1. Registro de tiempo exitoso (API POST /time-entries → 201)
 *   2. Comentarios visibles (Ops SPA UI: escribir + enviar + visible en lista)
 *   3. Evidencia fotográfica almacenada (API POST /media → 201)
 *   4. Firma guardada correctamente (API POST /signature → 201 + signature_type)
 *   5. Cambio de estado esperado (in_progress → completed → verified → closed)
 *   6. La OT cerrada aparece correctamente en el listado con filtro "Todas"
 *   7-8. Sin errores de consola ni page errors
 *
 * Datos sembrados: E2E-WO-0001 (equipo E2E-PRE-001, estado in_progress)
 * El seeder usa updateOrCreate para siempre resetear al estado inicial.
 * El token de test 1 se reutiliza en tests 3 y 4 para llamadas API directas.
 */
import { execSync } from 'child_process'
import { test, expect } from '@playwright/test'
import { loginToApp, PATHS } from '../helpers.js'

const WO_NUMBER = 'E2E-WO-0001'
const COMMENT_TEXT = `[E2E-G5] Comentario de prueba ${Date.now()}`

// Minimal 1×1 white JPEG — valid file for the attachment upload test.
const MINIMAL_JPEG = Buffer.from(
    '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8U' +
    'HRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/wAARCAABAAEDASIA' +
    'AhEBAxEB/8QAFAABAAAAAAAAAAAAAAAAAAAACf/EABQQAQAAAAAAAAAAAAAAAAAAAAD/xAAU' +
    'AQEAAAAAAAAAAAAAAAAAAAAA/8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAwDAQACEQMRAD8A' +
    'JgAB/9k=',
    'base64',
)

// State shared between serial tests
let token = '' // Sanctum Bearer token (set in test 1, reused in tests 3 and 4)
let woId = ''  // UUID of E2E-WO-0001 (resolved in test 1 via search API)

/** Extract the Pinia auth token from the mounted Vue app. */
async function extractToken(page) {
    return page.evaluate(
        () =>
            document
                .querySelector('#ops-app')
                ?.__vue_app__?.config?.globalProperties?.$pinia?.state?.value?.auth
                ?.token ?? null,
    )
}

/** Resolve the UUID of E2E-WO-0001 via the tenant-scoped search API. */
async function resolveWoId(page, tok) {
    const resp = await page.request.get(`/api/v1/work-orders?search=${WO_NUMBER}`, {
        headers: { Authorization: `Bearer ${tok}`, Accept: 'application/json' },
    })
    const body = await resp.json()
    const wo = (body.data ?? []).find((w) => w.work_order_number === WO_NUMBER)
    return wo?.id ?? null
}

test.describe.serial('Grupo 5 — Work Order ciclo operativo completo', () => {
    test.beforeEach(() => {
        // Reset the api-tokens rate limiter (5/min/IP) so loginToApp() steps
        // never trip on the rapid-execution cadence of the test suite.
        execSync('php artisan cache:clear', { stdio: 'ignore' })
    })

    // ── Validación 1: Registro de tiempo ─────────────────────────────────────
    test('1. el registro de tiempo devuelve 201', async ({ page }) => {
        await loginToApp(page)

        token = await extractToken(page)
        expect(token).toBeTruthy()

        woId = await resolveWoId(page, token)
        expect(woId).toBeTruthy()

        const now = new Date()
        const startedAt = new Date(now.getTime() - 60 * 60 * 1000).toISOString() // 1h ago

        const resp = await page.request.post(`/api/v1/work-orders/${woId}/time-entries`, {
            headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
            data: {
                started_at: startedAt,
                ended_at: now.toISOString(),
                description: '[E2E] Revisión hidráulica — registrado por test automatizado',
            },
        })

        expect(resp.status()).toBe(201)
        const body = await resp.json()
        expect(body.data?.id).toBeTruthy()
        expect(body.data?.hours).toBe(1)
    })

    // ── Validación 2: Comentarios visibles ───────────────────────────────────
    test('2. el comentario enviado aparece en la lista de comentarios', async ({ page }) => {
        await loginToApp(page)

        // Navigate via sidebar to preserve in-memory Pinia auth token
        await page.getByRole('link', { name: 'Órdenes de trabajo', exact: true }).click()
        await page.waitForURL('**/app/ordenes')

        // Search and open E2E-WO-0001 (in_progress → appears in default filter)
        await page.locator('input[placeholder*="Buscar"]').fill(WO_NUMBER)
        await page.waitForResponse(
            (r) =>
                r.url().includes('/api/v1/work-orders') &&
                r.url().includes('search=') &&
                r.request().method() === 'GET',
            { timeout: 10_000 },
        )
        await page.getByText(WO_NUMBER).first().click()
        await page.waitForURL(/\/app\/ordenes\/[^/]+$/, { timeout: 10_000 })

        // Click the Comentarios tab (rendered as a <button> with text label)
        await page.getByRole('button', { name: 'Comentarios' }).click()

        // Fill the compose textarea and submit
        await page.locator('textarea[placeholder*="comentario"]').fill(COMMENT_TEXT)
        await page.getByRole('button', { name: 'Enviar', exact: true }).click()

        // submitComment() appends the comment client-side after the POST succeeds
        await page.waitForResponse(
            (r) => r.url().includes('/comments') && r.request().method() === 'POST',
            { timeout: 10_000 },
        )

        await expect(page.getByText(COMMENT_TEXT)).toBeVisible({ timeout: 10_000 })
    })

    // ── Validación 3: Evidencia fotográfica almacenada ───────────────────────
    test('3. la evidencia fotográfica almacenada devuelve 201', async ({ page }) => {
        // Reuse token and woId from test 1 — Sanctum tokens are long-lived and
        // remain valid across the entire serial suite.
        expect(token).toBeTruthy()
        expect(woId).toBeTruthy()

        const resp = await page.request.post(`/api/v1/work-orders/${woId}/media`, {
            headers: {
                Authorization: `Bearer ${token}`,
                Accept: 'application/json',
            },
            multipart: {
                file: {
                    name: 'e2e-evidence.jpg',
                    mimeType: 'image/jpeg',
                    buffer: MINIMAL_JPEG,
                },
                attachment_type: 'before_photo',
                caption: '[E2E] Foto de evidencia — subida por test automatizado',
            },
        })

        expect(resp.status()).toBe(201)
        const body = await resp.json()
        expect(body.data?.id).toBeTruthy()
    })

    // ── Validación 4: Firma guardada correctamente ────────────────────────────
    test('4. la firma del técnico se guarda correctamente', async ({ page }) => {
        expect(token).toBeTruthy()
        expect(woId).toBeTruthy()

        const resp = await page.request.post(`/api/v1/work-orders/${woId}/signature`, {
            headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
            data: {
                signature_type: 'technician_completion',
                notes: '[E2E] Firma registrada por test automatizado',
            },
        })

        expect(resp.status()).toBe(201)
        const body = await resp.json()
        expect(body.data?.signature_type).toBe('technician_completion')
    })

    // ── Validación 5: Ciclo de estado completo ───────────────────────────────
    test('5. el ciclo in_progress → completed → verified → closed funciona en el Ops SPA', async ({ page }) => {
        await loginToApp(page)
        await page.getByRole('link', { name: 'Órdenes de trabajo', exact: true }).click()
        await page.waitForURL('**/app/ordenes')

        await page.locator('input[placeholder*="Buscar"]').fill(WO_NUMBER)
        await page.waitForResponse(
            (r) =>
                r.url().includes('/api/v1/work-orders') &&
                r.url().includes('search=') &&
                r.request().method() === 'GET',
            { timeout: 10_000 },
        )
        await page.getByText(WO_NUMBER).first().click()
        await page.waitForURL(/\/app\/ordenes\/[^/]+$/, { timeout: 10_000 })

        // in_progress → completed
        // "Completar" is the primary transition button for in_progress status
        await page.getByRole('button', { name: 'Completar', exact: true }).click()
        await page.waitForResponse(
            (r) => r.url().includes('/status') && r.request().method() === 'PATCH',
            { timeout: 10_000 },
        )
        // Vue recomputes primaryTransition after wo.value is updated — "Verificar"
        // appears only when status === completed
        await expect(
            page.getByRole('button', { name: 'Verificar', exact: true }),
        ).toBeVisible({ timeout: 10_000 })

        // completed → verified
        await page.getByRole('button', { name: 'Verificar', exact: true }).click()
        await page.waitForResponse(
            (r) => r.url().includes('/status') && r.request().method() === 'PATCH',
            { timeout: 10_000 },
        )
        // "Cerrar" appears only when status === verified
        await expect(
            page.getByRole('button', { name: 'Cerrar', exact: true }),
        ).toBeVisible({ timeout: 10_000 })

        // verified → closed
        await page.getByRole('button', { name: 'Cerrar', exact: true }).click()
        await page.waitForResponse(
            (r) => r.url().includes('/status') && r.request().method() === 'PATCH',
            { timeout: 10_000 },
        )
        // No entry in transitionMap for "closed" — all transition buttons disappear
        await expect(
            page.getByRole('button', { name: 'Completar', exact: true }),
        ).not.toBeVisible()
        await expect(
            page.getByRole('button', { name: 'Verificar', exact: true }),
        ).not.toBeVisible()
        await expect(
            page.getByRole('button', { name: 'Cerrar', exact: true }),
        ).not.toBeVisible()
    })

    // ── Validación 6: OT cerrada aparece en el listado ────────────────────────
    test('6. la OT cerrada aparece en el listado con filtro "Todas"', async ({ page }) => {
        await loginToApp(page)
        await page.getByRole('link', { name: 'Órdenes de trabajo', exact: true }).click()
        await page.waitForURL('**/app/ordenes')

        // Closed WOs are excluded from the default active filter — switch to Todas
        await page.getByRole('button', { name: /^Todas$/i }).click()

        await page.locator('input[placeholder*="Buscar"]').fill(WO_NUMBER)
        const resp = await page.waitForResponse(
            (r) =>
                r.url().includes('/api/v1/work-orders') &&
                r.url().includes('search=') &&
                r.request().method() === 'GET',
            { timeout: 10_000 },
        )

        expect(resp.status()).toBe(200)
        await expect(page.getByText(WO_NUMBER)).toBeVisible({ timeout: 10_000 })
    })

    // ── Validaciones 7-8: Sin errores de consola ni page errors ──────────────
    test('7-8. sin errores de consola ni page errors', async ({ page }) => {
        const errors = []
        page.on('pageerror', (e) => errors.push(`pageerror: ${e.message}`))
        page.on('console', (msg) => {
            if (msg.type() !== 'error') return
            const text = msg.text()
            if (/favicon|manifest|Failed to load resource|net::ERR/i.test(text)) return
            if (/Content Security Policy/i.test(text)) return
            errors.push(`console: ${text}`)
        })

        await loginToApp(page)
        await page.getByRole('link', { name: 'Órdenes de trabajo', exact: true }).click()
        await page.waitForURL('**/app/ordenes')

        // Search closed WO in "Todas" filter
        await page.getByRole('button', { name: /^Todas$/i }).click()
        await page.locator('input[placeholder*="Buscar"]').fill(WO_NUMBER)
        await page.waitForResponse(
            (r) =>
                r.url().includes('/api/v1/work-orders') &&
                r.url().includes('search=') &&
                r.request().method() === 'GET',
            { timeout: 10_000 },
        )

        await page.getByText(WO_NUMBER).first().click()
        await page.waitForURL(/\/app\/ordenes\/[^/]+$/, { timeout: 10_000 })
        await page.waitForTimeout(500)

        expect(errors).toEqual([])
    })
})
