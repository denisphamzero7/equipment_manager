export default [
  {
    path: '/dashboard',
    name: 'dashboard',
    component: () => import('./views/dashboard/index.vue'),
    meta: {
      requiresAuth: true,
      action: 'read',
      subject: 'Dashboard',
    },
  },
]
