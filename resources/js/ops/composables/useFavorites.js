import { ref } from 'vue'

const PREFIX = 'fronda.favorites'
const MAX = 20

// One shared reactive list per type, so a star toggled anywhere updates the
// dashboard and every other star for the same item instantly.
const stores = {}

function read(type) {
    try {
        const raw = JSON.parse(localStorage.getItem(`${PREFIX}.${type}`) ?? '[]')
        return Array.isArray(raw) ? raw.filter((id) => typeof id === 'string') : []
    } catch {
        return []
    }
}

function persist(type, arr) {
    try {
        localStorage.setItem(`${PREFIX}.${type}`, JSON.stringify(arr))
    } catch { /* best-effort */ }
}

function storeFor(type) {
    if (! stores[type]) {
        stores[type] = ref(read(type))
    }
    return stores[type]
}

/**
 * Personal favorites for a given entity type, persisted as an array of UUIDs in
 * localStorage under `fronda.favorites.<type>`. Capped at MAX per type.
 *
 * @param {'equipment'|'workorders'|'spareparts'|'preventives'} type
 */
export function useFavorites(type) {
    const items = storeFor(type)

    const isFavorite = (id) => items.value.includes(id)

    function toggle(id) {
        items.value = items.value.includes(id)
            ? items.value.filter((x) => x !== id)
            : [id, ...items.value].slice(0, MAX) // newest first, hard cap
        persist(type, items.value)
    }

    return { items, isFavorite, toggle, max: MAX }
}
