import { ref } from 'vue'

// Module-scoped singleton: any component can open/close the global palette.
const isOpen = ref(false)

export function useCommandPalette() {
    return {
        isOpen,
        open: () => { isOpen.value = true },
        close: () => { isOpen.value = false },
        toggle: () => { isOpen.value = !isOpen.value },
    }
}
