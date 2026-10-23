export default [
  {
    path: '/login',
    name: 'login',
    component: () => import('./views/login/index.vue'),
    meta: { guestOnly: true },
  },
  {
    path: '/register',
    name: 'register',
    component: () => import('./views/register/index.vue'),
    meta: { guestOnly: true },
  },
  {
    path: '/admin/users',
    name: 'admin.users',
    component: () => import('./views/users/index.vue'),
    meta: { requiresAuth: true, action: 'read', subject: 'Users' },
  },
  {
    path: '/admin/roles',
    name: 'admin.roles',
    component: () => import('./views/roles/index.vue'),
    meta: { requiresAuth: true, action: 'read', subject: 'Roles' },
  },
  {
    path: '/admin/permissions',
    name: 'admin.permissions',
    component: () => import('./views/permissions/index.vue'),
    meta: { requiresAuth: true, action: 'tree', subject: 'Permissions' },
  },
]
