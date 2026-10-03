/*
 * La cámara de la pantalla «Portería» del panel.
 *
 * Se carga solo en esa pantalla. Lee el carné y le pasa el token al componente de
 * Livewire, que es quien registra la marca: aquí no se decide nada, solo se lee. La región
 * de la cámara va con `wire:ignore`, para que cada marca que repinta la pantalla no se
 * lleve el video por delante.
 */
import { Html5Qrcode } from 'html5-qrcode'

// El carné lleva un UUID v4 pelado: cualquier otro QR frente a la cámara se ignora.
const UUID_V4 = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i

let scanner = null
let ultimoToken = null
let ultimoEn = 0

function componente(region) {
    const raiz = region.closest('[wire\\:id]')

    return raiz && window.Livewire ? window.Livewire.find(raiz.getAttribute('wire:id')) : null
}

function mensajeDeCamara(e) {
    if (e?.name === 'NotAllowedError') return 'El navegador no dio permiso para la cámara. Use el campo de abajo o un lector USB.'
    if (e?.name === 'NotFoundError') return 'Este equipo no tiene cámara. Use el campo de abajo o un lector USB.'
    if (e?.name === 'NotReadableError') return 'Otra aplicación está usando la cámara.'

    return 'No se pudo encender la cámara. Use el campo de abajo o un lector USB.'
}

async function iniciar() {
    const region = document.getElementById('porteria-camara')

    if (!region || scanner) return

    const aviso = document.getElementById('porteria-camara-aviso')
    scanner = new Html5Qrcode('porteria-camara')

    try {
        await scanner.start(
            { facingMode: 'environment' },
            { fps: 10, qrbox: { width: 240, height: 240 } },
            async (texto) => {
                const token = texto.trim()

                if (!UUID_V4.test(token)) return

                // El servidor ya ignora el pase repetido; esto evita diez peticiones por
                // segundo mientras el carné siga frente a la cámara.
                const ahora = Date.now()
                if (token === ultimoToken && ahora - ultimoEn < 4000) return
                ultimoToken = token
                ultimoEn = ahora

                await componente(region)?.call('registrarMarca', token)
            },
            () => {},
        )
    } catch (e) {
        scanner = null
        region.hidden = true

        if (aviso) {
            aviso.textContent = mensajeDeCamara(e)
            aviso.hidden = false
        }
    }
}

async function detener() {
    if (!scanner) return

    try { await scanner.stop() } catch (_) {}
    try { scanner.clear() } catch (_) {}
    scanner = null
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', iniciar)
} else {
    iniciar()
}

window.addEventListener('pagehide', detener)
