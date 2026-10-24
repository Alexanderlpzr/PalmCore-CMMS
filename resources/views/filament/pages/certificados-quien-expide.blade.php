{{-- Quién expide hoy, con su firma a la vista: lo primero que hay que confirmar antes de expedir. --}}
@php
    $resumen = $this->signerSummary();
    $firmante = $resumen['signer'];
@endphp
<div @class([
    'flex flex-wrap items-center gap-4 rounded-xl border px-4 py-3',
    'border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900' => $firmante,
    'border-warning-300 bg-warning-50 dark:border-warning-500/40 dark:bg-warning-500/10' => ! $firmante,
])>
    @if ($firmante)
        <div class="min-w-0 flex-1">
            <p class="text-xs text-gray-500 dark:text-gray-400">Expide</p>
            <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $firmante->fullName() }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $firmante->position ?: 'Sin cargo en la ficha' }}</p>
        </div>
        @if ($resumen['signature'])
            <img src="{{ $resumen['signature'] }}" alt="Firma de {{ $firmante->fullName() }}" class="h-12 max-w-48 rounded bg-white object-contain p-1">
        @else
            <p class="text-xs text-warning-700 dark:text-warning-400">Sin firma escaneada: los certificados salen para firmar a mano.</p>
        @endif
    @else
        <p class="text-sm text-warning-700 dark:text-warning-300">
            Todavía nadie expide los certificados. Elíjalo en <strong>«Quién expide»</strong>, arriba a la derecha, y suba su firma.
        </p>
    @endif
</div>
