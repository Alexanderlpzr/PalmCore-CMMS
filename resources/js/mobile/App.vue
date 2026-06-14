<template>
    <RouterView />
    <ToastContainer />
</template>

<script setup>
import { onMounted } from 'vue'
import { RouterView } from 'vue-router'
import ToastContainer from './components/ToastContainer.vue'
import { usePushNotifications } from './composables/usePushNotifications.js'
import { useNetworkStore } from './stores/networkStore.js'

const network = useNetworkStore()
const push = usePushNotifications()

onMounted(() => {
    network.init()

    // Listen for Background Sync signal from the Service Worker
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.addEventListener('message', (event) => {
            if (event.data?.type === 'SYNC_REQUESTED') {
                network.triggerSync()
            }
        })
    }

    // Refresh push subscription silently if permission was already granted.
    push.checkAndRefresh()
})
</script>
