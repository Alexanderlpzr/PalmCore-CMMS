{{--
    El checklist «Documentos pendientes» de la hoja Items: qué tiene la carpeta y qué
    le falta, arriba de la tabla, para no tener que contarlo fila por fila.
--}}
@php
    /** @var \App\Models\Employee $employee */
    $missing = collect($employee->missingRequiredDocuments());
    $required = \App\Domain\HumanResources\Enums\EmployeeDocumentType::required();
    $done = count($required) - $missing->count();
@endphp

<div class="flex flex-col gap-3 px-4 py-3 sm:px-6">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm font-medium text-gray-950 dark:text-white">
            @if ($missing->isEmpty())
                Carpeta completa: los {{ count($required) }} documentos obligatorios están cargados.
            @else
                {{ $done }} de {{ count($required) }} documentos obligatorios
                <span class="font-normal text-gray-500 dark:text-gray-400">
                    · faltan {{ $missing->count() }}
                </span>
            @endif
        </p>

        <x-filament::badge :color="$missing->isEmpty() ? 'success' : ($done === 0 ? 'danger' : 'warning')">
            {{ $done }}/{{ count($required) }}
        </x-filament::badge>
    </div>

    <div class="flex flex-wrap gap-1.5">
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
    </div>
</div>
