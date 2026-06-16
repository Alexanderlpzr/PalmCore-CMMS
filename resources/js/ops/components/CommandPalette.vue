<template>
    <Teleport to="body">
        <Transition name="cmd">
            <div
                v-if="isOpen"
                class="fixed inset-0 z-[100] flex items-start justify-center p-4 pt-[12vh]"
                @keydown="onKeydown"
            >
                <!-- Backdrop -->
                <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" @click="close" />

                <!-- Panel -->
                <div
                    role="dialog"
                    aria-modal="true"
                    aria-label="Búsqueda global"
                    class="relative w-full max-w-xl bg-white rounded-2xl shadow-2xl border border-gray-100 overflow-hidden flex flex-col max-h-[70vh]"
                >
                    <!-- Search input -->
                    <div class="flex items-center gap-3 px-4 border-b border-gray-100 shrink-0">
                        <AppIcon name="search" class="w-5 h-5 text-gray-400 shrink-0" />
                        <input
                            ref="inputRef"
                            v-model="query"
                            type="text"
                            role="combobox"
                            aria-expanded="true"
                            aria-controls="cmd-listbox"
                            :aria-activedescendant="activeId"
                            placeholder="Buscar equipos, OT, solicitudes, repuestos…"
                            class="flex-1 py-4 text-sm text-gray-900 placeholder-gray-400 bg-transparent focus:outline-none"
                        />
                        <kbd class="shrink-0 text-[11px] font-semibold text-gray-400 bg-gray-100 rounded px-1.5 py-0.5">Esc</kbd>
                    </div>

                    <!-- Results -->
                    <div id="cmd-listbox" role="listbox" class="flex-1 overflow-y-auto py-2">

                        <!-- Prompt -->
                        <div v-if="query.trim().length < MIN_LENGTH" class="px-4 py-10 text-center">
                            <p class="text-sm text-gray-500">Empieza a escribir para buscar…</p>
                            <p class="text-xs text-gray-400 mt-1">Equipos · Órdenes de trabajo · Solicitudes · Repuestos · Preventivos</p>
                        </div>

                        <!-- Loading skeleton -->
                        <div v-else-if="loading" class="px-2 space-y-1">
                            <div v-for="i in 5" :key="i" class="flex items-center gap-3 px-2 py-2.5">
                                <div class="skeleton w-8 h-8 rounded-lg shrink-0" />
                                <div class="flex-1 space-y-1.5">
                                    <div class="skeleton h-3 w-1/2 rounded" />
                                    <div class="skeleton h-2.5 w-1/4 rounded" />
                                </div>
                            </div>
                        </div>

                        <!-- Empty -->
                        <div v-else-if="!groups.length" class="px-4 py-10 text-center">
                            <p class="text-sm text-gray-500">No se encontraron resultados.</p>
                            <p class="text-xs text-gray-400 mt-1">Prueba con otro término.</p>
                        </div>

                        <!-- Grouped results -->
                        <template v-else>
                            <div v-for="group in groups" :key="group.type" class="mb-1">
                                <p class="px-4 pt-2 pb-1 text-[11px] font-semibold uppercase tracking-wider text-gray-400">
                                    {{ group.label }}
                                </p>
                                <button
                                    v-for="item in group.items"
                                    :id="`cmd-opt-${item._index}`"
                                    :key="item.id"
                                    role="option"
                                    :aria-selected="item._index === activeIndex"
                                    type="button"
                                    class="w-full flex items-center gap-3 px-4 py-2.5 text-left transition-colors"
                                    :class="item._index === activeIndex ? 'bg-emerald-50' : 'hover:bg-gray-50'"
                                    @click="activate(item)"
                                    @mousemove="activeIndex = item._index"
                                >
                                    <div class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center shrink-0">
                                        <AppIcon :name="meta(group.type).icon" class="w-4 h-4 text-gray-500" />
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900 truncate">{{ item.title }}</p>
                                        <p v-if="item.subtitle" class="text-xs text-gray-400 font-mono truncate">{{ item.subtitle }}</p>
                                    </div>
                                    <Badge
                                        v-if="item.status && meta(group.type).vocab"
                                        :tone="statusOf(group.type, item.status).tone"
                                        :label="statusOf(group.type, item.status).label"
                                        class="shrink-0"
                                    />
                                </button>
                            </div>
                        </template>
                    </div>

                    <!-- Footer hints -->
                    <div class="flex items-center gap-4 px-4 py-2 border-t border-gray-100 text-[11px] text-gray-400 shrink-0">
                        <span><kbd class="font-semibold">↑</kbd> <kbd class="font-semibold">↓</kbd> navegar</span>
                        <span><kbd class="font-semibold">↵</kbd> abrir</span>
                        <span><kbd class="font-semibold">Esc</kbd> cerrar</span>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import { useApi } from '../composables/useApi.js'
import { useCommandPalette } from '../composables/useCommandPalette.js'
import { describe, WORK_ORDER_STATUS, MAINTENANCE_REQUEST_STATUS, EQUIPMENT_STATUS } from '../../shared/design.js'
import AppIcon from './AppIcon.vue'
import Badge from './Badge.vue'

const MIN_LENGTH = 2
const DEBOUNCE_MS = 250

const api = useApi()
const router = useRouter()
const { isOpen, close } = useCommandPalette()

const query = ref('')
const loading = ref(false)
const groups = ref([])
const activeIndex = ref(0)
const inputRef = ref(null)

let debounceTimer = null
let controller = null

const GROUP_META = {
    equipment:             { icon: 'cube',      vocab: EQUIPMENT_STATUS,            route: (id) => ({ name: 'ops.equipos.show', params: { id } }) },
    work_orders:           { icon: 'wrench',    vocab: WORK_ORDER_STATUS,           route: (id) => ({ name: 'ops.ordenes.show', params: { id } }) },
    maintenance_requests:  { icon: 'clipboard', vocab: MAINTENANCE_REQUEST_STATUS,  route: (id) => ({ name: 'ops.solicitudes.show', params: { id } }) },
    spare_parts:           { icon: 'package',   vocab: null,                        route: () => ({ name: 'ops.repuestos' }) },
    maintenance_plans:     { icon: 'calendar',  vocab: null,                        route: () => ({ name: 'ops.preventivos' }) },
}

const meta = (type) => GROUP_META[type] ?? { icon: 'search', vocab: null, route: () => null }
const statusOf = (type, status) => describe(meta(type).vocab ?? {}, status)

// Flat list (with stable global index) for keyboard navigation.
const flatItems = computed(() => groups.value.flatMap((g) => g.items.map((it) => ({ ...it, type: g.type }))))
const activeId = computed(() => (flatItems.value.length ? `cmd-opt-${activeIndex.value}` : undefined))

async function runSearch(term) {
    if (controller) { controller.abort() }
    controller = new AbortController()
    loading.value = true
    try {
        const res = await api.get(`search?q=${encodeURIComponent(term)}`, { signal: controller.signal })
        // Attach a stable flat index used for keyboard nav + aria-activedescendant.
        let idx = 0
        groups.value = (res?.groups ?? []).map((g) => ({
            ...g,
            items: g.items.map((it) => ({ ...it, _index: idx++ })),
        }))
        activeIndex.value = 0
    } catch (err) {
        if (err?.name !== 'AbortError') { groups.value = [] }
    } finally {
        // Only clear loading for the most recent (non-aborted) request.
        if (!controller.signal.aborted) { loading.value = false }
    }
}

watch(query, (val) => {
    clearTimeout(debounceTimer)
    const term = val.trim()
    if (term.length < MIN_LENGTH) {
        if (controller) { controller.abort() }
        groups.value = []
        loading.value = false
        return
    }
    debounceTimer = setTimeout(() => runSearch(term), DEBOUNCE_MS)
})

watch(isOpen, async (open) => {
    if (open) {
        query.value = ''
        groups.value = []
        activeIndex.value = 0
        // Avoid popping the on-screen keyboard on touch devices.
        if (!window.matchMedia('(pointer: coarse)').matches) {
            await nextTick()
            inputRef.value?.focus()
        }
    } else {
        clearTimeout(debounceTimer)
        if (controller) { controller.abort() }
    }
})

function move(delta) {
    const n = flatItems.value.length
    if (!n) { return }
    activeIndex.value = (activeIndex.value + delta + n) % n
    nextTick(() => document.getElementById(`cmd-opt-${activeIndex.value}`)?.scrollIntoView({ block: 'nearest' }))
}

function activate(item) {
    const target = meta(item.type).route(item.id)
    close()
    if (target) { router.push(target) }
}

function onKeydown(e) {
    if (e.key === 'Escape') { e.preventDefault(); close() }
    else if (e.key === 'ArrowDown') { e.preventDefault(); move(1) }
    else if (e.key === 'ArrowUp') { e.preventDefault(); move(-1) }
    else if (e.key === 'Enter') {
        e.preventDefault()
        const item = flatItems.value[activeIndex.value]
        if (item) { activate(item) }
    }
    else if (e.key === 'Tab') { e.preventDefault() } // focus trap: keep focus on the input
}
</script>

<style scoped>
.cmd-enter-active, .cmd-leave-active { transition: opacity 0.15s ease; }
.cmd-enter-from, .cmd-leave-to { opacity: 0; }
.cmd-enter-active .relative, .cmd-leave-active .relative { transition: transform 0.15s ease; }
.cmd-enter-from .relative, .cmd-leave-to .relative { transform: translateY(-8px); }
</style>
