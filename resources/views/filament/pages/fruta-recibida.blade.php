@php
    $datos = $this->data();
    $report = app(\App\Domain\Commercial\Services\CommercialPlanReport::class);
    $proveedores = $this->suppliers($datos['real'] + $datos['plan']);
    $editable = $this->canManage();
    $real = $report->monthTotals($datos['real']);
    $proyectado = $report->monthTotals($datos['plan']);
    $procesada = $datos['processed'];
    $diferencia = array_map(fn (int $m): ?float => $real[$m] > 0 || $procesada[$m] > 0 ? round($real[$m] - $procesada[$m], 2) : null, range(1, 12));
    $diferencia = array_combine(range(1, 12), $diferencia);
    $cumplimiento = array_combine(range(1, 12), array_map(fn (int $m): ?float => $proyectado[$m] > 0 && $real[$m] > 0 ? $real[$m] / $proyectado[$m] * 100 : null, range(1, 12)));
    $tn = fn (float $v): string => number_format($v, 2, ',', '.');
    $totalReal = array_sum($real);
    $totalProyectado = array_sum($proyectado);

    $filas = $proveedores->map(fn ($p) => [
        'key' => $p->id,
        'label' => $p->name,
        'values' => $datos['real'][$p->id] ?? [],
        'call' => fn (int $m): string => "guardar('{$p->id}', {$m}, \$event.target.value)",
    ])->all();
    $filas[] = ['key' => 'total', 'label' => 'Total fruta recibida', 'values' => $real, 'strong' => true];
@endphp

<x-filament-panels::page>
    <div class="flex flex-wrap items-center gap-3">
        <x-filament::input.wrapper class="w-32">
            <x-filament::input type="number" wire:model.live.debounce.500ms="year" min="2020" max="2100" aria-label="Año" />
        </x-filament::input.wrapper>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <x-fronda.kpi label="Fruta recibida" :value="$tn($totalReal).' tn'" icon="heroicon-o-scale" :detail="'En '.$this->year.', por proveedor'" />
        <x-fronda.kpi label="Fruta proyectada" :value="$tn($totalProyectado).' tn'" icon="heroicon-o-calendar-days" detail="Del plan vigente del año" />
        <x-fronda.kpi label="Cumplimiento" :value="$totalProyectado > 0 ? number_format($totalReal / $totalProyectado * 100, 1, ',', '.').' %' : '—'" icon="heroicon-o-check-badge"
            :detail="$totalProyectado > 0 ? 'Faltan '.$tn(max($totalProyectado - $totalReal, 0)).' tn por entregar en el año' : 'Sin plan para el año'" />
    </div>

    <x-filament::section>
        <x-slot name="heading">Entrega real por proveedor (tn)</x-slot>
        <x-slot name="description">
            @if ($editable) La cifra del mes según la báscula: escríbala y salga de la celda, se guarda sola. @endif
        </x-slot>
        @if ($proveedores->isEmpty())
            <x-fronda.empty-state icon="heroicon-o-user-group" heading="No hay proveedores de fruta">
                Créelos con «Nuevo proveedor» o importe el plan del Excel en «Plan anual».
            </x-fronda.empty-state>
        @else
            @include('filament.pages.partials.grilla-mensual', ['clave' => 'recibida', 'editable' => $editable, 'encabezado' => 'Proveedor', 'filas' => $filas])
        @endif
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">¿Cuadra con la planta y con el plan?</x-slot>
        <x-slot name="description">
            La fruta procesada sale del calendario de producción que lleva la planta. Una diferencia grande es fruta que se recibió y no se procesó en el mes, o un mes sin digitar.
        </x-slot>
        @include('filament.pages.partials.grilla-mensual', [
            'clave' => 'cuadre',
            'editable' => false,
            'encabezado' => 'tn',
            'filas' => [
                ['key' => 'recibida', 'label' => 'Fruta recibida', 'values' => $real, 'strong' => true],
                ['key' => 'procesada', 'label' => 'Fruta procesada en planta', 'values' => $procesada],
                ['key' => 'diferencia', 'label' => 'Recibida − procesada', 'values' => $diferencia],
                ['key' => 'proyectada', 'label' => 'Fruta proyectada', 'values' => $proyectado],
                ['key' => 'cumplimiento', 'label' => '% cumplimiento', 'values' => $cumplimiento, 'total' => false, 'tone' => fn (float $v): ?string => $v < 90 ? 'danger' : ($v >= 100 ? 'success' : null)],
            ],
        ])
    </x-filament::section>
</x-filament-panels::page>
