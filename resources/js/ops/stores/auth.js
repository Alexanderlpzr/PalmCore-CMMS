import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const useAuthStore = defineStore('auth', () => {
    const token = ref(null)
    const tenantName = ref(localStorage.getItem('fronda_tenant_name') ?? null)
    const tenantSlug = ref(localStorage.getItem('fronda_tenant_slug') ?? null)
    const userEmail = ref(localStorage.getItem('fronda_user_email') ?? null)
    const userName = ref(localStorage.getItem('fronda_user_name') ?? null)

    const isAuthenticated = computed(() => token.value !== null)

    const userInitials = computed(() => {
        if (!userName.value) return '?'
        return userName.value
            .split(' ')
            .slice(0, 2)
            .map(n => n[0])
            .join('')
            .toUpperCase()
    })

    async function login(email, password, slug) {
        const response = await fetch('/api/v1/tokens', {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({
                email,
                password,
                tenant_slug: slug,
                token_name: 'Fronda Ops',
                abilities: [
                    'work-orders.read', 'work-orders.write',
                    'equipment.read', 'equipment.write',
                    'maintenance-requests.read', 'maintenance-requests.write',
                    'inventory.read', 'inventory.write',
                    'plants.read', 'areas.read',
                    'alerts.read', 'alerts.write',
                    'maintenance-plans.read',
                    'reports.read',
                ],
            }),
        })

        if (!response.ok) {
            const data = await response.json().catch(() => ({}))
            throw new Error(data.message ?? 'Credenciales incorrectas')
        }

        const data = await response.json()
        token.value = data.token
        tenantName.value = data.tenant?.name ?? slug
        tenantSlug.value = data.tenant?.slug ?? slug
        userEmail.value = email
        userName.value = data.user?.name ?? null

        localStorage.setItem('fronda_tenant_name', tenantName.value)
        localStorage.setItem('fronda_tenant_slug', tenantSlug.value)
        localStorage.setItem('fronda_user_email', email)
        if (userName.value) localStorage.setItem('fronda_user_name', userName.value)
    }

    async function restoreSession() {
        try {
            const response = await fetch('/api/v1/auth/refresh', {
                method: 'POST',
                credentials: 'include',
                headers: { Accept: 'application/json' },
            })

            if (!response.ok) { _clear(); return false }

            const data = await response.json()
            token.value = data.token

            if (data.tenant?.name) {
                tenantName.value = data.tenant.name
                localStorage.setItem('fronda_tenant_name', data.tenant.name)
            }
            if (data.tenant?.slug) {
                tenantSlug.value = data.tenant.slug
                localStorage.setItem('fronda_tenant_slug', data.tenant.slug)
            }
            if (data.user?.name) {
                userName.value = data.user.name
                localStorage.setItem('fronda_user_name', data.user.name)
            }
            return true
        } catch {
            return false
        }
    }

    async function logout() {
        try {
            await fetch('/api/v1/auth/logout', {
                method: 'DELETE',
                credentials: 'include',
                headers: {
                    Accept: 'application/json',
                    ...(token.value ? { Authorization: `Bearer ${token.value}` } : {}),
                },
            })
        } catch { /* best-effort */ }
        _clear()
    }

    function _clear() {
        token.value = null
        tenantName.value = null
        tenantSlug.value = null
        userEmail.value = null
        userName.value = null
        localStorage.removeItem('fronda_tenant_name')
        localStorage.removeItem('fronda_tenant_slug')
        localStorage.removeItem('fronda_user_email')
        localStorage.removeItem('fronda_user_name')
    }

    return {
        token, tenantName, tenantSlug, userEmail, userName,
        isAuthenticated, userInitials,
        login, logout, restoreSession,
    }
})
