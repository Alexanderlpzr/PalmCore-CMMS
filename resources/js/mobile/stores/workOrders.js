import { defineStore } from 'pinia'
import { ref } from 'vue'
import { useApi } from '../composables/useApi.js'
import db from '../db/database.js'

export const useWorkOrdersStore = defineStore('workOrders', () => {
    const items = ref([])
    const currentItem = ref(null)
    const loading = ref(false)
    const error = ref(null)
    const isFromCache = ref(false)

    async function fetchMine() {
        loading.value = true
        error.value = null
        isFromCache.value = false
        try {
            const api = useApi()
            const data = await api.get('work-orders/mine')
            items.value = data.data
            await db.cachedWorkOrders.bulkPut(
                data.data.map(wo => ({ id: wo.id, data: wo, cachedAt: new Date().toISOString() })),
            )
        } catch (e) {
            const cached = await db.cachedWorkOrders.toArray()
            if (cached.length > 0) {
                items.value = cached.map(c => c.data)
                isFromCache.value = true
            } else {
                error.value = e.message
            }
        } finally {
            loading.value = false
        }
    }

    async function fetchOne(id) {
        loading.value = true
        error.value = null
        isFromCache.value = false
        try {
            const api = useApi()
            const data = await api.get(`work-orders/${id}`)
            currentItem.value = data.data
            await db.cachedWorkOrders.put({
                id: data.data.id,
                data: data.data,
                cachedAt: new Date().toISOString(),
            })
        } catch (e) {
            const cached = await db.cachedWorkOrders.get(String(id))
            if (cached) {
                currentItem.value = cached.data
                isFromCache.value = true
            } else {
                error.value = e.message
            }
        } finally {
            loading.value = false
        }
    }

    return { items, currentItem, loading, error, isFromCache, fetchMine, fetchOne }
})
