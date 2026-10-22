export default [
  {
    path: '/rooms',
    name: 'rooms.index',
    component: () => import('./views/RoomListView.vue'),
    meta: {
      requiresAuth: true,
      action: 'index',
      subject: 'Room',
    },
  },
  {
    path: '/rooms/equipments',
    name: 'rooms.equipments',
    component: () => import('./views/RoomEquipmentView.vue'),
    meta: {
      requiresAuth: true,
      action: 'read',
      subject: 'Room',
    },
  },
]
