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
        meta: { requiresAuth: true },
    },
    {
        path: '/mobile/work-orders',
        name: 'work-orders',
        component: () => import('../views/WorkOrderListView.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/mobile/work-orders/:id',
        name: 'work-order-detail',
        component: () => import('../views/WorkOrderDetailView.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/mobile/equipment/:id',
        name: 'equipment-detail',
        component: () => import('../views/EquipmentDetailView.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/mobile/scan',
        name: 'scan-qr',
        component: () => import('../views/ScanQrView.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/mobile/alerts',
        name: 'alerts',
        component: () => import('../views/AlertsView.vue'),
        meta: { requiresAuth: true },
    },
    { path: '/mobile', redirect: '/mobile/dashboard' },
    { path: '/:pathMatch(.*)*', redirect: '/mobile/dashboard' },
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
        return { name: 'dashboard' }
    }
})

export default router
