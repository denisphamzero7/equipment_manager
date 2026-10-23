export default [
  {
    path: '/rooms',
    name: 'rooms.index',
    component: () => import('./views/room-list/index.vue'),
    meta: {
      requiresAuth: true,
      action: 'index',
      subject: 'Room',
    },
  },
  {
    path: '/rooms/equipments',
    name: 'rooms.equipments',
    component: () => import('./views/room-equipments/index.vue'),
    meta: {
      requiresAuth: true,
      action: 'read',
      subject: 'Room',
    },
  },
]
