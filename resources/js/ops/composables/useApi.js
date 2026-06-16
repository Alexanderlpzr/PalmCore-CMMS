import { useAuthStore } from '../stores/auth.js'

let router = null
export function setRouter(r) { router = r }

function headers(extra = {}) {
    const auth = useAuthStore()
    return {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        ...(auth.token ? { Authorization: `Bearer ${auth.token}` } : {}),
        ...extra,
    }
}

async function request(method, endpoint, body = null, extraHeaders = {}, isRetry = false, options = {}) {
    const response = await fetch(`/api/v1/${endpoint}`, {
        method,
        credentials: 'include',
        headers: headers(extraHeaders),
        ...(options.signal ? { signal: options.signal } : {}),
        ...(body !== null ? { body: JSON.stringify(body) } : {}),
    })

    if (response.status === 401 && !isRetry) {
        const auth = useAuthStore()
        const restored = await auth.restoreSession()
        if (restored) return request(method, endpoint, body, extraHeaders, true, options)
        auth.logout()
        router?.push({ name: 'ops.login' })
        throw new Error('Sesión expirada')
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
        if (restored) return upload(endpoint, formData, extraHeaders, true)
        auth.logout()
        router?.push({ name: 'ops.login' })
        throw new Error('Sesión expirada')
    }

    if (!response.ok) {
        const data = await response.json().catch(() => ({}))
        throw new Error(data.message ?? `Error ${response.status}`)
    }

    if (response.status === 204) return null
    return response.json()
}

async function getBlob(endpoint, isRetry = false) {
    const auth = useAuthStore()
    const response = await fetch(`/api/v1/${endpoint}`, {
        method: 'GET',
        credentials: 'include',
        headers: {
            Accept: 'application/pdf',
            ...(auth.token ? { Authorization: `Bearer ${auth.token}` } : {}),
        },
    })

    if (response.status === 401 && !isRetry) {
        const restored = await auth.restoreSession()
        if (restored) return getBlob(endpoint, true)
        auth.logout()
        router?.push({ name: 'ops.login' })
        throw new Error('Sesión expirada')
    }

    if (!response.ok) {
        throw new Error(`Error ${response.status}`)
    }

    return response.blob()
}

async function downloadFile(endpoint, filename) {
    const blob = await getBlob(endpoint)
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = filename
    document.body.appendChild(a)
    a.click()
    a.remove()
    URL.revokeObjectURL(url)
}

export function useApi() {
    return {
        get: (endpoint, options = {}) => request('GET', endpoint, null, {}, false, options),
        download: (endpoint, filename) => downloadFile(endpoint, filename),
        post: (endpoint, body, extra = {}) => request('POST', endpoint, body, extra),
        patch: (endpoint, body, extra = {}) => request('PATCH', endpoint, body, extra),
        put: (endpoint, body, extra = {}) => request('PUT', endpoint, body, extra),
        del: (endpoint) => request('DELETE', endpoint),
        upload: (endpoint, formData, extra = {}) => upload(endpoint, formData, extra),
    }
}
