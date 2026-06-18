import { test, expect } from '@playwright/test'
import { BASE } from '../helpers.js'

test.describe('Grupo 15 — Health Check Endpoint', () => {
    let response
    let body

    test.beforeEach(async ({ request }) => {
        response = await request.get(`${BASE}/api/health`)
        body = await response.json()
    })

    test('15-1: /api/health responde HTTP 200', async () => {
        expect(response.status()).toBe(200)
    })

    test('15-2: status es "ok"', async () => {
        expect(body.status).toBe('ok')
    })

    test('15-3: check database presente y true', async () => {
        expect(body.checks).toBeDefined()
        expect(body.checks.database).toBe(true)
    })

    test('15-4: check cache presente y true', async () => {
        expect(body.checks.cache).toBe(true)
    })

    test('15-5: check queue presente y true', async () => {
        expect(body.checks.queue).toBe(true)
    })

    test('15-6: check storage presente y true', async () => {
        expect(body.checks.storage).toBe(true)
    })

    test('15-7: timestamp presente y es ISO 8601', async () => {
        expect(body.timestamp).toBeDefined()
        expect(new Date(body.timestamp).toString()).not.toBe('Invalid Date')
    })

    test('15-8: multi-tenant — responde sin header de tenant', async ({ request }) => {
        // The health endpoint has no tenant context — confirm it still returns 200
        // even without any tenant-specific headers
        const res = await request.get(`${BASE}/api/health`, {
            headers: { 'Accept': 'application/json' }
        })
        expect(res.status()).toBe(200)
        const b = await res.json()
        expect(b.status).toBe('ok')
    })
})
