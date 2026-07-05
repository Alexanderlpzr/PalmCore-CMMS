<div class="p-2 space-y-5">

    @if ($qrCode && $qrCode->qr_image_path)
        {{-- QR Image --}}
        <div class="flex justify-center">
            <div class="bg-white p-3 rounded-xl shadow-inner border border-gray-100 inline-block">
                <img
                    src="{{ $qrCode->imageUrl() }}"
                    alt="QR {{ $equipment->code }}"
                    class="w-48 h-48"
                >
            </div>
        </div>

        {{-- Equipment label (mimics the printed sticker) --}}
        <div class="text-center">
            <p class="text-xs font-mono font-bold text-gray-500 tracking-widest uppercase">{{ $equipment->code }}</p>
            <p class="text-sm font-semibold text-gray-800 mt-0.5">{{ $equipment->name }}</p>
            @if ($equipment->area)
                <p class="text-xs text-gray-400 mt-0.5">{{ $equipment->plant?->name }} — {{ $equipment->area->name }}</p>
            @endif
        </div>

        {{-- URL --}}
        <div class="bg-gray-50 rounded-lg px-3 py-2 flex items-center gap-2">
            <code class="text-xs text-gray-600 flex-1 truncate">{{ $qrCode->publicUrl() }}</code>
            <button
                onclick="navigator.clipboard.writeText('{{ $qrCode->publicUrl() }}').then(() => this.innerText = '✓').catch(() => {}); setTimeout(() => this.innerText = 'Copiar', 1500)"
                class="text-xs text-emerald-600 font-medium hover:text-emerald-800 flex-shrink-0"
            >Copiar</button>
        </div>

        {{-- Stats --}}
        <div class="grid grid-cols-2 gap-3 text-center">
            <div class="bg-gray-50 rounded-lg px-3 py-2">
                <p class="text-xs text-gray-400">Escaneos</p>
                <p class="text-lg font-bold text-gray-700">{{ number_format($qrCode->scan_count) }}</p>
            </div>
            <div class="bg-gray-50 rounded-lg px-3 py-2">
                <p class="text-xs text-gray-400">Último escaneo</p>
                <p class="text-xs font-semibold text-gray-700 mt-1">
                    {{ $qrCode->last_scanned_at?->diffForHumans() ?? 'Nunca' }}
                </p>
            </div>
        </div>

        {{-- Action buttons --}}
        <div class="flex gap-2">
            <a
                href="{{ $qrCode->imageUrl() }}"
                download="{{ $equipment->code }}_qr.png"
                class="flex-1 text-center bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium py-2 px-4 rounded-lg transition-colors"
            >
                Descargar PNG
            </a>
            <a
                href="{{ $qrCode->publicUrl() }}"
                target="_blank"
                class="flex-1 text-center bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium py-2 px-4 rounded-lg transition-colors"
            >
                Abrir página
            </a>
        </div>

        {{-- Regenerate action --}}
        <div class="pt-2 border-t border-gray-100 text-center">
            {{ $action->getModalAction('regenerate') }}
        </div>

    @else
        {{-- QR not yet generated — polls automatically until the queued job finishes --}}
        <div class="text-center py-4" wire:poll.3s="$refresh">
            <div class="text-4xl mb-3">⏳</div>
            <p class="text-sm font-medium text-gray-700">QR en generación</p>
            <p class="text-xs text-gray-400 mt-1">El QR se genera automáticamente tras crear el equipo.<br>Esta ventana se actualiza sola cuando esté listo.</p>

            <div class="mt-4">
                {{ $action->getModalAction('regenerate') }}
            </div>
        </div>
    @endif

</div>
