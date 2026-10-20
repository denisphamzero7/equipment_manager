export default [
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
