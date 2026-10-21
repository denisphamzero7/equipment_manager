/**
 * Toàn bộ Endpoint API của hệ thống
 */
export const Endpoints = Object.freeze({
  AUTH: {
    LOGIN: '/api/auth/login',
    REGISTER: '/api/auth/register',
    LOGOUT: '/api/auth/logout',
    ME: '/api/user',
    FORGOT_PASSWORD: '/api/auth/forgot-password',
    RESET_PASSWORD: '/api/auth/reset-password',
  },
  EQUIPMENT: {
    INDEX: '/api/equipments',
    SHOW: id => `/api/equipments/${id}`,
    STORE: '/api/equipments',
    UPDATE: id => `/api/equipments/${id}`,
    DESTROY: id => `/api/equipments/${id}`,
    CHANGE_STATUS: id => `/api/equipments/${id}/status`,
    BULK_STATUS: '/api/equipments/bulk/status',
    BULK_DESTROY: '/api/equipments/bulk/destroy',
    ASSIGN_ROOM: '/api/equipments/assign-to-room',
    REPORT_BROKEN: id => `/api/equipments/${id}/report-broken`,
    UPLOAD_PHOTO: id => `/api/equipments/${id}/upload-photo`,
    STATS: '/api/equipments/stats',
    EXPORT: '/api/equipments/export',
    IMPORT: '/api/equipments/import',
    MAP_VIEW_BLOCKS: '/api/equipments/map-view/blocks',
    MAP_VIEW_FLOORS: '/api/equipments/map-view/floors',
    MAP_VIEW: '/api/equipments/map-view',
    ROOM_DETAIL: roomId => `/api/equipments/rooms/${roomId}`,
  },
  ROOM: {
    INDEX: '/api/motel/rooms',
    SHOW: id => `/api/motel/rooms/${id}`,
    STORE: '/api/motel/rooms',
    UPDATE: id => `/api/motel/rooms/${id}`,
    DESTROY: id => `/api/motel/rooms/${id}`,
    BATCH_GENERATE: '/api/motel/rooms/batch-generate',
    ROOM_TYPES: '/api/motel/rooms/room-types',
    BULK_DESTROY: '/api/motel/rooms/bulk-delete',
    RENAME_BLOCK: '/api/motel/structure/rename-block',
    DELETE_BLOCK: '/api/motel/structure/delete-block',
  },
  ORGANIZATION: {
    PUBLIC_OPTIONS: '/api/organizations/public-options',
  },
})
