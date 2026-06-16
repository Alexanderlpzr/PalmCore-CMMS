import { ref, computed } from 'vue'

/**
 * Per-list selection state for bulk actions. Call once per view (each call owns
 * its own Set), pass row ids through toggle()/setMany(), and read count/ids().
 */
export function useBulkSelection() {
    const selected = ref(new Set())

    const count = computed(() => selected.value.size)
    const has = (id) => selected.value.has(id)

    function toggle(id) {
        const next = new Set(selected.value)
        next.has(id) ? next.delete(id) : next.add(id)
        selected.value = next
    }

    function setMany(ids, on) {
        const next = new Set(selected.value)
        ids.forEach((id) => (on ? next.add(id) : next.delete(id)))
        selected.value = next
    }

    function clear() {
        selected.value = new Set()
    }

    const ids = () => [...selected.value]

    return { selected, count, has, toggle, setMany, clear, ids }
}
