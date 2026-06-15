import { useAuthStore } from '../stores/auth.js'
import router from '../router/index.js'

export function useApi() {
    function buildHeaders(extra = {}) {
        const auth = useAuthStore()
        return {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            ...(auth.token ? { Authorization: `Bearer ${auth.token}` } : {}),
            ...extra,
        }
    }

    async function request(method, endpoint, body = null, extraHeaders = {}, isRetry = false) {
        const response = await fetch(`/api/v1/${endpoint}`, {
            method,
            credentials: 'include',
            headers: buildHeaders(extraHeaders),
            ...(body !== null ? { body: JSON.stringify(body) } : {}),
        })

        if (response.status === 401 && !isRetry) {
            const auth = useAuthStore()
            const restored = await auth.restoreSession()
            if (restored) {
                return request(method, endpoint, body, extraHeaders, true)
            }
            auth.logout()
            router.push({ name: 'login' })
            throw new Error('Sesión expirada. Ingresa nuevamente.')
        }

        if (!response.ok) {
            const data = await response.json().catch(() => ({}))
            throw new Error(data.message ?? `Error ${response.status}`)
        }

        if (response.status === 204) return null
        return response.json()
    }

    async function upload(endpoint, formData, extraHeaders = {}, isRetry = false) {
        const auth = useAuthStore()
        const response = await fetch(`/api/v1/${endpoint}`, {
            method: 'POST',
            credentials: 'include',
            headers: {
                Accept: 'application/json',
                ...(auth.token ? { Authorization: `Bearer ${auth.token}` } : {}),
                ...extraHeaders,
            },
            body: formData,
        })

        if (response.status === 401 && !isRetry) {
            const restored = await auth.restoreSession()
            if (restored) {
                return upload(endpoint, formData, extraHeaders, true)
            }
            auth.logout()
            router.push({ name: 'login' })
            throw new Error('Sesión expirada. Ingresa nuevamente.')
        }

        if (!response.ok) {
            const data = await response.json().catch(() => ({}))
            throw new Error(data.message ?? `Error ${response.status}`)
        }

        if (response.status === 204) return null
        return response.json()
    }

    return {
        get: (endpoint) => request('GET', endpoint),
        post: (endpoint, body, extraHeaders = {}) => request('POST', endpoint, body, extraHeaders),
        patch: (endpoint, body, extraHeaders = {}) => request('PATCH', endpoint, body, extraHeaders),
        del: (endpoint) => request('DELETE', endpoint),
        upload: (endpoint, formData, extraHeaders = {}) => upload(endpoint, formData, extraHeaders),
    }
}
