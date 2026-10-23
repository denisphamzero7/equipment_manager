export default [
  {
    path: '/equipments',
    name: 'equipments.index',
    component: () => import('./views/equipment-list/index.vue'),
    meta: {
      requiresAuth: true,
      action: 'read',
      subject: 'Equipment',
    },
  },
  {
    path: '/equipments/map-view',
    name: 'equipments.map_view',
    component: () => import('./views/equipment-map/index.vue'),
    meta: {
      requiresAuth: true,
      action: 'read',
      subject: 'Equipment',
    },
  },
  {
    path: '/equipments/:id',
    name: 'equipments.show',
    component: () => import('./views/equipment-detail/index.vue'),
    meta: {
      requiresAuth: true,
      action: 'read',
      subject: 'Equipment',
    },
  },
]
