import { defineStore } from 'pinia'
import { ref } from 'vue'
import { useApi } from '../composables/useApi.js'

export const useCommentsStore = defineStore('comments', () => {
    const loading = ref(false)
    const error = ref(null)

    async function postComment(workOrderId, body, isInternal = false) {
        loading.value = true
        error.value = null
        try {
            const api = useApi()
            return await api.post(`work-orders/${workOrderId}/comments`, {
                body,
                is_internal: isInternal,
            })
        } catch (e) {
            error.value = e.message
            throw e
        } finally {
            loading.value = false
        }
    }

    return { loading, error, postComment }
})
