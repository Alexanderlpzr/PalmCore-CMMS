import { ref } from 'vue'

const toasts = ref([])
let nextId = 0

function add(message, type = 'info', duration = 4000) {
    const id = ++nextId
    toasts.value.push({ id, message, type })
    setTimeout(() => remove(id), duration)
    return id
}

function remove(id) {
    const idx = toasts.value.findIndex(t => t.id === id)
    if (idx !== -1) toasts.value.splice(idx, 1)
}

export function useToast() {
    return {
        toasts,
        success: (msg) => add(msg, 'success'),
        error: (msg) => add(msg, 'error', 6000),
        info: (msg) => add(msg, 'info'),
        warning: (msg) => add(msg, 'warning'),
        remove,
    }
}
