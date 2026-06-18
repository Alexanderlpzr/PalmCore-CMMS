import { createApp } from 'vue'
import { createPinia } from 'pinia'
import * as Sentry from '@sentry/vue'
import App from './App.vue'
import router from './router/index.js'
import { useAuthStore } from './stores/auth.js'

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)
app.use(router)

// Initialize Sentry before mounting — only when DSN is configured.
if (import.meta.env.VITE_SENTRY_DSN) {
    Sentry.init({
        app,
        dsn: import.meta.env.VITE_SENTRY_DSN,
        integrations: [
            Sentry.browserTracingIntegration({ router }),
        ],
        tracesSampleRate: 0.1,
        environment: import.meta.env.MODE,
        // Limit breadcrumb noise
        maxBreadcrumbs: 30,
        // Do not send auth tokens under any circumstance
        beforeSend(event) {
            // Strip Authorization headers from any captured request
            if (event.request?.headers) {
                delete event.request.headers['Authorization']
                delete event.request.headers['authorization']
            }
            return event
        },
    })
}

// Restore session from HttpOnly refresh cookie before mounting.
// If valid, the user lands directly on their dashboard without re-entering credentials.
const auth = useAuthStore()
auth.restoreSession().finally(() => {
    // Set Sentry user context after session is restored — no access token included
    if (import.meta.env.VITE_SENTRY_DSN && auth.isAuthenticated) {
        Sentry.setUser({
            email: auth.userEmail,
            username: auth.userName,
        })
        Sentry.setTag('tenant_slug', auth.tenantSlug)
    }
    app.mount('#ops-app')
})
