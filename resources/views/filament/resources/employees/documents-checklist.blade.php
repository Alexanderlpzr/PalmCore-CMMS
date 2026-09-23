{{--
    El checklist «Documentos pendientes» de la hoja Items: qué tiene la carpeta y qué le
    falta, para no tener que contarlo fila por fila.

    Va como descripción de la tabla y no como cabecera propia: la cabecera de Filament es
    una sola, y ocuparla dejaba fuera el botón de subir documentos. Por eso aquí todo es
    en línea —spans, no divs—, que es lo que cabe dentro del párrafo de la descripción.
--}}
@php
    /** @var \App\Models\Employee $employee */
    $missing = collect($employee->missingRequiredDocuments());
    $required = \App\Domain\HumanResources\Enums\EmployeeDocumentType::required();
    $done = count($required) - $missing->count();
@endphp

<span class="fi-ta-header-description">
    <span class="font-medium text-gray-950 dark:text-white">
        @if ($missing->isEmpty())
            Carpeta completa: los {{ count($required) }} documentos obligatorios están cargados.
        @else
            {{ $done }} de {{ count($required) }} documentos obligatorios · faltan {{ $missing->count() }}
        @endif
    </span>

    <span class="mt-2 flex flex-wrap gap-1.5">
        @foreach ($required as $type)
            @php($isMissing = $missing->contains($type))
            <x-filament::badge
                :color="$isMissing ? 'gray' : 'success'"
                :icon="$isMissing ? 'heroicon-m-clock' : 'heroicon-m-check-circle'"
                :tooltip="$type->label() . ($isMissing ? ' — pendiente' : ' — cargado')"
            >
                {{ $type->shortLabel() }}
            </x-filament::badge>
        @endforeach
    </span>
</span>
