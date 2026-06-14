import { computed, ref } from 'vue'
import { useApi } from './useApi.js'

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4)
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/')
    const rawData = window.atob(base64)
    const outputArray = new Uint8Array(rawData.length)
    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i)
    }
    return outputArray
}

export function usePushNotifications() {
    const api = useApi()

    const isSupported = computed(
        () =>
            'serviceWorker' in navigator &&
            'PushManager' in window &&
            'Notification' in window,
    )

    const permission = ref(
        typeof Notification !== 'undefined' ? Notification.permission : 'denied',
    )

    async function sendSubscriptionToBackend(sub) {
        const json = sub.toJSON()
        await api.post('push-subscriptions', {
            endpoint: json.endpoint,
            public_key: json.keys?.p256dh ?? json.keys?.['p256dh'],
            auth_token: json.keys?.auth,
            content_encoding: json.contentEncoding ?? 'aesgcm',
            device_name: navigator.userAgent.slice(0, 255),
        })
    }

    async function subscribe() {
        const vapidKey = window.PalmCoreConfig?.vapidPublicKey
        if (!vapidKey) {
            return null
        }
        const reg = await navigator.serviceWorker.ready
        const sub = await reg.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(vapidKey),
        })
        await sendSubscriptionToBackend(sub)
        return sub
    }

    async function requestAndSubscribe() {
        if (!isSupported.value) {
            return { supported: false }
        }

        if (permission.value === 'granted') {
            await subscribe()
            return { granted: true }
        }

        if (permission.value === 'denied') {
            return { granted: false, denied: true }
        }

        const result = await Notification.requestPermission()
        permission.value = result

        if (result !== 'granted') {
            return { granted: false }
        }

        await subscribe()
        return { granted: true }
    }

    async function checkAndRefresh() {
        if (!isSupported.value || permission.value !== 'granted') {
            return
        }

        try {
            const reg = await navigator.serviceWorker.ready
            let sub = await reg.pushManager.getSubscription()

            if (!sub) {
                sub = await subscribe()
            } else {
                // Always upsert — endpoint may have been rotated by the browser.
                await sendSubscriptionToBackend(sub)
            }
        } catch {
            // Non-critical: push refresh failing should not affect app startup.
        }
    }

    async function deactivate() {
        if (!isSupported.value) {
            return
        }

        try {
            const reg = await navigator.serviceWorker.ready
            const sub = await reg.pushManager.getSubscription()

            if (sub) {
                await api
                    .del(`push-subscriptions?endpoint=${encodeURIComponent(sub.endpoint)}`)
                    .catch(() => {})
                await sub.unsubscribe()
            }
        } catch {
            // Best-effort on logout — don't block the logout flow.
        }
    }

    return {
        isSupported,
        permission,
        requestAndSubscribe,
        checkAndRefresh,
        deactivate,
    }
}
