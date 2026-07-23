<div>
    @if ($submitted)
        {{-- Confirmation state --}}
        <div class="bg-emerald-50 border border-emerald-200 rounded-2xl px-5 py-6 text-center">
            <div class="text-3xl mb-2">✅</div>
            <h3 class="text-base font-semibold text-emerald-800 mb-1">Novedad registrada</h3>
            <p class="text-sm text-emerald-600">El equipo de mantenimiento ha sido notificado.</p>
            <button
                wire:click="$set('submitted', false)"
                class="mt-4 text-sm text-emerald-700 underline underline-offset-2"
            >
                Reportar otra novedad
            </button>
        </div>
    @else
        {{-- Report form --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 px-5 py-5">
            <h2 class="text-base font-semibold text-gray-900 mb-4">Reportar novedad</h2>

            <form wire:submit.prevent="submit" class="space-y-4">

                {{-- Severity --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Severidad</label>
                    <div class="grid grid-cols-4 gap-2">
                        @foreach ($this->getSeverityOptions() as $value => $label)
                            <label class="cursor-pointer">
                                <input
                                    type="radio"
                                    wire:model="severity"
                                    value="{{ $value }}"
                                    class="sr-only peer"
                                >
                                <span class="block text-center text-xs font-semibold py-2 rounded-lg border-2 transition-colors
                                    peer-checked:border-emerald-500 peer-checked:bg-emerald-50 peer-checked:text-emerald-700
                                    border-gray-200 text-gray-600 hover:border-gray-300">
                                    {{ $label }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('severity') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Description --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Descripción <span class="text-red-500">*</span>
                    </label>
                    <textarea
                        wire:model="description"
                        rows="4"
                        placeholder="Describa la falla o novedad observada..."
                        class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:border-transparent"
                    ></textarea>
                    @error('description') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Foto: se abre la cámara del celular directamente (capture) --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Foto <span class="text-gray-400">(opcional)</span></label>
                    <label class="flex items-center justify-center gap-2 w-full border-2 border-dashed border-gray-300 rounded-xl py-3 text-sm text-gray-600 cursor-pointer hover:border-emerald-400 transition-colors">
                        <span class="text-lg">📷</span>
                        <span wire:loading.remove wire:target="photo">{{ $photo ? 'Cambiar foto' : 'Tomar o subir foto' }}</span>
                        <span wire:loading wire:target="photo">Cargando...</span>
                        <input
                            type="file"
                            wire:model="photo"
                            accept="image/*"
                            capture="environment"
                            class="sr-only"
                        >
                    </label>
                    @if ($photo && $photo->isPreviewable())
                        <img src="{{ $photo->temporaryUrl() }}" alt="Vista previa" class="mt-2 h-40 w-full rounded-xl object-cover">
                    @endif
                    @error('photo') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Datos del reportante: obligatorios --}}
                <div class="space-y-3 border-t border-gray-100 pt-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Nombre <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            wire:model="reporterName"
                            placeholder="Tu nombre completo"
                            class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:border-transparent"
                        >
                        @error('reporterName') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Cargo <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            wire:model="reporterPosition"
                            placeholder="Ej: Operario de planta, Supervisor de turno..."
                            class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:border-transparent"
                        >
                        @error('reporterPosition') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- Submit --}}
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    class="w-full bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white text-sm font-semibold py-3 rounded-xl transition-colors"
                >
                    <span wire:loading.remove>Enviar reporte</span>
                    <span wire:loading>Enviando...</span>
                </button>

            </form>
        </div>
    @endif
</div>
