import { ref } from 'vue'

let counter = 0
const toasts = ref([])

export function useToast() {
    function show(message, type = 'success', duration = 3000) {
        const id = ++counter
        toasts.value.push({ id, message, type })
        setTimeout(() => {
            toasts.value = toasts.value.filter(t => t.id !== id)
        }, duration)
    }

    return {
        toasts,
        success: (msg) => show(msg, 'success'),
        error: (msg) => show(msg, 'error', 4000),
        info: (msg) => show(msg, 'info'),
    }
}
