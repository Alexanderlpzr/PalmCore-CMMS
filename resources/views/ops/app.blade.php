<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <meta name="theme-color" content="#0F4C5C">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <title>Fronda CMMS</title>
    @vite(['resources/css/ops.css', 'resources/js/ops/main.js'])
</head>
<body class="h-full antialiased">
    <div id="ops-app" class="h-full"></div>
</body>
</html>
