export default [
  {
    path: '/equipments',
    name: 'equipments.index',
    component: () => import('./views/EquipmentListView.vue'),
    meta: {
      requiresAuth: true,
      action: 'read',
      subject: 'Equipment',
    },
  },
  {
    path: '/equipments/map-view',
    name: 'equipments.map_view',
    component: () => import('./views/EquipmentMapView.vue'),
    meta: {
      requiresAuth: true,
      action: 'read',
      subject: 'Equipment',
    },
  },
  {
    path: '/equipments/:id',
    name: 'equipments.show',
    component: () => import('./views/EquipmentDetailView.vue'),
    meta: {
      requiresAuth: true,
      action: 'read',
      subject: 'Equipment',
    },
  },
]
