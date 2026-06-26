import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth.js'
import { setRouter } from '../composables/useApi.js'

const routes = [
    // Auth
    {
        path: '/app/login',
        name: 'ops.login',
        component: () => import('../views/LoginView.vue'),
        meta: { guest: true },
    },

    // Authenticated — wrapped in OpsLayout
    {
        path: '/app',
        component: () => import('../layout/OpsLayout.vue'),
        meta: { requiresAuth: true },
        children: [
            { path: '', redirect: { name: 'ops.dashboard' } },
            {
                path: 'dashboard',
                name: 'ops.dashboard',
                component: () => import('../views/DashboardView.vue'),
                meta: { title: 'Dashboard' },
            },
            {
                path: 'solicitudes',
                name: 'ops.solicitudes',
                component: () => import('../views/MaintenanceRequestListView.vue'),
                meta: { title: 'Solicitudes' },
            },
            {
                path: 'solicitudes/:id',
                name: 'ops.solicitudes.show',
                component: () => import('../views/MaintenanceRequestDetailView.vue'),
                meta: { title: 'Solicitud de mantenimiento' },
            },
            {
                path: 'ordenes',
                name: 'ops.ordenes',
                component: () => import('../views/WorkOrderListView.vue'),
                meta: { title: 'Órdenes de trabajo' },
            },
            {
                path: 'ordenes/:id',
                name: 'ops.ordenes.show',
                component: () => import('../views/WorkOrderDetailView.vue'),
                meta: { title: 'Orden de trabajo' },
            },
            {
                path: 'preventivos',
                name: 'ops.preventivos',
                component: () => import('../views/PreventivosView.vue'),
                meta: { title: 'Mantenimiento programado' },
            },
            {
                path: 'equipos',
                name: 'ops.equipos',
                component: () => import('../views/EquipmentListView.vue'),
                meta: { title: 'Equipos' },
            },
            {
                path: 'equipos/:id',
                name: 'ops.equipos.show',
                component: () => import('../views/EquipmentDetailView.vue'),
                meta: { title: 'Perfil de equipo' },
            },
            {
                path: 'repuestos',
                name: 'ops.repuestos',
                component: () => import('../views/RepuestosView.vue'),
                meta: { title: 'Repuestos' },
            },
            {
                path: 'almacenes',
                name: 'ops.almacenes',
                component: () => import('../views/AlmacenesView.vue'),
                meta: { title: 'Almacenes' },
            },
            {
                path: 'kpis',
                name: 'ops.kpis',
                component: () => import('../views/KpisView.vue'),
                meta: { title: 'Indicadores' },
            },
            {
                path: 'gerencial',
                name: 'ops.gerencial',
                component: () => import('../views/ExecutiveDashboardView.vue'),
                meta: { title: 'Resumen Ejecutivo' },
            },
            {
                path: 'plataforma',
                name: 'ops.plataforma',
                component: () => import('../views/PlatformDashboardView.vue'),
                meta: { title: 'Dashboard de Plataforma', requiresSuperAdmin: true },
            },
            {
                path: 'reportes',
                name: 'ops.reportes',
                component: () => import('../views/ReportesView.vue'),
                meta: { title: 'Reportes' },
            },
            {
                path: 'alertas',
                name: 'ops.alertas',
                component: () => import('../views/AlertsView.vue'),
                meta: { title: 'Alertas' },
            },
            // PX-3 — Contextual navigation
            {
                path: 'plantes/:id',
                name: 'ops.plantes.show',
                component: () => import('../views/PlantDetailView.vue'),
                meta: { title: 'Planta' },
            },
            {
                path: 'areas/:id',
                name: 'ops.areas.show',
                component: () => import('../views/AreaDetailView.vue'),
                meta: { title: 'Área' },
            },
        ],
    },

    // Fallback
    { path: '/app/:pathMatch(.*)*', redirect: { name: 'ops.dashboard' } },
]

const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior: () => ({ top: 0 }),
})

// Register router in useApi for 401 redirects
setRouter(router)

router.beforeEach((to) => {
    const auth = useAuthStore()

    if (to.meta.requiresAuth && !auth.isAuthenticated) {
        return { name: 'ops.login' }
    }
    if (to.meta.guest && auth.isAuthenticated) {
        return { name: 'ops.dashboard' }
    }
    // Platform dashboard is Super Admin only — the backend enforces it too.
    if (to.meta.requiresSuperAdmin && !auth.isSuperAdmin) {
        return { name: 'ops.dashboard' }
    }
})

export default router
