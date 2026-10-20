export default [
  {
    path: '/dashboard',
    name: 'dashboard',
    component: () => import('./views/DashboardView.vue'),
    meta: {
      requiresAuth: true,
      action: 'read',
      subject: 'Dashboard',
    },
  },
]
