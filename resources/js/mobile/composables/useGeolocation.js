import { ref } from 'vue'

const GPS_OPTIONS = {
    enableHighAccuracy: true,
    timeout: 10000,
    maximumAge: 60000,
}

function inferSource(accuracy) {
    if (accuracy < 20) return 'gps'
    return 'network'
}

export function useGeolocation() {
    const isSupported = 'geolocation' in navigator
    const isCapturing = ref(false)
    const lastSnapshot = ref(null)

    /**
     * Request a one-shot GPS fix.
     * Returns a GpsSnapshot or null if unavailable / denied / timeout.
     *
     * @returns {Promise<{latitude: number, longitude: number, accuracy: number, source: string, gps_timestamp: string}|null>}
     */
    async function capture() {
        if (!isSupported) return null

        isCapturing.value = true

        try {
            return await new Promise((resolve) => {
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        const snapshot = {
                            latitude: position.coords.latitude,
                            longitude: position.coords.longitude,
                            accuracy: position.coords.accuracy,
                            source: inferSource(position.coords.accuracy),
                            gps_timestamp: new Date(position.timestamp).toISOString(),
                        }
                        lastSnapshot.value = snapshot
                        resolve(snapshot)
                    },
                    () => {
                        // Permission denied, position unavailable, or timeout — GPS is optional
                        resolve(null)
                    },
                    GPS_OPTIONS,
                )
            })
        } finally {
            isCapturing.value = false
        }
    }

    return {
        isSupported,
        isCapturing,
        lastSnapshot,
        capture,
    }
}
