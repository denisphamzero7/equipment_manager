import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { storage, StorageKeys } from '@/core/helpers/storage'

window.Pusher = Pusher

let echoInstance = null

export function initEcho() {
  if (echoInstance) {
    return echoInstance
  }

  const host = import.meta.env.VITE_REVERB_HOST || window.location.hostname
  const port = Number(import.meta.env.VITE_REVERB_PORT) || 8090
  const scheme = import.meta.env.VITE_REVERB_SCHEME || 'http'

  echoInstance = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY || 'pnhau_core_reverb_key',
    wsHost: host,
    wsPort: port,
    wssPort: port,
    forceTLS: scheme === 'https',
    enabledTransports: ['ws', 'wss'],
    authorizer: (channel) => {
      return {
        authorize: (socketId, callback) => {
          const currentToken = storage.get(StorageKeys.TOKEN)
          const currentOrg = storage.get(StorageKeys.ORGANIZATION_ID, 22)

          fetch('/api/broadcasting/auth', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              Accept: 'application/json',
              Authorization: `Bearer ${currentToken}`,
              'X-Organization-Id': String(currentOrg),
            },
            body: JSON.stringify({
              socket_id: socketId,
              channel_name: channel.name,
            }),
          })
            .then((res) => {
              if (!res.ok) {
                throw new Error(`Broadcast authorization failed: HTTP ${res.status}`)
              }
              return res.json()
            })
            .then((data) => callback(null, data))
            .catch((err) => callback(err, null))
        },
      }
    },
  })

  return echoInstance
}

export function getEcho() {
  return echoInstance || initEcho()
}

export function disconnectEcho() {
  if (echoInstance) {
    try {
      echoInstance.disconnect()
    } catch (e) {
      console.warn('Echo disconnect error:', e)
    }
    echoInstance = null
  }
}
