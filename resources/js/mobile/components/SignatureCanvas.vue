<template>
    <div class="px-5 py-4 space-y-4">

        <!-- Canvas -->
        <div class="space-y-1.5">
            <div class="flex justify-between items-center">
                <label class="text-sm font-medium text-zinc-400">Firma del técnico</label>
                <button
                    type="button"
                    @click="clearPad"
                    class="text-xs text-zinc-400 hover:text-zinc-300 transition"
                >
                    Limpiar
                </button>
            </div>
            <div
                class="bg-zinc-800 rounded-2xl border border-zinc-700 overflow-hidden"
                style="height: 200px;"
            >
                <canvas
                    ref="canvasRef"
                    class="w-full h-full touch-none"
                    style="cursor: crosshair"
                />
            </div>
            <p class="text-xs text-zinc-400 text-center">Dibujá tu firma con el dedo</p>
        </div>

        <!-- Notes -->
        <div class="space-y-1.5">
            <label class="block text-sm font-medium text-zinc-400">
                Notas <span class="text-zinc-400">(opcional)</span>
            </label>
            <input
                v-model="notes"
                type="text"
                placeholder="Observaciones adicionales…"
                class="w-full bg-zinc-800 text-zinc-100 rounded-xl px-4 py-3 text-sm border border-zinc-700 focus:border-amber-500 focus:outline-none"
            />
        </div>

        <p v-if="status" class="text-sm text-zinc-400">{{ status }}</p>
        <p v-if="error" class="text-sm text-red-400">{{ error }}</p>

        <button
            type="button"
            :disabled="!!status"
            @click="save"
            class="w-full py-4 rounded-2xl font-semibold text-base transition"
            :class="status
                ? 'bg-zinc-700 text-zinc-400 cursor-not-allowed'
                : 'bg-amber-500 text-zinc-900 active:scale-95'"
        >
            {{ status ?? 'Guardar firma' }}
        </button>
    </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import SignaturePad from 'signature_pad'
import { usePendingActions } from '../composables/usePendingActions.js'
import { useGeolocation } from '../composables/useGeolocation.js'
import { useWatermark } from '../composables/useWatermark.js'
import { useToast } from '../composables/useToast.js'
import { useAuthStore } from '../stores/auth.js'

const props = defineProps({
    workOrderId:     { type: String, required: true },
    workOrderNumber: { type: String, required: true },
})

const emit = defineEmits(['saved'])

const pendingActions = usePendingActions()
const geo = useGeolocation()
const { applyToSignature } = useWatermark()
const toast = useToast()
const auth = useAuthStore()

const canvasRef = ref(null)
const notes = ref('')
const status = ref(null)
const error = ref(null)

let pad = null

onMounted(() => {
    const canvas = canvasRef.value
    const ratio = Math.max(window.devicePixelRatio || 1, 1)
    canvas.width = canvas.offsetWidth * ratio
    canvas.height = canvas.offsetHeight * ratio
    canvas.getContext('2d').scale(ratio, ratio)
    pad = new SignaturePad(canvas, {
        backgroundColor: 'rgb(39, 39, 42)',
        penColor: 'rgb(244, 244, 245)',
    })
})

onBeforeUnmount(() => {
    pad?.off()
})

function clearPad() {
    pad?.clear()
}

async function save() {
    if (!pad || pad.isEmpty()) {
        error.value = 'Dibujá tu firma antes de guardar'
        return
    }

    error.value = null
    status.value = 'Obteniendo ubicación…'

    try {
        const [blob, gps] = await Promise.all([
            new Promise((resolve, reject) => {
                canvasRef.value.toBlob(
                    b => (b ? resolve(b) : reject(new Error('Error al generar imagen de firma'))),
                    'image/png',
                )
            }),
            geo.capture(),
        ])

        status.value = 'Aplicando marca de agua…'
        const techName = auth.userName ?? auth.userEmail ?? 'Técnico'
        const watermarked = await applyToSignature(blob, {
            workOrderNumber: props.workOrderNumber,
            technicianName: techName,
            signedAt: new Date(),
            gps,
        })

        status.value = 'Guardando firma…'

        const result = await pendingActions.queueOrSubmitSignature(
            props.workOrderId,
            watermarked,
            'technician_completion',
            notes.value,
            gps,
        )

        const locationTag = gps ? ` · 📍 ±${Math.round(gps.accuracy)}m` : ''
        toast[result.queued ? 'info' : 'success'](
            (result.queued ? 'Firma guardada localmente' : 'Firma guardada') + locationTag,
        )
        emit('saved')
    } catch (e) {
        error.value = e.message
    } finally {
        status.value = null
    }
}
</script>
