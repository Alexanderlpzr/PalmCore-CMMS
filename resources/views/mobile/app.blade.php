<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#09090b">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Fronda CMMS">
    <link rel="manifest" href="/mobile-manifest.json">
    <link rel="apple-touch-icon" href="/icons/icon-192.svg">
    <title>Fronda CMMS</title>
    @vite(['resources/css/mobile.css', 'resources/js/mobile/main.js'])
</head>
<body class="bg-zinc-950 text-zinc-100 antialiased overflow-hidden">
    <div id="app"></div>
    <script>
        window.FrondaConfig = {
            vapidPublicKey: '{{ config('webpush.vapid.public_key') }}'
        };
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register('/sw.js', { scope: '/mobile/' });
            });
        }
    </script>
</body>
</html>
