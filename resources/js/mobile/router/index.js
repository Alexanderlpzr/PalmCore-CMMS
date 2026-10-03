import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth.js'

const routes = [
    {
        path: '/mobile/login',
        name: 'login',
        component: () => import('../views/LoginView.vue'),
        meta: { guest: true },
    },
    {
        path: '/mobile/dashboard',
        name: 'dashboard',
        component: () => import('../views/DashboardView.vue'),
        meta: { requiresAuth: true, mode: 'maintenance' },
    },
    {
        path: '/mobile/work-orders',
        name: 'work-orders',
        component: () => import('../views/WorkOrderListView.vue'),
        meta: { requiresAuth: true, mode: 'maintenance' },
    },
    {
        path: '/mobile/work-orders/:id',
        name: 'work-order-detail',
        component: () => import('../views/WorkOrderDetailView.vue'),
        meta: { requiresAuth: true, mode: 'maintenance' },
    },
    {
        path: '/mobile/equipment/:id',
        name: 'equipment-detail',
        component: () => import('../views/EquipmentDetailView.vue'),
        meta: { requiresAuth: true, mode: 'maintenance' },
    },
    {
        path: '/mobile/scan',
        name: 'scan-qr',
        component: () => import('../views/ScanQrView.vue'),
        meta: { requiresAuth: true, mode: 'maintenance' },
    },
    {
        // La puerta. Separada de `scan-qr` porque no comparten casi nada: aquel busca un
        // equipo y navega a su ficha; este marca y se queda escaneando al siguiente.
        path: '/mobile/porteria',
        name: 'porteria',
        component: () => import('../views/PorteriaView.vue'),
        meta: { requiresAuth: true, mode: 'gate' },
    },
    {
        path: '/mobile/alerts',
        name: 'alerts',
        component: () => import('../views/AlertsView.vue'),
        meta: { requiresAuth: true, mode: 'maintenance' },
    },
    { path: '/mobile', redirect: () => ({ name: useAuthStore().homeRoute }) },
    { path: '/:pathMatch(.*)*', redirect: () => ({ name: useAuthStore().homeRoute }) },
]

const router = createRouter({
    history: createWebHistory(),
    routes,
})

router.beforeEach((to) => {
    const auth = useAuthStore()

    if (to.meta.requiresAuth && !auth.token) {
        return { name: 'login' }
    }

    if (to.meta.guest && auth.token) {
        return { name: auth.homeRoute }
    }

    // El vigilante no ve mantenimiento ni quien solo hace mantenimiento ve la puerta: el
    // servidor igual le negaría los datos, pero así no aterriza en una pantalla vacía.
    if (to.meta.mode && auth.token && !auth.modes[to.meta.mode] && to.name !== auth.homeRoute) {
        return { name: auth.homeRoute }
    }
})

export default router
