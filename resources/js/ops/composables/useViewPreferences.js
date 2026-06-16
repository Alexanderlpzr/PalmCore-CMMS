import { ref, watch } from 'vue'

// All keys live under this prefix, e.g. `fronda.equipment.filter`.
const PREFIX = 'fronda'

function readKey(key) {
    try {
        const raw = localStorage.getItem(key)
        return raw === null ? undefined : JSON.parse(raw)
    } catch {
        return undefined
    }
}

function writeKey(key, value) {
    try {
        localStorage.setItem(key, JSON.stringify(value))
    } catch { /* storage full / unavailable — preferences are best-effort */ }
}

/**
 * Per-user, per-view UI preferences backed by localStorage.
 *
 * Only UI state (filters, search, sort, page size, layout) — never tokens,
 * tenant_id or anything sensitive. Each field is restored on creation and
 * persisted on change under `fronda.<view>.<field>`.
 *
 * @param {string} view    Stable view namespace (e.g. 'equipment', 'workorders').
 * @param {Record<string, any>} schema  Field name → default value.
 * @returns {Record<string, import('vue').Ref> & { reset: () => void }}
 */
export function useViewPreferences(view, schema) {
    const refs = {}

    for (const [field, def] of Object.entries(schema)) {
        const key = `${PREFIX}.${view}.${field}`
        const stored = readKey(key)
        const r = ref(stored === undefined ? def : stored)
        watch(r, (val) => writeKey(key, val))
        refs[field] = r
    }

    function reset() {
        for (const [field, def] of Object.entries(schema)) {
            localStorage.removeItem(`${PREFIX}.${view}.${field}`)
            // Assigning the default re-persists it via the watcher above.
            refs[field].value = typeof def === 'object' && def !== null
                ? JSON.parse(JSON.stringify(def))
                : def
        }
    }

    return { ...refs, reset }
}
