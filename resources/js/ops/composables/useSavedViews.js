import { ref } from 'vue'

const PREFIX = 'fronda.savedviews'
const MAX = 10

// Shared reactive list per view namespace, so the dropdown stays in sync.
const stores = {}

function read(view) {
    try {
        const raw = JSON.parse(localStorage.getItem(`${PREFIX}.${view}`) ?? '[]')
        return Array.isArray(raw) ? raw : []
    } catch {
        return []
    }
}

function persist(view, arr) {
    try {
        localStorage.setItem(`${PREFIX}.${view}`, JSON.stringify(arr))
    } catch { /* best-effort */ }
}

function storeFor(view) {
    if (! stores[view]) {
        stores[view] = ref(read(view))
    }
    return stores[view]
}

function newId() {
    return (typeof crypto !== 'undefined' && crypto.randomUUID) ? crypto.randomUUID() : `v_${Date.now()}_${Math.random().toString(36).slice(2, 8)}`
}

/**
 * Saved filter presets for a list view, persisted under `fronda.savedviews.<view>`.
 * Each entry: { id, name, state, createdAt }. Capped at MAX, newest-first.
 * `state` only ever holds UI filter/search values — never tokens or tenant_id.
 *
 * @param {'equipment'|'workorders'|'requests'|'spareparts'|'preventives'} view
 */
export function useSavedViews(view) {
    const views = storeFor(view)

    function save(name, state) {
        const entry = {
            id: newId(),
            name,
            state: JSON.parse(JSON.stringify(state ?? {})),
            createdAt: new Date().toISOString(),
        }
        views.value = [entry, ...views.value].slice(0, MAX)
        persist(view, views.value)
    }

    function remove(id) {
        views.value = views.value.filter((v) => v.id !== id)
        persist(view, views.value)
    }

    return { views, save, remove, max: MAX }
}
