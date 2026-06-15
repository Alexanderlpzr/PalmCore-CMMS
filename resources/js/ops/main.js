import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router/index.js'
import { useAuthStore } from './stores/auth.js'

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)
app.use(router)

// Restore session from HttpOnly refresh cookie before mounting.
// If valid, the user lands directly on their dashboard without re-entering credentials.
const auth = useAuthStore()
auth.restoreSession().finally(() => {
    app.mount('#ops-app')
})
