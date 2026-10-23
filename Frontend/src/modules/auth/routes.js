export default [
  {
    path: '/login',
    name: 'login',
    component: () => import('./views/LoginView.vue'),
    meta: { guestOnly: true },
  },
  {
    path: '/register',
    name: 'register',
    component: () => import('./views/RegisterView.vue'),
    meta: { guestOnly: true },
  },
  {
    path: '/admin/users',
    name: 'admin.users',
    component: () => import('./views/UserManagementView.vue'),
    meta: { requiresAuth: true, action: 'read', subject: 'Users' },
  },
  {
    path: '/admin/roles',
    name: 'admin.roles',
    component: () => import('./views/RoleManagementView.vue'),
    meta: { requiresAuth: true, action: 'read', subject: 'Roles' },
  },
  {
    path: '/admin/permissions',
    name: 'admin.permissions',
    component: () => import('./views/PermissionManagementView.vue'),
    meta: { requiresAuth: true, action: 'tree', subject: 'Permissions' },
  },
]
