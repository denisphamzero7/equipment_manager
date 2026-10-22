import { storage, StorageKeys } from '@/core/helpers/storage'

let echoInstance = null
let echoInitialization = null
let initializationGeneration = 0

export async function initEcho() {
  if (echoInstance) {
    return echoInstance
  }
  if (echoInitialization) {
    return echoInitialization
  }

  const generation = initializationGeneration
  const initialization = (async () => {
    const [{ default: Echo }, { default: Pusher }] = await Promise.all([
      import('laravel-echo'),
      import('pusher-js'),
    ])
    if (generation !== initializationGeneration) return null
    window.Pusher = Pusher

  const host = import.meta.env.VITE_REVERB_HOST || window.location.hostname
  const port = Number(import.meta.env.VITE_REVERB_PORT) || 8090
  const scheme = import.meta.env.VITE_REVERB_SCHEME || 'http'

  const instance = new Echo({
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

    if (generation !== initializationGeneration) {
      instance.disconnect()
      return null
    }
    echoInstance = instance
    return echoInstance
  })()

  echoInitialization = initialization
  try {
    return await initialization
  } finally {
    if (echoInitialization === initialization) {
      echoInitialization = null
    }
  }
}

export function getEcho() {
  return echoInstance || initEcho()
}

export function disconnectEcho() {
  initializationGeneration += 1
  echoInitialization = null

  if (echoInstance) {
    try {
      echoInstance.disconnect()
    } catch (e) {
      console.warn('Echo disconnect error:', e)
    }
    echoInstance = null
  }
}
