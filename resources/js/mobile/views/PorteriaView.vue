<template>
    <AppLayout title="Portería" show-back>
        <div class="flex flex-col px-4 py-4 space-y-4">

            <!--
                A diferencia del escáner de equipos, aquí no se navega a ninguna parte: en
                el cambio de turno pasan cuarenta personas en diez minutos, y obligar al
                vigilante a volver atrás entre cada una haría el sistema inservible. La
                cámara sigue viva y el resultado aparece encima.
            -->
            <div class="w-full" :class="{ hidden: !!cameraError }">
                <div
                    id="porteria-scanner-region"
                    class="rounded-2xl overflow-hidden bg-zinc-900 w-full"
                    style="min-height: 260px;"
                />
            </div>

            <!-- Resultado del último escaneo: grande, porque se lee de reojo y con lluvia -->
            <div
                v-if="ultimo"
                class="rounded-2xl p-5 space-y-1 border"
                :class="ultimo.direction === 'entrada'
                    ? 'bg-emerald-500/10 border-emerald-500/40'
                    : 'bg-sky-500/10 border-sky-500/40'"
            >
                <p
                    class="text-xs font-semibold uppercase tracking-wider"
                    :class="ultimo.direction === 'entrada' ? 'text-emerald-400' : 'text-sky-400'"
                >
                    {{ ultimo.direction_label }} · {{ formatoHora(ultimo.scanned_at) }}
                </p>
                <p class="text-xl font-bold text-zinc-100 leading-tight">
                    {{ ultimo.employee.full_name }}
                </p>
                <p class="text-sm text-zinc-400">
                    {{ ultimo.employee.document_number }}
                    <span v-if="ultimo.employee.position"> · {{ ultimo.employee.position }}</span>
                </p>
                <p v-if="ultimo.notice" class="text-sm text-amber-400 pt-1">
                    {{ ultimo.notice }}
                </p>
            </div>

            <p v-if="errorMarca" class="rounded-2xl bg-red-500/10 border border-red-500/40 p-4 text-sm text-red-300">
                {{ errorMarca }}
            </p>

            <!-- Cámara caída: la planta no se detiene por eso -->
            <div v-if="cameraError" class="space-y-3">
                <p class="text-sm text-zinc-300 font-medium">{{ cameraError }}</p>
                <input
                    v-model="tokenManual"
                    type="text"
                    placeholder="Token del carné"
                    class="w-full bg-zinc-800 text-zinc-100 rounded-xl px-4 py-3 text-sm border border-zinc-700 focus:border-emerald-500 focus:outline-none font-mono"
                    @keyup.enter="marcarManual"
                />
                <button
                    type="button"
                    :disabled="enviando"
                    class="w-full py-4 rounded-2xl font-semibold text-base transition"
                    :class="enviando
                        ? 'bg-zinc-700 text-zinc-400 cursor-not-allowed'
                        : 'bg-emerald-500 text-zinc-900 active:scale-95'"
                    @click="marcarManual"
                >
                    {{ enviando ? 'Registrando…' : 'Registrar marca' }}
                </button>
            </div>

            <!-- Lo del día, para confirmar que quedó registrado -->
            <div v-if="delDia.length" class="space-y-2">
                <p class="text-xs font-semibold uppercase tracking-wider text-zinc-500">
                    Hoy · {{ delDia.length }} marcas
                </p>
                <div
                    v-for="marca in delDia"
                    :key="marca.scan_id"
                    class="flex items-center justify-between bg-zinc-900 rounded-xl px-4 py-3"
                >
                    <div class="min-w-0">
                        <p class="text-sm text-zinc-100 truncate">{{ marca.employee_name }}</p>
                        <p class="text-xs text-zinc-500">{{ marca.document_number }}</p>
                    </div>
                    <div class="text-right shrink-0 pl-3">
                        <p
                            class="text-xs font-semibold"
                            :class="marca.direction === 'entrada' ? 'text-emerald-400' : 'text-sky-400'"
                        >
                            {{ marca.direction_label }}
                        </p>
                        <p class="text-xs text-zinc-500 tabular-nums">{{ formatoHora(marca.scanned_at) }}</p>
                    </div>
                </div>
            </div>

        </div>
    </AppLayout>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { Html5Qrcode } from 'html5-qrcode'
import AppLayout from '../components/AppLayout.vue'
import { useApi } from '../composables/useApi.js'

const api = useApi()

const cameraError = ref(null)
const tokenManual = ref('')
const enviando = ref(false)
const errorMarca = ref(null)
const ultimo = ref(null)
const delDia = ref([])

// El carné lleva un UUID v4 pelado, no una URL: si alguien lo fotografía en la puerta,
// no abre nada.
const UUID_V4 = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i

let scanner = null

/*
 * El servidor ya ignora los pases repetidos del mismo carné, pero sin esta guarda el
 * lector dispara diez peticiones por segundo mientras el carné siga frente a la cámara.
 * Es una cortesía con la red de la planta, no la regla de negocio.
 */
let ultimoTokenLeido = null
let ultimoLeidoEn = 0

onMounted(async () => {
    await cargarDelDia()

    try {
        scanner = new Html5Qrcode('porteria-scanner-region')
        await scanner.start(
            { facingMode: 'environment' },
            { fps: 10, qrbox: { width: 240, height: 240 } },
            alLeer,
            () => {},
        )
    } catch (e) {
        cameraError.value = mensajeDeCamara(e)
    }
})

onBeforeUnmount(async () => {
    if (scanner) {
        try { await scanner.stop() } catch (_) {}
        try { scanner.clear() } catch (_) {}
    }
})

function mensajeDeCamara(e) {
    if (e?.name === 'NotAllowedError') return 'Permiso de cámara denegado. Registra el token a mano.'
    if (e?.name === 'NotFoundError') return 'No se encontró cámara trasera.'
    if (e?.name === 'NotReadableError') return 'La cámara está en uso por otra aplicación.'
    return 'No se pudo iniciar la cámara.'
}

async function alLeer(texto) {
    const token = texto.trim()

    if (!UUID_V4.test(token)) return

    const ahora = Date.now()
    if (token === ultimoTokenLeido && ahora - ultimoLeidoEn < 4000) return

    ultimoTokenLeido = token
    ultimoLeidoEn = ahora

    await marcar(token)
}

async function marcarManual() {
    const token = tokenManual.value.trim()

    if (!UUID_V4.test(token)) {
        errorMarca.value = 'Ese no es un token de carné válido.'
        return
    }

    await marcar(token, 'manual')
    tokenManual.value = ''
}

async function marcar(token, source = 'qr') {
    enviando.value = true
    errorMarca.value = null

    try {
        const respuesta = await api.post('attendance/scan', { qr_token: token, source })
        ultimo.value = respuesta.data
        await cargarDelDia()
    } catch (e) {
        ultimo.value = null
        errorMarca.value = e.message
    } finally {
        enviando.value = false
    }
}

async function cargarDelDia() {
    try {
        const respuesta = await api.get('attendance/scans')
        delDia.value = respuesta.data
    } catch (_) {
        // La lista es una confirmación, no la función: si falla, marcar sigue sirviendo.
    }
}

function formatoHora(iso) {
    return new Date(iso).toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit' })
}
</script>
