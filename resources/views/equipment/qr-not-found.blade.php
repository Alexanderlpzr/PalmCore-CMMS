@extends('layouts.public')

@section('title', 'QR no válido')

@section('content')
<div class="max-w-lg mx-auto px-4 py-16 text-center">
    <div class="text-6xl mb-4">🔍</div>
    <h1 class="text-xl font-bold text-gray-800 mb-2">Código QR no encontrado</h1>
    <p class="text-sm text-gray-500">
        Este QR puede haber sido desactivado o regenerado.<br>
        Solicita un nuevo QR al responsable de mantenimiento.
    </p>
</div>
@endsection
