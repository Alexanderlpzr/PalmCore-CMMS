<template>
    <div class="px-5 py-4 space-y-5">

        <!-- Attachment type selector -->
        <div class="space-y-1.5">
            <label class="block text-sm font-medium text-zinc-400">Tipo de foto</label>
            <div class="grid grid-cols-3 gap-2">
                <button
                    v-for="opt in typeOptions"
                    :key="opt.value"
                    type="button"
                    :disabled="!!status"
                    @click="selectedType = opt.value"
                    class="py-2.5 rounded-xl text-xs font-medium border transition"
                    :class="selectedType === opt.value
                        ? 'bg-amber-500/20 border-amber-500 text-amber-400'
                        : 'bg-zinc-800 border-zinc-700 text-zinc-400'"
                >
                    {{ opt.label }}
                </button>
            </div>
        </div>

        <!-- Caption -->
        <div class="space-y-1.5">
            <label class="block text-sm font-medium text-zinc-400">
                Descripción <span class="text-zinc-600">(opcional)</span>
            </label>
            <input
                v-model="caption"
                type="text"
                :disabled="!!status"
                placeholder="Ej: Panel eléctrico abierto"
                class="w-full bg-zinc-800 text-zinc-100 rounded-xl px-4 py-3 text-sm border border-zinc-700 focus:border-amber-500 focus:outline-none"
            />
        </div>

        <!-- Processing status -->
        <div v-if="status" class="bg-zinc-800 rounded-xl px-4 py-3">
            <p class="text-sm text-zinc-400">{{ status }}</p>
        </div>

        <p v-if="error" class="text-sm text-red-400">{{ error }}</p>

        <!-- Preview state -->
        <template v-if="previewUrl">
            <div class="space-y-3">
                <img
                    :src="previewUrl"
                    alt="Previsualización"
                    class="w-full rounded-2xl object-cover"
                    style="max-height: 320px;"
                />
                <div class="grid grid-cols-2 gap-3">
                    <button
                        type="button"
                        @click="discardPreview"
                        class="py-3.5 rounded-2xl font-semibold text-sm bg-zinc-700 text-zinc-300 active:scale-95 transition"
                    >
                        Volver a tomar
                    </button>
                    <button
                        type="button"
                        :disabled="!!status"
                        @click="uploadPreview"
                        class="py-3.5 rounded-2xl font-semibold text-sm transition"
                        :class="status
                            ? 'bg-zinc-700 text-zinc-500 cursor-not-allowed'
                            : 'bg-amber-500 text-zinc-900 active:scale-95'"
                    >
                        {{ status ?? 'Subir esta foto' }}
                    </button>
                </div>
            </div>
        </template>

        <!-- Idle / take photo state -->
        <template v-else>
            <!-- Hidden file input -->
            <input
                ref="fileInput"
                type="file"
                accept="image/*"
                capture="environment"
                class="hidden"
                @change="handleFile"
            />

            <button
                type="button"
                :disabled="!!status"
                @click="fileInput.click()"
                class="w-full py-4 rounded-2xl font-semibold text-base transition"
                :class="status
                    ? 'bg-zinc-700 text-zinc-500 cursor-not-allowed'
                    : 'bg-amber-500 text-zinc-900 active:scale-95'"
            >
                {{ status ?? 'Tomar foto' }}
            </button>
        </template>
    </div>
</template>

<script setup>
import { ref, computed, onBeforeUnmount } from 'vue'
import { usePendingActions } from '../composables/usePendingActions.js'
import { useGeolocation } from '../composables/useGeolocation.js'
import { useImageResize } from '../composables/useImageResize.js'
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
const { resizeImage } = useImageResize()
const { applyToPhoto } = useWatermark()
const toast = useToast()
const auth = useAuthStore()

const fileInput = ref(null)
const selectedType = ref('evidence')
const caption = ref('')
const status = ref(null)
const error = ref(null)

const watermarkedBlob = ref(null)
const capturedGps = ref(null)
let _previewObjectUrl = null

const previewUrl = computed(() => _previewObjectUrl)

const typeOptions = [
    { value: 'before_photo', label: 'Antes' },
    { value: 'after_photo', label: 'Después' },
    { value: 'evidence', label: 'Evidencia' },
]

onBeforeUnmount(() => {
    revokePreview()
})

function revokePreview() {
    if (_previewObjectUrl) {
        URL.revokeObjectURL(_previewObjectUrl)
        _previewObjectUrl = null
    }
}

function discardPreview() {
    revokePreview()
    watermarkedBlob.value = null
    capturedGps.value = null
    error.value = null
    // Reset file input so the same file can be re-selected
    if (fileInput.value) fileInput.value.value = ''
}

async function handleFile(event) {
    const file = event.target.files?.[0]
    if (!file) return

    error.value = null
    event.target.value = ''

    try {
        status.value = 'Procesando imagen…'
        const [resized, gps] = await Promise.all([
            resizeImage(file),
            geo.capture(),
        ])

        status.value = 'Aplicando marca de agua…'
        const techName = auth.userName ?? auth.userEmail ?? 'Técnico'
        const blob = await applyToPhoto(resized, {
            workOrderNumber: props.workOrderNumber,
            technicianName: techName,
            capturedAt: new Date(),
            gps,
        })

        watermarkedBlob.value = blob
        capturedGps.value = gps
        revokePreview()
        _previewObjectUrl = URL.createObjectURL(blob)
    } catch (e) {
        error.value = e.message
    } finally {
        status.value = null
    }
}

async function uploadPreview() {
    if (!watermarkedBlob.value) return

    error.value = null

    try {
        status.value = 'Guardando…'
        const result = await pendingActions.queueOrSubmitMedia(
            props.workOrderId,
            watermarkedBlob.value,
            selectedType.value,
            caption.value,
            capturedGps.value,
        )

        const locationTag = capturedGps.value ? ` · 📍 ±${Math.round(capturedGps.value.accuracy)}m` : ''
        toast[result.queued ? 'info' : 'success'](
            (result.queued ? 'Foto guardada localmente' : 'Foto subida correctamente') + locationTag,
        )

        revokePreview()
        watermarkedBlob.value = null
        capturedGps.value = null
        caption.value = ''
        emit('saved')
    } catch (e) {
        error.value = e.message
    } finally {
        status.value = null
    }
}
</script>
