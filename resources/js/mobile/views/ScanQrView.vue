<template>
    <AppLayout title="Escanear QR" show-back>
        <div class="flex flex-col items-center px-4 py-6 space-y-6">

            <!-- Scanner region (always rendered so html5-qrcode has an element at mount time) -->
            <div class="w-full max-w-sm" :class="{ hidden: !!cameraError }">
                <div
                    id="qr-scanner-region"
                    class="rounded-2xl overflow-hidden bg-zinc-900 w-full"
                    style="min-height: 300px;"
                />
                <p class="text-sm text-zinc-400 text-center mt-3">
                    Apunta al código QR del equipo
                </p>
            </div>

            <!-- Camera error state -->
            <div
                v-if="cameraError"
                class="w-full max-w-sm bg-zinc-900 rounded-2xl p-6 space-y-3 text-center"
            >
                <div class="w-14 h-14 mx-auto bg-red-500/10 rounded-xl flex items-center justify-center">
                    <svg class="w-7 h-7 text-red-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z"/>
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z"/>
                    </svg>
                </div>
                <p class="text-sm text-zinc-300 font-medium">{{ cameraError }}</p>
                <p class="text-xs text-zinc-400">Ingresa el token del QR manualmente</p>
            </div>

            <!-- Manual token fallback -->
            <div v-if="cameraError" class="w-full max-w-sm space-y-3">
                <div class="space-y-1.5">
                    <label class="block text-sm font-medium text-zinc-400">Token del QR</label>
                    <input
                        v-model="manualToken"
                        type="text"
                        placeholder="xxxxxxxx-xxxx-4xxx-xxxx-xxxxxxxxxxxx"
                        class="w-full bg-zinc-800 text-zinc-100 rounded-xl px-4 py-3 text-sm border border-zinc-700 focus:border-amber-500 focus:outline-none font-mono"
                        @keyup.enter="handleManualLookup"
                    />
                </div>
                <p v-if="lookupError" class="text-sm text-red-400">{{ lookupError }}</p>
                <button
                    type="button"
                    :disabled="searching"
                    @click="handleManualLookup"
                    class="w-full py-4 rounded-2xl font-semibold text-base transition"
                    :class="searching
                        ? 'bg-zinc-700 text-zinc-400 cursor-not-allowed'
                        : 'bg-amber-500 text-zinc-900 active:scale-95'"
                >
                    {{ searching ? 'Buscando…' : 'Buscar equipo' }}
                </button>
            </div>

            <!-- Inline error for QR lookup (while camera active) -->
            <p v-if="!cameraError && lookupError" class="text-sm text-red-400 text-center">
                {{ lookupError }}
            </p>

        </div>
    </AppLayout>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import { Html5Qrcode } from 'html5-qrcode'
import AppLayout from '../components/AppLayout.vue'
import { useEquipmentStore } from '../stores/equipment.js'
import { useToast } from '../composables/useToast.js'

const router = useRouter()
const equipmentStore = useEquipmentStore()
const toast = useToast()

const cameraError = ref(null)
const manualToken = ref('')
const searching = ref(false)
const lookupError = ref(null)

// Fronda QR tokens are UUID v4
const UUID_V4 = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i

let scanner = null
let navigating = false

onMounted(async () => {
    try {
        scanner = new Html5Qrcode('qr-scanner-region')
        await scanner.start(
            { facingMode: 'environment' },
            { fps: 10, qrbox: { width: 250, height: 250 } },
            onScanSuccess,
            () => {}, // per-frame decode errors are expected — ignore
        )
    } catch (e) {
        cameraError.value = getCameraErrorMessage(e)
    }
})

onBeforeUnmount(async () => {
    if (scanner) {
        try { await scanner.stop() } catch (_) {}
        try { scanner.clear() } catch (_) {}
    }
})

function getCameraErrorMessage(e) {
    if (e?.name === 'NotAllowedError') return 'Permiso de cámara denegado'
    if (e?.name === 'NotFoundError') return 'No se encontró cámara trasera'
    if (e?.name === 'NotReadableError') return 'La cámara está en uso por otra app'
    return 'No se pudo iniciar la cámara'
}

async function onScanSuccess(decodedText) {
    if (navigating) return

    const token = decodedText.trim()
    if (!UUID_V4.test(token)) {
        toast.info('QR no reconocido como Fronda')
        return
    }

    navigating = true
    try {
        await scanner.stop()
    } catch (_) {}

    await lookupAndNavigate(token)
}

async function handleManualLookup() {
    lookupError.value = null
    const token = manualToken.value.trim()

    if (!UUID_V4.test(token)) {
        lookupError.value = 'Formato de token inválido (debe ser un UUID)'
        return
    }

    await lookupAndNavigate(token)
}

async function lookupAndNavigate(token) {
    searching.value = true
    lookupError.value = null

    await equipmentStore.fetchByQrToken(token)

    if (equipmentStore.error) {
        const msg = equipmentStore.error
        lookupError.value = msg.includes('404') || msg.includes('No query results')
            ? 'Equipo no encontrado en este tenant'
            : msg
        searching.value = false
        navigating = false
        return
    }

    router.push({ name: 'equipment-detail', params: { id: equipmentStore.currentItem.id } })
}
</script>
