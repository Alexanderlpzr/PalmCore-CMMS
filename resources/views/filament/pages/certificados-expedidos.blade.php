{{-- Los últimos certificados expedidos: cada uno está también en la carpeta de su trabajador. --}}
@php($expedidos = $this->issuedCertificates())
<x-filament::section heading="Últimos expedidos" description="Cada uno queda también en la carpeta del trabajador, en Personal.">
    @if ($expedidos->isEmpty())
        <p class="text-sm text-gray-500 dark:text-gray-400">Todavía no se ha expedido ningún certificado.</p>
    @else
        <ul class="divide-y divide-gray-100 dark:divide-white/5">
            @foreach ($expedidos as $documento)
                <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-gray-950 dark:text-white">{{ $documento->employee?->fullName() }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $documento->title }}
                            · {{ $documento->created_at?->setTimezone(\Filament\Facades\Filament::getTenant()?->plantTimezone() ?? 'America/Bogota')->format('d/m/Y h:i a') }}
                            @if ($documento->uploadedBy)
                                · {{ $documento->uploadedBy->name }}
                            @endif
                        </p>
                    </div>
                    <x-filament::link :href="route('employee-documents.download', $documento)" icon="heroicon-o-arrow-down-tray" size="sm">
                        Descargar
                    </x-filament::link>
                </li>
            @endforeach
        </ul>
    @endif
</x-filament::section>
