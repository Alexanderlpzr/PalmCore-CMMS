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
            // El recuadro de lectura se ajusta a la cámara, que en el celular es más chica.
            { fps: 10, qrbox: (ancho, alto) => { const lado = Math.floor(Math.min(ancho, alto) * 0.7); return { width: lado, height: lado } } },
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

/*
 * El aviso que se oye y se siente: el vigilante sabe si el carné pasó sin tener que mirar.
 * Pitido corto y agudo si marcó; largo y grave, con vibración, si no. Sin archivos de
 * sonido: lo genera el navegador.
 */
let audio = null

function pitar(ok) {
    try {
        audio ??= new (window.AudioContext || window.webkitAudioContext)()
        const oscilador = audio.createOscillator()
        const volumen = audio.createGain()
        const duracion = ok ? 0.15 : 0.6

        oscilador.type = 'sine'
        oscilador.frequency.value = ok ? 1200 : 330
        volumen.gain.setValueAtTime(0.25, audio.currentTime)
        volumen.gain.exponentialRampToValueAtTime(0.001, audio.currentTime + duracion)
        oscilador.connect(volumen).connect(audio.destination)
        oscilador.start()
        oscilador.stop(audio.currentTime + duracion)
    } catch (_) {}

    if (navigator.vibrate) navigator.vibrate(ok ? 80 : [250, 120, 250])
}

// En el computador el cursor queda en el campo, para el lector USB.
function enfocarCampo() {
    if (!window.matchMedia('(min-width: 640px)').matches) return

    document.getElementById('porteria-token')?.focus()
}

window.addEventListener('porteria-marca', (evento) => {
    pitar(Boolean(evento.detail?.ok))
    setTimeout(enfocarCampo, 50)
})

// El navegador solo deja sonar después de que alguien toca la pantalla: el primer toque lo habilita.
document.addEventListener('pointerdown', () => {
    try {
        audio ??= new (window.AudioContext || window.webkitAudioContext)()
        audio.resume?.()
    } catch (_) {}
}, { once: true })

async function detener() {
    if (!scanner) return

    try { await scanner.stop() } catch (_) {}
    try { scanner.clear() } catch (_) {}
    scanner = null
}

function arrancar() {
    iniciar()
    enfocarCampo()
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', arrancar)
} else {
    arrancar()
}

window.addEventListener('pagehide', detener)
