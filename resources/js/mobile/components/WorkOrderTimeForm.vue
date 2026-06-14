<template>
    <form @submit.prevent="submit" class="px-5 py-4 space-y-5">

        <!-- Started at -->
        <div class="space-y-1.5">
            <label class="block text-sm font-medium text-zinc-400">Inicio</label>
            <input
                v-model="form.started_at"
                type="datetime-local"
                required
                class="w-full bg-zinc-800 text-zinc-100 rounded-xl px-4 py-3 text-sm border border-zinc-700 focus:border-amber-500 focus:outline-none"
            />
        </div>

        <!-- Ended at -->
        <div class="space-y-1.5">
            <label class="block text-sm font-medium text-zinc-400">
                Fin <span class="text-zinc-600">(opcional)</span>
            </label>
            <input
                v-model="form.ended_at"
                type="datetime-local"
                class="w-full bg-zinc-800 text-zinc-100 rounded-xl px-4 py-3 text-sm border border-zinc-700 focus:border-amber-500 focus:outline-none"
            />
        </div>

        <!-- Calculated duration -->
        <div v-if="duration" class="bg-amber-500/10 rounded-xl px-4 py-3">
            <p class="text-sm text-amber-400">Duración: <strong>{{ duration }}</strong></p>
        </div>

        <!-- Description -->
        <div class="space-y-1.5">
            <label class="block text-sm font-medium text-zinc-400">
                Descripción <span class="text-zinc-600">(opcional)</span>
            </label>
            <textarea
                v-model="form.description"
                rows="3"
                placeholder="Describí el trabajo realizado…"
                class="w-full bg-zinc-800 text-zinc-100 rounded-xl px-4 py-3 text-sm border border-zinc-700 focus:border-amber-500 focus:outline-none resize-none"
            />
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
            {{ submitting ? 'Guardando…' : 'Registrar tiempo' }}
        </button>
    </form>
</template>

<script setup>
import { ref, computed } from 'vue'
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

const form = ref({ started_at: '', ended_at: '', description: '' })
const submitting = ref(false)

const duration = computed(() => {
    if (!form.value.started_at || !form.value.ended_at) return null
    const diff = new Date(form.value.ended_at) - new Date(form.value.started_at)
    if (diff <= 0) return null
    const h = Math.floor(diff / 3_600_000)
    const m = Math.floor((diff % 3_600_000) / 60_000)
    return h > 0 ? `${h}h ${m}min` : `${m}min`
})

async function submit() {
    submitting.value = true
    try {
        const gps = await geo.capture()

        const payload = {
            started_at: form.value.started_at
                ? new Date(form.value.started_at).toISOString()
                : undefined,
            ended_at: form.value.ended_at
                ? new Date(form.value.ended_at).toISOString()
                : undefined,
            description: form.value.description || undefined,
        }

        const result = await pendingActions.queueOrSubmitTimeEntry(props.workOrderId, payload, gps)

        const locationTag = gps ? ` · 📍 ±${Math.round(gps.accuracy)}m` : ''
        toast[result.queued ? 'info' : 'success'](
            (result.queued ? 'Guardado localmente' : 'Tiempo registrado') + locationTag,
        )
        form.value = { started_at: '', ended_at: '', description: '' }
        emit('saved')
    } catch {
        // error displayed via pendingActions.error
    } finally {
        submitting.value = false
    }
}
</script>
