// PalmCore Mobile — Service Worker v3
// Sprint 10.1: asset caching + navigation fallback
// Sprint 10.3: Background Sync signal to Vue app
// Sprint 10.4: Web Push notifications + notificationclick deeplink

const CACHE = 'palmcore-v1'

self.addEventListener('install', event => {
    self.skipWaiting()
    event.waitUntil(
        caches.open(CACHE).then(cache => cache.add('/mobile/'))
    )
})

self.addEventListener('activate', event => {
    event.waitUntil(
        Promise.all([
            self.clients.claim(),
            caches.keys().then(keys =>
                Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k)))
            ),
        ])
    )
})

self.addEventListener('fetch', event => {
    const url = new URL(event.request.url)

    // Pass through non-GET and API requests unchanged
    if (event.request.method !== 'GET') return
    if (url.pathname.startsWith('/api/')) return

    // Navigation to /mobile/* — network-first, fall back to cached shell
    if (event.request.mode === 'navigate' && url.pathname.startsWith('/mobile')) {
        event.respondWith(
            fetch(event.request).catch(() => caches.match('/mobile/'))
        )
        return
    }

    // Vite build assets — network-first, cache on success for subsequent visits
    if (url.pathname.startsWith('/build/')) {
        event.respondWith(
            fetch(event.request)
                .then(response => {
                    if (response.ok) {
                        const clone = response.clone()
                        caches.open(CACHE).then(cache => cache.put(event.request, clone))
                    }
                    return response
                })
                .catch(() => caches.match(event.request))
        )
    }
})

// Background Sync: notify active Vue clients to run the sync queue.
// If no clients are active the sync will happen when the app is opened next time.
self.addEventListener('sync', event => {
    if (event.tag === 'palmcore-sync') {
        event.waitUntil(
            self.clients.matchAll({ type: 'window', includeUncontrolled: false }).then(clients => {
                clients.forEach(client => {
                    client.postMessage({ type: 'SYNC_REQUESTED' })
                })
            })
        )
    }
})

// Web Push: show notification when server sends a push event.
self.addEventListener('push', event => {
    if (!event.data) return

    const data = event.data.json()

    event.waitUntil(
        self.registration.showNotification(data.title ?? 'PalmCore', {
            body: data.body ?? '',
            icon: data.icon ?? '/icons/icon-192.svg',
            badge: data.badge ?? '/icons/icon-192.svg',
            data: { url: data.url ?? '/mobile/dashboard' },
            tag: data.tag,
            vibrate: [200, 100, 200],
            requireInteraction: false,
        }),
    )
})

// notificationclick: focus or open the relevant route.
// Falls back to clients.openWindow() when the PWA is not in memory (Android killed it).
self.addEventListener('notificationclick', event => {
    event.notification.close()

    const url = event.notification.data?.url ?? '/mobile/dashboard'

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: false }).then(clientList => {
            const mobileClient = clientList.find(c => c.url.includes('/mobile/'))

            if (mobileClient) {
                // App is open in background: navigate without opening a new tab.
                mobileClient.navigate(url)
                return mobileClient.focus()
            }

            // App is closed: open a new window to the target route.
            return clients.openWindow(url)
        }),
    )
})
