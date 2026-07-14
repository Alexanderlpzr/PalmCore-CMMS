/**
 * Grupo 26 — Fase 1 y 2: paros, horómetros y checklist ejecutable
 *
 * M5. Lo que Pest ya prueba es que el backend responde: que un paro no se solapa,
 * que el acumulado no retrocede, que una OT no cierra con mediciones en blanco.
 * Lo que Pest **no** ve es si el técnico con guantes puede llegar de la pantalla al
 * dato: un botón que no aparece porque el `v-if` mira un campo renombrado, un modal
 * que no cierra, una respuesta que se guarda pero no se pinta.
 *
 * Cubre:
 *   26A — Paros (SPA /app/paros): registrar, cerrar, clasificar Tipo I, y la firma
 *         de producción (A5), que hoy no tiene ninguna prueba de navegador.
 *   26B — Ronda de horómetros (SPA /app/horometros): la carga en bloque y el cambio
 *         de dial, que el sistema debe aceptar como reset en vez de rechazar.
 *   26C — Checklist ejecutable (PWA /mobile): medir, guardar y ver que la OT se
 *         niega a cerrarse con una medición obligatoria en blanco.
 *
 * Fixtures (E2EDataSeeder):
 *   E2E-PRE-001, E2E-PRE-002 — equipos
 *   E2E-WO-CHECK — OT en ejecución con checklist congelado (1 tarea, 2 ítems)
 */
import { execFileSync } from 'child_process'
import { expect, test } from '@playwright/test'
import { BASE, loginToApp } from '../helpers.js'

const MOBILE_BASE = `${BASE}/mobile`
const WO_CHECK = 'E2E-WO-CHECK'

// ── Tinker helpers ────────────────────────────────────────────────────────────

/**
 * Se invoca a PHP con execFileSync —sin shell— a propósito.
 *
 * `execSync` pasa el comando por un intérprete, y ahí las contrabarras de un nombre
 * de clase (`App\Models\WorkOrder`) desaparecen o sobreviven según el shell desde el
 * que se lance la suite. El test entonces no falla: recibe una cadena vacía y la
 * compara contra lo que espera, que es la peor forma de fallar — en silencio.
 */
function tinker(phpExpr) {
    try {
        return execFileSync(
            'php',
            ['artisan', 'tinker', '--execute', `echo ${phpExpr};`],
            { encoding: 'utf8', stdio: ['pipe', 'pipe', 'ignore'] },
        )
    } catch {
        return ''
    }
}

function tinkerString(phpExpr) {
    return tinker(phpExpr).replace(/[^\x20-\x7E -￿]/g, '').trim()
}

function tinkerInt(phpExpr) {
    const m = tinker(phpExpr).match(/-?\d+/)
    return m ? parseInt(m[0], 10) : 0
}

function tinkerFloat(phpExpr) {
    const m = tinker(phpExpr).match(/-?\d+(\.\d+)?/)
    return m ? parseFloat(m[0]) : 0
}

/**
 * Se lee por el query builder y no por Eloquent a propósito: los casts convierten
 * `stoppage_category` o `status` en un objeto enum que no se puede imprimir, y el
 * test se quedaría mirando una cadena vacía creyendo que el dato no existe.
 */
function latestStoppageValue(column) {
    return tinkerString(
        `\\DB::table('equipment_downtime_events')->orderByDesc('created_at')->value('${column}')`,
    )
}

function stoppageCount() {
    return tinkerInt(`\\DB::table('equipment_downtime_events')->where('source','manual')->count()`)
}

function stoppageValue(id, column) {
    return tinkerString(`\\DB::table('equipment_downtime_events')->where('id','${id}')->value('${column}')`)
}

/** Las horas que la máquina realmente trabajó — el número que nunca retrocede. */
function accumulatedOf(code) {
    return tinkerFloat(`\\DB::table('equipment')->where('code','${code}')->value('accumulated_meter_reading')`)
}

function readingsOf(code) {
    return tinkerInt(
        `\\DB::table('equipment_meter_readings')->whereIn('equipment_id',` +
        `\\DB::table('equipment')->where('code','${code}')->pluck('id'))->count()`,
    )
}

function taskStatus() {
    return tinkerString(
        `\\DB::table('work_order_tasks')->where('work_order_id','${WO_CHECK_ID}')->value('status')`,
    )
}

/**
 * Un token real de Sanctum, emitido en PHP. Se hace del lado del servidor para no
 * gastar el limitador de 5/min de POST /api/v1/tokens en cada test.
 */
function getMobileToken(email, tenantSlug) {
    const abilities = "['work-orders.read','work-orders.write','equipment.read','equipment.write','maintenance-requests.read','inventory.read','plants.read','areas.read']"
    const code = [
        `$u = App\\Models\\User::where('email','${email}')->first();`,
        `$t = App\\Models\\Tenant::where('slug','${tenantSlug}')->first();`,
        `$r = $u->createToken('E2E Grupo26', ${abilities}, now()->addDay());`,
        `$r->accessToken->forceFill(['tenant_id' => $t->id])->save();`,
        'echo $r->plainTextToken;',
    ].join(' ')

    try {
        return execFileSync(
            'php',
            ['artisan', 'tinker', '--execute', code],
            { encoding: 'utf8', stdio: ['pipe', 'pipe', 'ignore'] },
        ).replace(/[^\x20-\x7E-￿]/g, '').trim()
    } catch {
        return ''
    }
}

const MOBILE_TOKEN = getMobileToken('admin@elpajuil.demo', 'el-pajuil')

const WO_CHECK_ID = tinkerString(
    `\\DB::table('work_orders')->where('work_order_number','${WO_CHECK}')->value('id')`,
)

// ── Browser helpers ───────────────────────────────────────────────────────────

/**
 * Entra a la PWA sin pasar por el endpoint de refresh, que comparte el limitador
 * de 5/min con la emisión de tokens (mismo patrón que el Grupo 13).
 */
async function loginMobile(page) {
    await page.addInitScript((fakeToken) => {
        const orig = window.fetch.bind(window)
        window.fetch = function (url, opts) {
            if (typeof url === 'string' && url.includes('/api/v1/auth/refresh')) {
                return Promise.resolve(new Response(
                    JSON.stringify({ token: fakeToken, user: { name: 'E2E' }, tenant: { name: 'El Pajuil' } }),
                    { status: 200, headers: { 'Content-Type': 'application/json' } },
                ))
            }
            return orig(url, opts)
        }
    }, MOBILE_TOKEN)

    await page.goto(`${MOBILE_BASE}/dashboard`, { waitUntil: 'domcontentloaded', timeout: 15_000 })
    await page.waitForFunction(
        () => !!document.querySelector('#app')?.__vue_app__?.config?.globalProperties?.$pinia?.state?.value?.auth?.token,
        { timeout: 10_000 },
    )
}

/**
 * Navega dentro de la PWA por el router de Vue.
 *
 * Un `page.goto()` recarga la página entera: el token de acceso vive en memoria y
 * se pierde, la app intenta refrescarlo y el guard la manda al login antes de que
 * la respuesta llegue. La navegación cliente conserva la sesión, que es lo que
 * hace el técnico de verdad: no recarga la app, toca un botón.
 */
async function mobilePush(page, path) {
    await page.evaluate(async (p) => {
        const app = document.querySelector('#app')?.__vue_app__
        await app?.config?.globalProperties?.$router?.push(p)
    }, path)
}

/** Lo mismo en la SPA de operaciones, que monta en #ops-app. */
async function opsPush(page, path) {
    await page.evaluate(async (p) => {
        const app = document.querySelector('#ops-app')?.__vue_app__
        await app?.config?.globalProperties?.$router?.push(p)
    }, path)
    await page.waitForTimeout(800)
}

/** Una fecha/hora local en el formato que espera un <input type="datetime-local">. */
function localInput(date) {
    const pad = (n) => String(n).padStart(2, '0')
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}

// ══════════════════════════════════════════════════════════════════════════════
// Grupo 26A — Paros
// ══════════════════════════════════════════════════════════════════════════════

test.describe.serial('Grupo 26A — Paros', () => {
    // Una sola sesión para todo el grupo: POST /api/v1/tokens está limitado a 5/min,
    // y un login por test agota el limitador antes de terminar el grupo.
    let page

    // Cada verificación contra la base arranca un `artisan tinker` completo, y estos
    // tests hacen varias. El trabajo del navegador es rápido; el arranque de PHP no.
    test.beforeEach(() => {
        test.setTimeout(60_000)
    })

    test.beforeAll(async ({ browser }) => {
        page = await browser.newPage()
        await loginToApp(page)
    })

    test.afterAll(async () => {
        await page?.close()
    })

    test('26A-1: registrar un paro cerrado de planta y verlo en el listado', async () => {
        await opsPush(page, '/app/paros')

        const before = stoppageCount()

        await page.getByRole('button', { name: /Registrar paro/i }).click()

        // Paro de planta: falta de fruta. No es de ningún equipo, y es el caso más
        // común en una extractora — la mitad del Excel del cliente son estos.
        await page.getByRole('button', { name: 'Toda la planta', exact: true }).click()
        await page.locator('select').filter({ hasText: 'Selecciona…' }).first().selectOption('raw_material')
        await page.getByPlaceholder('Ej.: atasco en prensa 2').fill('[E2E] Sin fruta en tolva')

        const start = new Date(Date.now() - 4 * 60 * 60 * 1000)
        const end = new Date(Date.now() - 2 * 60 * 60 * 1000)
        await page.locator('input[type="datetime-local"]').first().fill(localInput(start))
        await page.locator('input[type="datetime-local"]').nth(1).fill(localInput(end))

        await page.getByRole('button', { name: 'Registrar', exact: true }).click()

        await expect(page.getByText('[E2E] Sin fruta en tolva')).toBeVisible({ timeout: 10_000 })

        const after = stoppageCount()
        expect(after - before, 'El paro debe existir en el servidor').toBe(1)

        // 2 h exactas: lo que la pantalla mostró es lo que la planta perdió.
        expect(latestStoppageValue('duration_minutes')).toBe('120')
    })

    test('26A-2: producción firma las horas del paro (A5)', async () => {
        await opsPush(page, '/app/paros')

        const row = page.locator('div').filter({ hasText: '[E2E] Sin fruta en tolva' }).last()
        await expect(row).toBeVisible({ timeout: 10_000 })

        // El paro nace sin firmar: mantenimiento declaró las horas, producción no las
        // ha visto todavía.
        await expect(page.getByText('Sin firmar').first()).toBeVisible()

        await page.getByRole('button', { name: 'Confirmar horas' }).first().click()

        await expect(page.getByText('Confirmado por producción').first()).toBeVisible({ timeout: 10_000 })
        expect(latestStoppageValue('confirmation_status')).toBe('confirmed')
    })

    test('26A-3: un paro firmado ya no se puede volver a firmar', async () => {
        await opsPush(page, '/app/paros')

        await expect(page.getByText('Confirmado por producción').first()).toBeVisible({ timeout: 10_000 })

        // La firma es un hecho fechado, no un campo editable: los botones desaparecen.
        await expect(page.getByRole('button', { name: 'Confirmar horas' })).toHaveCount(0)
        await expect(page.getByRole('button', { name: 'No estoy de acuerdo' })).toHaveCount(0)
    })

    test('26A-4: disputar un paro lo deja marcado sin borrar sus horas', async () => {
        await opsPush(page, '/app/paros')

        // Un segundo paro, este de equipo, para disputarlo.
        await page.getByRole('button', { name: /Registrar paro/i }).click()
        await page.locator('select').first().selectOption({ index: 1 }) // primer equipo
        await page.locator('select').filter({ hasText: 'Selecciona…' }).last().selectOption('mechanical')
        await page.getByPlaceholder('Ej.: atasco en prensa 2').fill('[E2E] Rodamiento del reductor')

        const start = new Date(Date.now() - 26 * 60 * 60 * 1000)
        const end = new Date(Date.now() - 25 * 60 * 60 * 1000)
        await page.locator('input[type="datetime-local"]').first().fill(localInput(start))
        await page.locator('input[type="datetime-local"]').nth(1).fill(localInput(end))
        await page.getByRole('button', { name: 'Registrar', exact: true }).click()

        await expect(page.getByText('[E2E] Rodamiento del reductor')).toBeVisible({ timeout: 10_000 })

        const paroId = latestStoppageValue('id')
        const minutesBefore = tinkerInt(
            `\\DB::table('equipment_downtime_events')->where('id','${paroId}')->value('duration_minutes')`,
        )

        await page.getByRole('button', { name: 'No estoy de acuerdo' }).first().click()
        await page.getByPlaceholder(/la línea reanudó/i).fill('[E2E] La prensa arrancó 20 minutos antes.')
        await page.getByRole('button', { name: 'Guardar', exact: true }).click()

        await expect(page.getByText('En disputa').first()).toBeVisible({ timeout: 10_000 })

        // El desacuerdo no borra las horas: el paro sigue contando hasta que las dos
        // áreas se sienten a mirarlo. Un paro en disputa que desaparece del informe es
        // exactamente la mentira que este campo existe para evitar.
        const minutesAfter = tinkerInt(
            `\\DB::table('equipment_downtime_events')->where('id','${paroId}')->value('duration_minutes')`,
        )
        expect(minutesAfter).toBe(minutesBefore)
        expect(stoppageValue(paroId, 'confirmation_status')).toBe('disputed')
    })

    test('26A-5: el paro solapado se rechaza con un mensaje, no con una pantalla rota', async () => {
        await opsPush(page, '/app/paros')

        const before = stoppageCount()

        // Las mismas horas del paro de planta de 26A-1: si esto entrara, la planta
        // perdería esas horas dos veces y la eficiencia del mes saldría inflada.
        await page.getByRole('button', { name: /Registrar paro/i }).click()
        await page.getByRole('button', { name: 'Toda la planta', exact: true }).click()
        await page.locator('select').filter({ hasText: 'Selecciona…' }).first().selectOption('utilities')

        const start = new Date(Date.now() - 3 * 60 * 60 * 1000)
        const end = new Date(Date.now() - 2.5 * 60 * 60 * 1000)
        await page.locator('input[type="datetime-local"]').first().fill(localInput(start))
        await page.locator('input[type="datetime-local"]').nth(1).fill(localInput(end))
        await page.getByRole('button', { name: 'Registrar', exact: true }).click()

        await expect(page.getByText(/se cruza con este|dos veces/i)).toBeVisible({ timeout: 10_000 })

        const after = stoppageCount()
        expect(after, 'El paro solapado no debe llegar a la base').toBe(before)
    })
})

// ══════════════════════════════════════════════════════════════════════════════
// Grupo 26B — Ronda de horómetros
// ══════════════════════════════════════════════════════════════════════════════

test.describe.serial('Grupo 26B — Ronda de horómetros', () => {
    let page

    test.beforeAll(async ({ browser }) => {
        page = await browser.newPage()
        await loginToApp(page)
    })

    test.afterAll(async () => {
        await page?.close()
    })

    /** La ronda con el listado acotado a los equipos del fixture. */
    async function openRound(filter = 'E2E-PRE') {
        await opsPush(page, '/app/horometros')

        await expect(page.getByRole('heading', { name: /Ronda de horómetros/i })).toBeVisible()
        await page.getByPlaceholder('Filtrar equipos…').fill(filter)
        await page.waitForTimeout(300)
    }

    test('26B-1: la ronda diaria guarda varias lecturas de una vez', async () => {
        await openRound()

        const inputs = page.locator('input[type="number"]')
        await expect(inputs.first()).toBeVisible({ timeout: 10_000 })

        // El operario recorre la planta y entra los diales de golpe: una lectura mala
        // no puede perder las otras 29.
        await inputs.nth(0).fill('1200')
        await inputs.nth(1).fill('850')

        await page.getByRole('button', { name: /Guardar ronda \(2\)/ }).click()
        await expect(page.getByText(/lectura\(s\) guardadas/i)).toBeVisible({ timeout: 15_000 })

        expect(readingsOf('E2E-PRE-001'), 'La lectura debe llegar al servidor').toBe(1)
        expect(readingsOf('E2E-PRE-002')).toBe(1)

        // La primera lectura de una máquina no dice cuánto trabajó: dice dónde está el
        // dial. El acumulado solo empieza a moverse con la segunda.
        expect(accumulatedOf('E2E-PRE-001')).toBe(0)
    })

    test('26B-2: un dial cambiado se registra como reset y el acumulado no retrocede', async () => {
        await openRound('E2E-PRE-001')

        const input = page.locator('input[type="number"]').first()
        await expect(input).toBeVisible({ timeout: 10_000 })

        // Segunda lectura: 300 horas de trabajo real desde la anterior.
        await input.fill('1500')
        await page.getByRole('button', { name: /Guardar ronda \(1\)/ }).click()
        await expect(page.getByText(/lectura\(s\) guardadas/i)).toBeVisible({ timeout: 15_000 })
        expect(accumulatedOf('E2E-PRE-001')).toBe(300)

        // El horómetro se rompió y le pusieron uno nuevo, que arranca casi en cero. La
        // máquina no viajó al pasado.
        await openRound('E2E-PRE-001')
        const swapped = page.locator('input[type="number"]').first()
        await swapped.fill('15')

        // La pantalla lo avisa antes de guardar, que es lo que evita el susto.
        await expect(page.getByText(/cambio de horómetro/i)).toBeVisible()

        await page.getByRole('button', { name: /Guardar ronda \(1\)/ }).click()
        await expect(page.getByText(/lectura\(s\) guardadas/i)).toBeVisible({ timeout: 15_000 })

        const resets = tinkerInt(`\\DB::table('equipment_meter_readings')->where('is_reset',true)->count()`)
        expect(resets, 'La lectura debe quedar marcada como cambio de dial').toBeGreaterThanOrEqual(1)

        // 300 h del dial viejo + 15 h del nuevo. El dial bajó; el acumulado no.
        expect(accumulatedOf('E2E-PRE-001'), 'El acumulado no puede retroceder al cambiar el dial').toBe(315)
    })
})

// ══════════════════════════════════════════════════════════════════════════════
// Grupo 26C — Checklist ejecutable en la PWA
// ══════════════════════════════════════════════════════════════════════════════

test.describe.serial('Grupo 26C — Checklist ejecutable (PWA)', () => {
    test.beforeAll(() => {
        if (!WO_CHECK_ID) throw new Error(`OT ${WO_CHECK} no encontrada — verificar E2EDataSeeder`)
    })

    test('26C-1: el técnico registra una medición y la ve guardada', async ({ page }) => {
        await loginMobile(page)
        await mobilePush(page, `/mobile/work-orders/${WO_CHECK_ID}`)

        await expect(page.getByText('Temperatura del reductor').first()).toBeVisible({ timeout: 15_000 })

        // Dentro del rango 40–80 °C.
        await page.locator('input[type="number"]').first().fill('65')
        await page.getByRole('button', { name: 'Guardar', exact: true }).first().click()

        await expect(page.getByText(/Registrado:/)).toBeVisible({ timeout: 10_000 })

        const value = tinkerFloat(
            `\\DB::table('work_order_checklist_results')->where('label','Temperatura del reductor')->value('value_numeric')`,
        )
        expect(value).toBe(65)
    })

    test('26C-2: un valor fuera de rango se avisa en la pantalla', async ({ page }) => {
        await loginMobile(page)
        await mobilePush(page, `/mobile/work-orders/${WO_CHECK_ID}`)

        await expect(page.getByText('Temperatura del reductor').first()).toBeVisible({ timeout: 15_000 })

        // 120 °C con un rango esperado de 40–80: el reductor se está cocinando, y el
        // técnico tiene que enterarse ahí mismo, no cuando alguien lea el reporte.
        await page.locator('input[type="number"]').first().fill('120')
        await page.getByRole('button', { name: 'Guardar', exact: true }).first().click()

        await expect(page.getByText('Fuera de rango')).toBeVisible({ timeout: 10_000 })
    })

    test('26C-3: la tarea no se cierra con una medición obligatoria en blanco', async ({ page }) => {
        await loginMobile(page)
        await mobilePush(page, `/mobile/work-orders/${WO_CHECK_ID}`)

        await expect(page.getByText('¿Hay fuga de aceite?').first()).toBeVisible({ timeout: 15_000 })

        // La medición booleana sigue sin responder. Marcar la tarea como hecha es
        // decir que se ejecutó un preventivo que nadie ejecutó.
        await page.getByRole('button', { name: 'Marcar como hecha' }).first().click()

        await expect(page.getByText(/obligatoria|sin responder|pendiente/i).first())
            .toBeVisible({ timeout: 10_000 })

        expect(taskStatus(), 'La tarea no puede quedar hecha con mediciones en blanco').toBe('pending')
    })

    test('26C-4: con todo respondido, la tarea se cierra', async ({ page }) => {
        await loginMobile(page)
        await mobilePush(page, `/mobile/work-orders/${WO_CHECK_ID}`)

        await expect(page.getByText('¿Hay fuga de aceite?').first()).toBeVisible({ timeout: 15_000 })

        // Responder la booleana que faltaba.
        await page.getByRole('button', { name: 'No', exact: true }).first().click()
        await page.waitForTimeout(1_000)

        await page.getByRole('button', { name: 'Marcar como hecha' }).first().click()

        await page.waitForFunction(
            () => !document.body.innerText.includes('Marcar como hecha'),
            { timeout: 10_000 },
        ).catch(() => {})

        expect(taskStatus()).toBe('done')
    })
})
