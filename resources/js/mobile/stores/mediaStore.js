import { defineStore } from 'pinia'
import { ref } from 'vue'
import { useApi } from '../composables/useApi.js'

export const useMediaStore = defineStore('media', () => {
    const loading = ref(false)
    const error = ref(null)

    async function uploadMedia(workOrderId, blob, attachmentType, caption = '') {
        loading.value = true
        error.value = null
        try {
            const api = useApi()
            const form = new FormData()
            const ext = blob.type === 'image/png' ? 'png' : 'jpg'
            form.append('file', blob, `photo_${Date.now()}.${ext}`)
            form.append('attachment_type', attachmentType)
            if (caption) {
                form.append('caption', caption)
            }
            return await api.upload(`work-orders/${workOrderId}/media`, form)
        } catch (e) {
            error.value = e.message
            throw e
        } finally {
            loading.value = false
        }
    }

    return { loading, error, uploadMedia }
})
