import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const useAuthStore = defineStore('auth', () => {
    // Access token lives in memory only — never written to localStorage.
    // The refresh token is stored in an HttpOnly cookie (invisible to JS).
    const token = ref(null)

    // Non-sensitive display data persisted for UX across restarts
    const tenantName = ref(localStorage.getItem('fronda_tenant_name') ?? null)
    const userEmail = ref(localStorage.getItem('fronda_user_email') ?? null)
    const userName = ref(localStorage.getItem('fronda_user_name') ?? null)

    const isAuthenticated = computed(() => token.value !== null)

    async function login(email, password, tenantSlug) {
        const response = await fetch('/api/v1/tokens', {
            method: 'POST',
            credentials: 'include', // sends/receives the refresh cookie
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({
                email,
                password,
                tenant_slug: tenantSlug,
                token_name: 'Fronda Mobile',
                abilities: ['work-orders.read', 'work-orders.write', 'equipment.read', 'maintenance-requests.read', 'maintenance-requests.write', 'inventory.read', 'plants.read', 'areas.read'],
            }),
        })

        if (!response.ok) {
            const data = await response.json().catch(() => ({}))
            throw new Error(data.message ?? 'Error al iniciar sesión')
        }

        const data = await response.json()

        token.value = data.token
        tenantName.value = data.tenant?.name ?? tenantSlug
        userEmail.value = email
        userName.value = data.user?.name ?? null

        // Persist only non-sensitive display data
        localStorage.setItem('fronda_tenant_name', tenantName.value)
        localStorage.setItem('fronda_user_email', email)
        if (userName.value) localStorage.setItem('fronda_user_name', userName.value)

        // Clean up old palmcore_* keys if they exist (migration)
        localStorage.removeItem('palmcore_token')
        localStorage.removeItem('palmcore_tenant_name')
        localStorage.removeItem('palmcore_user_email')
        localStorage.removeItem('palmcore_user_name')
    }

    /**
     * Restore the access token from the HttpOnly refresh cookie.
     * Called on app start — if the cookie is valid, the user is silently logged in.
     * Returns true if session was restored, false otherwise.
     */
    async function restoreSession() {
        try {
            const response = await fetch('/api/v1/auth/refresh', {
                method: 'POST',
                credentials: 'include',
                headers: { 'Accept': 'application/json' },
            })

            if (!response.ok) {
                _clearState()
                return false
            }

            const data = await response.json()
            token.value = data.token
            if (data.tenant?.name) {
                tenantName.value = data.tenant.name
                localStorage.setItem('fronda_tenant_name', data.tenant.name)
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
                    'Accept': 'application/json',
                    ...(token.value ? { Authorization: `Bearer ${token.value}` } : {}),
                },
            })
        } catch {
            // Best-effort — clear local state regardless
        }
        _clearState()
    }

    function _clearState() {
        token.value = null
        tenantName.value = null
        userEmail.value = null
        userName.value = null
        localStorage.removeItem('fronda_tenant_name')
        localStorage.removeItem('fronda_user_email')
        localStorage.removeItem('fronda_user_name')
    }

    return { token, tenantName, userEmail, userName, isAuthenticated, login, logout, restoreSession }
})
