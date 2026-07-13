<template>
    <div v-if="permits.length" class="bg-zinc-900 rounded-2xl p-4 space-y-3">
        <div class="flex items-center gap-2">
            <span class="text-lg">⚠️</span>
            <p class="text-xs font-medium text-zinc-400 uppercase tracking-wide">Permisos de trabajo</p>
        </div>

        <!-- Lo que el técnico necesita saber antes de tocar la máquina -->
        <div
            v-if="blocking"
            class="bg-red-500/10 border border-red-500/30 rounded-xl px-4 py-3"
        >
            <p class="text-sm font-semibold text-red-400">Este trabajo no puede iniciarse todavía</p>
            <p class="text-xs text-red-300/80 mt-1">
                Falta el permiso firmado y vigente. No inicies la OT ni intervengas el equipo.
            </p>
        </div>

        <div
            v-for="permit in permits"
            :key="permit.id"
            class="rounded-xl border p-4 space-y-3"
            :class="permit.authorizes_work_now
                ? 'border-emerald-500/30 bg-emerald-500/5'
                : 'border-zinc-700 bg-zinc-800'"
        >
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-zinc-100">{{ permit.permit_label }}</p>
                    <p class="text-xs text-zinc-400 mt-0.5">{{ permit.permit_number }}</p>
                </div>
                <span
                    class="shrink-0 px-2 py-1 rounded-full text-[10px] font-bold"
                    :class="permit.authorizes_work_now
                        ? 'bg-emerald-500/20 text-emerald-400'
                        : permit.is_expired
                            ? 'bg-red-500/20 text-red-400'
                            : 'bg-amber-500/20 text-amber-400'"
                >
                    {{ permit.is_expired ? 'VENCIDO' : permit.authorizes_work_now ? 'VIGENTE' : 'SIN FIRMAR' }}
                </span>
            </div>

            <div class="space-y-2">
                <div>
                    <p class="text-[10px] font-bold text-zinc-500 uppercase">Peligros</p>
                    <p class="text-sm text-zinc-300 leading-snug">{{ permit.hazards }}</p>
                </div>
                <div>
                    <p class="text-[10px] font-bold text-zinc-500 uppercase">Controles</p>
                    <p class="text-sm text-zinc-300 leading-snug">{{ permit.controls }}</p>
                </div>
                <!-- LOTO: los candados que tienen que estar puestos ANTES de entrar -->
                <div v-if="permit.isolation_points.length">
                    <p class="text-[10px] font-bold text-zinc-500 uppercase">Puntos de aislamiento</p>
                    <ul class="mt-1 space-y-1">
                        <li
                            v-for="(point, i) in permit.isolation_points"
                            :key="i"
                            class="text-sm text-zinc-300 flex gap-2"
                        >
                            <span class="text-amber-500">🔒</span>{{ point }}
                        </li>
                    </ul>
                </div>
                <p class="text-xs text-zinc-500">
                    Vigente hasta {{ formatDateTime(permit.valid_until) }}
                </p>
            </div>

            <!-- La firma. Aceptar un permiso es decir «me explicaron los riesgos». -->
            <div v-if="permit.status === 'issued' && !permit.is_expired">
                <button
                    v-if="network.isOnline"
                    @click="sign(permit)"
                    :disabled="signingId === permit.id"
                    class="w-full py-4 rounded-2xl font-semibold text-base transition active:scale-95"
                    :class="signingId === permit.id
                        ? 'bg-zinc-700 text-zinc-400'
                        : 'bg-amber-500 text-zinc-900'"
                >
                    {{ signingId === permit.id ? 'Firmando…' : 'Entiendo los riesgos y firmo' }}
                </button>

                <!-- Sin conexión NO se firma: una firma guardada en el teléfono no
                     protege a nadie. El servidor tiene que saber que este hombre va
                     a entrar, y saberlo antes de que entre. -->
                <div v-else class="bg-zinc-800 border border-zinc-700 rounded-xl px-4 py-3">
                    <p class="text-sm font-semibold text-zinc-300">Sin conexión no se puede firmar</p>
                    <p class="text-xs text-zinc-500 mt-1">
                        Un permiso de alto riesgo no se guarda para después: nadie más sabría que
                        estás adentro. Busca señal antes de intervenir el equipo.
                    </p>
                </div>
            </div>

            <p v-else-if="permit.accepted_by" class="text-xs text-emerald-400">
                Firmado por {{ permit.accepted_by }}
            </p>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useApi } from '../composables/useApi.js'
import { useNetworkStore } from '../stores/networkStore.js'
import { useToast } from '../composables/useToast.js'

const props = defineProps({
    workOrderId: { type: String, required: true },
})

const api = useApi()
const network = useNetworkStore()
const toast = useToast()

const permits = ref([])
const signingId = ref(null)

// Si algún permiso todavía no autoriza el trabajo, la OT no arranca — y el
// servidor lo va a rechazar igual. Decirlo aquí evita el viaje en falso.
const blocking = computed(() => permits.value.some(p => !p.authorizes_work_now && p.status !== 'closed'))

function formatDateTime(iso) {
    if (!iso) return '—'
    return new Intl.DateTimeFormat('es', {
        day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit',
    }).format(new Date(iso))
}

async function load() {
    try {
        const res = await api.get(`work-orders/${props.workOrderId}/permits`)
        permits.value = res?.data ?? []
    } catch {
        // Sin conexión no hay permisos que mostrar; el bloqueo del servidor sigue en pie.
    }
}

async function sign(permit) {
    signingId.value = permit.id
    try {
        await api.patch(`work-permits/${permit.id}/accept`)
        toast.success('Permiso firmado')
        await load()
    } catch (e) {
        toast.error(e.message ?? 'No se pudo firmar el permiso')
    } finally {
        signingId.value = null
    }
}

onMounted(load)
</script>
