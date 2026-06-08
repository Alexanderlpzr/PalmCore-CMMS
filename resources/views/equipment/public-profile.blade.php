@extends('layouts.public')

@section('title', $equipment->code . ' — ' . $equipment->name)

@php
    $statusColors = [
        'active'            => 'bg-emerald-100 text-emerald-800',
        'inactive'          => 'bg-gray-100 text-gray-600',
        'under_maintenance' => 'bg-amber-100 text-amber-800',
        'retired'           => 'bg-red-100 text-red-700',
        'disposed'          => 'bg-red-200 text-red-800',
    ];
    $criticalityColors = [
        'critical' => 'bg-red-100 text-red-700',
        'high'     => 'bg-orange-100 text-orange-700',
        'medium'   => 'bg-yellow-100 text-yellow-700',
        'low'      => 'bg-green-100 text-green-700',
    ];
    $statusVal      = $equipment->status?->value ?? 'inactive';
    $criticalityVal = $equipment->criticality?->value ?? 'low';
@endphp

@section('content')
<div class="max-w-lg mx-auto px-4 py-6">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <p class="text-xs font-semibold text-emerald-600 uppercase tracking-widest">PalmCore EAM</p>
            <h1 class="text-xl font-bold text-gray-900 leading-tight mt-0.5">{{ $equipment->name }}</h1>
        </div>
        <span class="text-2xl font-mono font-black text-gray-400">{{ $equipment->code }}</span>
    </div>

    {{-- Primary photo --}}
    @if ($equipment->primaryPhoto)
        <div class="rounded-2xl overflow-hidden mb-5 shadow-sm bg-gray-100 aspect-video">
            <img
                src="{{ Storage::disk('public')->url($equipment->primaryPhoto->file_path) }}"
                alt="{{ $equipment->name }}"
                class="w-full h-full object-cover"
            >
        </div>
    @endif

    {{-- Status + Criticality badges --}}
    <div class="flex gap-2 flex-wrap mb-5">
        <span class="px-3 py-1 rounded-full text-sm font-semibold {{ $statusColors[$statusVal] ?? 'bg-gray-100 text-gray-600' }}">
            {{ $equipment->status?->label() ?? 'Desconocido' }}
        </span>
        <span class="px-3 py-1 rounded-full text-sm font-semibold {{ $criticalityColors[$criticalityVal] ?? 'bg-gray-100 text-gray-600' }}">
            Criticidad: {{ $equipment->criticality?->label() ?? '—' }}
        </span>
        @if ($equipment->priority)
            <span class="px-3 py-1 rounded-full text-sm font-semibold bg-blue-100 text-blue-700">
                {{ $equipment->priority->label() }}
            </span>
        @endif
    </div>

    {{-- Equipment details card --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 divide-y divide-gray-50 mb-5">
        @if ($equipment->plant)
            <div class="flex items-center justify-between px-4 py-3">
                <span class="text-sm text-gray-500">Planta</span>
                <span class="text-sm font-medium text-gray-900">{{ $equipment->plant->name }}</span>
            </div>
        @endif
        @if ($equipment->area)
            <div class="flex items-center justify-between px-4 py-3">
                <span class="text-sm text-gray-500">Área</span>
                <span class="text-sm font-medium text-gray-900">{{ $equipment->area->name }}</span>
            </div>
        @endif
        @if ($equipment->category)
            <div class="flex items-center justify-between px-4 py-3">
                <span class="text-sm text-gray-500">Categoría</span>
                <span class="text-sm font-medium text-gray-900">{{ $equipment->category->name }}</span>
            </div>
        @endif
        @if ($equipment->model)
            <div class="flex items-center justify-between px-4 py-3">
                <span class="text-sm text-gray-500">Modelo</span>
                <span class="text-sm font-medium text-gray-900">{{ $equipment->model }}</span>
            </div>
        @endif
        @if ($equipment->serial_number)
            <div class="flex items-center justify-between px-4 py-3">
                <span class="text-sm text-gray-500">N° Serie</span>
                <span class="text-sm font-medium font-mono text-gray-900">{{ $equipment->serial_number }}</span>
            </div>
        @endif
    </div>

    {{-- Report form (Livewire) --}}
    @livewire('equipment.report-form', ['equipment' => $equipment, 'qrCode' => $qrCode])

    {{-- Footer --}}
    <p class="text-center text-xs text-gray-400 mt-8">
        PalmCore EAM/CMMS &mdash; Escaneos: {{ number_format($qrCode->scan_count) }}
    </p>

</div>
@endsection
