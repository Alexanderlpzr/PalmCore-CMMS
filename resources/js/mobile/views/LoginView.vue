<template>
    <div class="min-h-screen bg-zinc-950 flex flex-col justify-center px-6"
         :style="{ paddingTop: 'env(safe-area-inset-top)', paddingBottom: 'env(safe-area-inset-bottom)' }">

        <!-- Branding -->
        <div class="mb-10 text-center">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-amber-500 mb-4">
                <span class="text-2xl font-bold text-zinc-950">F</span>
            </div>
            <h1 class="text-2xl font-bold text-zinc-100">Fronda</h1>
            <p class="text-sm text-zinc-400 mt-1">Gestión de mantenimiento</p>
        </div>

        <!-- Form -->
        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-zinc-400 mb-1.5">
                    Correo electrónico
                </label>
                <input
                    v-model="form.email"
                    type="email"
                    autocomplete="email"
                    required
                    class="w-full bg-zinc-900 border border-zinc-700 rounded-xl px-4 py-3 text-zinc-100 placeholder-zinc-600
                           focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent transition"
                    placeholder="tecnico@empresa.com"
                />
            </div>

            <div>
                <label class="block text-sm font-medium text-zinc-400 mb-1.5">
                    Contraseña
                </label>
                <input
                    v-model="form.password"
                    type="password"
                    autocomplete="current-password"
                    required
                    class="w-full bg-zinc-900 border border-zinc-700 rounded-xl px-4 py-3 text-zinc-100 placeholder-zinc-600
                           focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent transition"
                    placeholder="••••••••"
                />
            </div>

            <div>
                <label class="block text-sm font-medium text-zinc-400 mb-1.5">
                    Código de empresa
                </label>
                <input
                    v-model="form.tenantSlug"
                    type="text"
                    autocomplete="organization"
                    required
                    class="w-full bg-zinc-900 border border-zinc-700 rounded-xl px-4 py-3 text-zinc-100 placeholder-zinc-600
                           focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-transparent transition"
                    placeholder="mi-empresa"
                />
            </div>

            <!-- Error -->
            <div v-if="errorMessage" class="rounded-xl bg-red-500/10 border border-red-500/20 px-4 py-3">
                <p class="text-sm text-red-400">{{ errorMessage }}</p>
            </div>

            <!-- Submit -->
            <button
                type="submit"
                :disabled="loading"
                class="w-full bg-amber-500 hover:bg-amber-400 disabled:opacity-50 disabled:cursor-not-allowed
                       text-zinc-950 font-semibold rounded-xl px-4 py-3.5 transition mt-2 flex items-center justify-center gap-2"
            >
                <svg v-if="loading" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                </svg>
                {{ loading ? 'Ingresando…' : 'Iniciar sesión' }}
            </button>
        </form>
    </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth.js'

const router = useRouter()
const auth = useAuthStore()

const form = reactive({ email: '', password: '', tenantSlug: '' })
const loading = ref(false)
const errorMessage = ref('')

async function submit() {
    loading.value = true
    errorMessage.value = ''
    try {
        await auth.login(form.email, form.password, form.tenantSlug)
        router.push({ name: 'dashboard' })
    } catch (e) {
        errorMessage.value = e.message
    } finally {
        loading.value = false
    }
}
</script>
