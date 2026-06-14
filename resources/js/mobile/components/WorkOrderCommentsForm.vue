<template>
    <form @submit.prevent="submit" class="px-5 py-4 space-y-5">

        <!-- Body -->
        <div class="space-y-1.5">
            <label class="block text-sm font-medium text-zinc-400">Comentario</label>
            <textarea
                v-model="form.body"
                rows="4"
                required
                placeholder="Escribí tu comentario…"
                class="w-full bg-zinc-800 text-zinc-100 rounded-xl px-4 py-3 text-sm border border-zinc-700 focus:border-amber-500 focus:outline-none resize-none"
            />
        </div>

        <!-- Internal toggle -->
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-zinc-300">Nota interna</p>
                <p class="text-xs text-zinc-600">Solo visible para el equipo</p>
            </div>
            <button
                type="button"
                @click="form.is_internal = !form.is_internal"
                class="relative inline-flex h-7 w-12 items-center rounded-full transition"
                :class="form.is_internal ? 'bg-amber-500' : 'bg-zinc-700'"
                :aria-checked="form.is_internal"
                role="switch"
            >
                <span
                    class="inline-block h-5 w-5 rounded-full bg-white shadow transition-transform"
                    :class="form.is_internal ? 'translate-x-6' : 'translate-x-1'"
                />
            </button>
        </div>

        <p v-if="pendingActions.error.value" class="text-sm text-red-400">
            {{ pendingActions.error.value }}
        </p>

        <button
            type="submit"
            :disabled="submitting"
            class="w-full py-4 rounded-2xl font-semibold text-base transition"
            :class="submitting
                ? 'bg-zinc-700 text-zinc-500 cursor-not-allowed'
                : 'bg-amber-500 text-zinc-900 active:scale-95'"
        >
            {{ submitting ? 'Guardando…' : 'Agregar comentario' }}
        </button>
    </form>
</template>

<script setup>
import { ref } from 'vue'
import { usePendingActions } from '../composables/usePendingActions.js'
import { useGeolocation } from '../composables/useGeolocation.js'
import { useToast } from '../composables/useToast.js'

const props = defineProps({
    workOrderId: { type: String, required: true },
})

const emit = defineEmits(['saved'])

const pendingActions = usePendingActions()
const geo = useGeolocation()
const toast = useToast()

const form = ref({ body: '', is_internal: false })
const submitting = ref(false)

async function submit() {
    submitting.value = true
    try {
        const gps = await geo.capture()
        const result = await pendingActions.queueOrSubmitComment(
            props.workOrderId,
            form.value.body,
            form.value.is_internal,
            gps,
        )
        const locationTag = gps ? ` · 📍 ±${Math.round(gps.accuracy)}m` : ''
        toast[result.queued ? 'info' : 'success'](
            (result.queued ? 'Guardado localmente' : 'Comentario agregado') + locationTag,
        )
        form.value = { body: '', is_internal: false }
        emit('saved')
    } catch {
        // error displayed via pendingActions.error
    } finally {
        submitting.value = false
    }
}
</script>
