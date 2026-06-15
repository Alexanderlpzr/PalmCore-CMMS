<template>
    <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-slate-900 via-slate-800 to-emerald-950 px-4 py-12">
        <div class="w-full max-w-sm">

            <!-- Logo -->
            <div class="flex flex-col items-center mb-8">
                <div class="flex items-center justify-center w-14 h-14 rounded-2xl bg-emerald-600 shadow-xl mb-4">
                    <svg class="w-8 h-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 004.486-6.336l-3.276 3.277a3.004 3.004 0 01-2.25-2.25l3.276-3.276a4.5 4.5 0 00-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437l1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008z"/>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-white tracking-tight">Fronda CMMS</h1>
                <p class="text-sm text-slate-400 mt-1">Panel de Operaciones</p>
            </div>

            <!-- Card -->
            <div class="bg-white rounded-2xl shadow-2xl p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-5">Iniciar sesión</h2>

                <form @submit.prevent="handleLogin" class="space-y-4">
                    <!-- Tenant -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1.5">Código de empresa</label>
                        <input
                            v-model="form.tenant"
                            type="text"
                            placeholder="ej: mi-empresa"
                            autocomplete="organization"
                            required
                            class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition"
                            :class="{ 'border-red-300 ring-1 ring-red-300': error }"
                        />
                    </div>

                    <!-- Email -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1.5">Correo electrónico</label>
                        <input
                            v-model="form.email"
                            type="email"
                            placeholder="usuario@empresa.com"
                            autocomplete="email"
                            required
                            class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition"
                            :class="{ 'border-red-300 ring-1 ring-red-300': error }"
                        />
                    </div>

                    <!-- Password -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1.5">Contraseña</label>
                        <input
                            v-model="form.password"
                            type="password"
                            placeholder="••••••••"
                            autocomplete="current-password"
                            required
                            class="w-full px-3 py-2.5 rounded-xl border border-gray-200 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent transition"
                            :class="{ 'border-red-300 ring-1 ring-red-300': error }"
                        />
                    </div>

                    <!-- Error -->
                    <p v-if="error" class="text-xs text-red-600 flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd"/>
                        </svg>
                        {{ error }}
                    </p>

                    <!-- Submit -->
                    <button
                        type="submit"
                        :disabled="loading"
                        class="w-full bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60 text-white font-semibold py-2.5 rounded-xl text-sm transition-colors flex items-center justify-center gap-2"
                    >
                        <svg v-if="loading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        {{ loading ? 'Ingresando...' : 'Ingresar' }}
                    </button>
                </form>
            </div>

            <p class="text-center text-xs text-slate-500 mt-6">Fronda CMMS &copy; {{ new Date().getFullYear() }}</p>
        </div>
    </div>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth.js'

const router = useRouter()
const auth = useAuthStore()

const form = reactive({
    email: localStorage.getItem('fronda_user_email') ?? '',
    password: '',
    tenant: localStorage.getItem('fronda_tenant_slug') ?? '',
})
const loading = ref(false)
const error = ref(null)

async function handleLogin() {
    loading.value = true
    error.value = null
    try {
        await auth.login(form.email, form.password, form.tenant)
        router.push({ name: 'ops.dashboard' })
    } catch (e) {
        error.value = e.message
    } finally {
        loading.value = false
    }
}
</script>
