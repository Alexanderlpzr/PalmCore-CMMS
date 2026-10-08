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

/*
 * html5-qrcode no entrega el error del navegador sino un texto que lo envuelve
 * («Error getting userMedia, error = NotAllowedError: …»), así que se busca el nombre
 * dentro del texto y no solo en `e.name`.
 */
function causa(e) {
    const texto = `${e?.name ?? ''} ${e?.message ?? ''} ${typeof e === 'string' ? e : ''}`

    if (/NotAllowed|Permission|denied/i.test(texto)) return 'permiso'
    if (/NotFound|DevicesNotFound|no camera/i.test(texto)) return 'sin-camara'
    if (/NotReadable|TrackStart|Could not start/i.test(texto)) return 'ocupada'
    if (/secure|https/i.test(texto)) return 'inseguro'

    return 'otra'
}

function mensajeDeCamara(e) {
    switch (causa(e)) {
        case 'permiso': return 'El navegador no dio permiso para la cámara. Toque el candado junto a la dirección → Permisos → Cámara → Permitir, y luego «Reintentar cámara».'
        case 'sin-camara': return 'Este equipo no tiene cámara. Use «Escribir código» o un lector USB.'
        case 'ocupada': return 'Otra aplicación está usando la cámara. Ciérrela y toque «Reintentar cámara».'
        case 'inseguro': return 'La cámara solo funciona entrando por https://fronda.app.'
        default: return 'No se pudo encender la cámara. Toque «Reintentar cámara»; si sigue igual, use «Escribir código».'
    }
}

async function iniciar() {
    const region = document.getElementById('porteria-camara')

    if (!region || scanner) return

    const aviso = document.getElementById('porteria-camara-aviso')
    const reintentar = document.getElementById('porteria-camara-reintentar')
    region.hidden = false
    if (aviso) aviso.hidden = true
    if (reintentar) reintentar.hidden = true

    // El recuadro de lectura se ajusta a la cámara, que en el celular es más chica. Algunos
    // Android informan alto 0 al arrancar el video: entonces se mide por el ancho, y nunca
    // por debajo del mínimo de 50 px de la librería, que si no aborta el arranque.
    const config = {
        fps: 10,
        qrbox: (ancho, alto) => {
            const lado = Math.max(60, Math.floor(Math.min(ancho, alto > 0 ? alto : ancho) * 0.7))

            return { width: lado, height: lado }
        },
    }

    const alLeer = async (texto) => {
        const token = texto.trim()

        if (!UUID_V4.test(token)) return

        // El servidor ya ignora el pase repetido; esto evita diez peticiones por
        // segundo mientras el carné siga frente a la cámara.
        const ahora = Date.now()
        if (token === ultimoToken && ahora - ultimoEn < 4000) return
        ultimoToken = token
        ultimoEn = ahora

        await componente(region)?.call('registrarMarca', token)
    }

    try {
        scanner = new Html5Qrcode('porteria-camara')

        try {
            await scanner.start({ facingMode: 'environment' }, config, alLeer, () => {})
        } catch (primero) {
            // Sin permiso no hay segundo intento que valga. Con otro fallo se prueba la
            // lista de cámaras: hay equipos que no entienden «la de atrás» pero sí su id.
            if (causa(primero) === 'permiso') throw primero

            const camaras = await Html5Qrcode.getCameras()
            if (!camaras?.length) throw primero

            const trasera = camaras.find((c) => /back|rear|trasera|environment/i.test(c.label)) ?? camaras[camaras.length - 1]
            try { scanner.clear() } catch (_) {}
            await scanner.start(trasera.id, config, alLeer, () => {})
        }
    } catch (e) {
        console.error('Portería: la cámara no encendió', e)
        try { scanner?.clear() } catch (_) {}
        scanner = null
        region.hidden = true

        if (aviso) {
            // El error técnico va debajo, en chico: con él se sabe qué pasó en ese equipo.
            const detalle = String(e?.name ? `${e.name}: ${e.message}` : e).slice(0, 160)
            aviso.textContent = mensajeDeCamara(e)
            const pie = document.createElement('small')
            pie.className = 'mt-2 block font-mono text-xs opacity-70'
            pie.textContent = `Detalle: ${detalle}`
            aviso.appendChild(pie)
            aviso.hidden = false
        }

        if (reintentar && causa(e) !== 'sin-camara') reintentar.hidden = false
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

// El botón que aparece cuando la cámara no encendió: el toque también sirve de gesto del
// usuario, que algunos navegadores piden para volver a preguntar el permiso.
document.addEventListener('click', (evento) => {
    if (evento.target.closest('#porteria-camara-reintentar')) iniciar()
})
