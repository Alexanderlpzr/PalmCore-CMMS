import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router/index.js'
import { useAuthStore } from './stores/auth.js'

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)
app.use(router)

// Attempt to restore session from the HttpOnly refresh cookie before mounting.
// This ensures the router guard has the access token available when the app starts,
// allowing users with a valid refresh cookie to skip the login screen silently.
const auth = useAuthStore()
auth.restoreSession().finally(() => {
    app.mount('#app')
})
