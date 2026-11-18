@php
    $plan = $this->plan();
    $editable = $this->editable();
    $versiones = $this->versions();
    $tn = fn (?float $v): string => $v === null ? '—' : number_format($v, 2, ',', '.');
@endphp

<x-filament-panels::page>
    <div class="flex flex-wrap items-center gap-3">
        <x-filament::input.wrapper class="w-32">
            <x-filament::input.select wire:model.live="year" aria-label="Año">
                @foreach ($this->years() as $anio)
                    <option value="{{ $anio }}">{{ $anio }}</option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>

        @if (count($versiones) > 0)
            <x-filament::tabs>
                @foreach ($versiones as $valor => $nombre)
                    <x-filament::tabs.item :active="$this->version === $valor" wire:click="$set('version', '{{ $valor }}')"
                        :icon="$valor === 'original' ? 'heroicon-m-lock-closed' : 'heroicon-m-pencil-square'">
                        {{ $nombre }}
                    </x-filament::tabs.item>
                @endforeach
            </x-filament::tabs>
        @endif

        @if ($plan)
            <span class="text-sm text-gray-500 dark:text-gray-400">
                @if ($plan->approved_at)
                    Aprobado el {{ $plan->approved_at->timezone('America/Bogota')->format('d/m/Y') }}{{ $plan->approvedBy ? ' por '.$plan->approvedBy->name : '' }}.
                @elseif ($plan->version->value === 'original')
                    Borrador: se puede ajustar hasta aprobarlo.
                @endif
                @if ($plan->version->value === 'reproyeccion')
                    La reproyección se ajusta durante el año; el original queda como referencia.
                @endif
            </span>
        @endif
    </div>

    @if (! $plan)
        <x-fronda.empty-state icon="heroicon-o-calendar-days" :heading="'Todavía no hay plan para '.$this->year">
            Créelo con «Crear plan {{ $this->year }}» y llene la fruta, la planta y las ventas, o tráigalo de su Excel con «Importar del Excel».
        </x-fronda.empty-state>
    @else
        @php
            $datos = $this->data();
            $meses = $datos['months'];
            $columna = fn (string $campo): array => array_map(fn (array $m) => $m[$campo], $meses);
        @endphp

        <x-filament::tabs>
            @foreach (['resumen' => ['Resumen', 'heroicon-m-presentation-chart-line'], 'fruta' => ['Fruta', 'heroicon-m-sun'], 'ventas' => ['Ventas', 'heroicon-m-truck'], 'planta' => ['Planta', 'heroicon-m-cog-6-tooth']] as $hoja => [$nombre, $icono])
                <x-filament::tabs.item :active="$this->tab === $hoja" wire:click="$set('tab', '{{ $hoja }}')" :icon="$icono">
                    {{ $nombre }}
                </x-filament::tabs.item>
            @endforeach
        </x-filament::tabs>

        @if ($this->tab === 'resumen')
            @php
                $totalCpo = array_sum($columna('cpo'));
                $totalVentas = array_sum($columna('sales_cpo'));
                $diciembre = $meses[12]['cumulative'];
            @endphp
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-fronda.kpi label="Fruta proyectada" :value="$tn(array_sum($columna('fruit'))).' tn'" icon="heroicon-o-sun" :detail="'Recibida en el año: '.$tn(array_sum($columna('real_fruit'))).' tn'" />
                <x-fronda.kpi label="Aceite esperado (CPO)" :value="$tn($totalCpo).' tn'" icon="heroicon-o-beaker" detail="Fruta × TEA de cada mes" />
                <x-fronda.kpi label="Ventas de aceite planeadas" :value="$tn($totalVentas).' tn'" icon="heroicon-o-truck" :detail="$totalCpo > 0 ? number_format($totalVentas / $totalCpo * 100, 1, ',', '.').' % del aceite esperado' : null" />
                <x-fronda.kpi label="Saldo de aceite a diciembre" :value="$tn($diciembre).' tn'" icon="heroicon-o-scale"
                    :tone="$diciembre < 0 ? 'danger' : 'ok'"
                    :detail="$diciembre < 0 ? 'Se vende más aceite del que saldría: comprar a terceros o mover ventas' : 'Inventario inicial + aceite − ventas'" />
            </div>

            <x-filament::section>
                <x-slot name="heading">Aceite: ¿alcanza para lo que se va a vender?</x-slot>
                <x-slot name="description">
                    El aceite esperado es la fruta del plan por la TEA de cada mes. Una diferencia negativa (en rojo) es un mes con más ventas que aceite propio.
                    El saldo arranca con el inventario inicial ({{ $tn((float) $plan->opening_oil_tons) }} tn).
                </x-slot>
                @include('filament.pages.partials.grilla-mensual', [
                    'clave' => 'resumen-aceite',
                    'editable' => false,
                    'encabezado' => 'tn',
                    'filas' => [
                        ['key' => 'fruta', 'label' => 'Fruta proyectada', 'values' => $columna('fruit')],
                        ['key' => 'real', 'label' => 'Fruta recibida', 'hint' => 'Según «Fruta recibida»', 'values' => $columna('real_fruit')],
                        ['key' => 'cpo', 'label' => 'Aceite esperado (CPO)', 'values' => $columna('cpo'), 'strong' => true],
                        ['key' => 'ventas', 'label' => 'Ventas de aceite planeadas', 'values' => $columna('sales_cpo')],
                        ['key' => 'dif', 'label' => 'Diferencia', 'values' => $columna('balance'), 'tone' => fn (float $v): ?string => $v < 0 ? 'danger' : null],
                        ['key' => 'saldo', 'label' => 'Saldo acumulado', 'values' => $columna('cumulative'), 'total' => false, 'strong' => true, 'tone' => fn (float $v): ?string => $v < 0 ? 'danger' : null],
                    ],
                ])
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Planta y palmiste</x-slot>
                <x-slot name="description">Cuánto de la capacidad usa la fruta del plan, y el palmiste (PKO) que saldría de la almendra.</x-slot>
                @include('filament.pages.partials.grilla-mensual', [
                    'clave' => 'resumen-planta',
                    'editable' => false,
                    'encabezado' => 'tn',
                    'filas' => [
                        ['key' => 'capacidad', 'label' => 'Capacidad de la planta', 'values' => $columna('capacity')],
                        ['key' => 'uso', 'label' => '% de la capacidad', 'values' => array_map(fn ($v) => $v === null ? null : $v * 100, $columna('utilization')), 'total' => false, 'tone' => fn (float $v): ?string => $v > 100 ? 'danger' : null],
                        ['key' => 'almendra', 'label' => 'Almendra', 'values' => $columna('kernel')],
                        ['key' => 'pko', 'label' => 'Palmiste esperado (PKO)', 'values' => $columna('pko'), 'strong' => true],
                        ['key' => 'ventas-pko', 'label' => 'Ventas de palmiste planeadas', 'values' => $columna('sales_pko')],
                    ],
                ])
            </x-filament::section>
        @endif

        @if ($this->tab === 'fruta')
            @php
                $proveedores = $this->suppliers($datos['fruit']);
                $filas = $proveedores->map(fn ($p) => [
                    'key' => $p->id,
                    'label' => $p->name,
                    'values' => $datos['fruit'][$p->id] ?? [],
                    'call' => fn (int $m): string => "guardarFruta('{$p->id}', {$m}, \$event.target.value)",
                ])->all();
                $filas[] = ['key' => 'total', 'label' => 'Total fruta proyectada', 'values' => $columna('fruit'), 'strong' => true];
            @endphp
            <x-filament::section>
                <x-slot name="heading">Fruta proyectada por proveedor (tn)</x-slot>
                <x-slot name="description">
                    @if ($editable) Escriba la cifra y salga de la celda: se guarda sola. Vacía o en cero, se borra. @endif
                    Los proveedores se administran en «Proveedores de fruta».
                </x-slot>
                @if ($proveedores->isEmpty())
                    <x-fronda.empty-state icon="heroicon-o-user-group" heading="No hay proveedores de fruta">
                        Créelos en «Proveedores de fruta» o importe el Excel.
                    </x-fronda.empty-state>
                @else
                    @include('filament.pages.partials.grilla-mensual', ['clave' => 'fruta', 'editable' => $editable, 'encabezado' => 'Proveedor', 'filas' => $filas])
                @endif
            </x-filament::section>
        @endif

        @if ($this->tab === 'ventas')
            @foreach ([\App\Domain\Commercial\Enums\CommercialProduct::Cpo, \App\Domain\Commercial\Enums\CommercialProduct::Pko] as $producto)
                @php
                    $ventas = $producto->value === 'cpo' ? $datos['cpo'] : $datos['pko'];
                    $filas = collect($this->salesRows($producto, $ventas))->map(fn ($nombre, $id) => [
                        'key' => $producto->value.'-'.$id,
                        'label' => $nombre,
                        'values' => $ventas[$id] ?? [],
                        'call' => fn (int $m): string => "guardarVenta('{$id}', '{$producto->value}', {$m}, \$event.target.value)",
                    ])->values()->all();
                    $filas[] = ['key' => $producto->value.'-total', 'label' => 'Total', 'values' => $producto->value === 'cpo' ? $columna('sales_cpo') : $columna('sales_pko'), 'strong' => true];
                @endphp
                <x-filament::section>
                    <x-slot name="heading">Ventas planeadas · {{ $producto->label() }} (tn)</x-slot>
                    @if ($producto->value === 'cpo')
                        <x-slot name="description">Una tractomula son unas 34 tn. «Por concretar» es lo que se espera vender sin comprador todavía.</x-slot>
                    @endif
                    @include('filament.pages.partials.grilla-mensual', ['clave' => 'ventas-'.$producto->value, 'editable' => $editable, 'encabezado' => 'Cliente', 'filas' => $filas])

                    @if ($producto->value === 'pko' && $editable)
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <x-filament::input.wrapper class="w-64">
                                <x-filament::input.select wire:model="pkoCustomerToAdd" aria-label="Cliente de palmiste">
                                    <option value="">Agregar un cliente…</option>
                                    @foreach ($this->customers() as $id => $nombre)
                                        <option value="{{ $id }}">{{ $nombre }}</option>
                                    @endforeach
                                </x-filament::input.select>
                            </x-filament::input.wrapper>
                            <x-filament::button size="sm" color="gray" wire:click="agregarClientePalmiste" icon="heroicon-m-plus">Agregar fila</x-filament::button>
                        </div>
                    @endif
                </x-filament::section>
            @endforeach
        @endif

        @if ($this->tab === 'planta')
            @php
                $parametro = fn (string $campo, string $nombre, ?string $pista = null) => [
                    'key' => $campo,
                    'label' => $nombre,
                    'hint' => $pista,
                    'values' => $columna(match ($campo) { 'plant_capacity_tons' => 'capacity', 'tea_percent' => 'tea', default => $campo }),
                    'call' => fn (int $m): string => "guardarParametro('{$campo}', {$m}, \$event.target.value)",
                    'total' => false,
                ];
            @endphp
            <x-filament::section>
                <x-slot name="heading">Parámetros de la planta</x-slot>
                <x-slot name="description">
                    La capacidad en toneladas de fruta, la TEA esperada (el aceite que sale por cada 100 tn de fruta) y las tasas del subproducto: la almendra sobre la fruta y el PKO sobre la almendra.
                </x-slot>
                @include('filament.pages.partials.grilla-mensual', [
                    'clave' => 'planta',
                    'editable' => $editable,
                    'encabezado' => 'Parámetro',
                    'conTotal' => false,
                    'filas' => [
                        $parametro('plant_capacity_tons', 'Capacidad (tn de fruta)'),
                        $parametro('tea_percent', 'TEA (%)', 'Aceite / fruta'),
                        $parametro('kernel_percent', 'Almendra (%)', 'Sobre la fruta'),
                        $parametro('pko_percent', 'PKO (%)', 'Sobre la almendra'),
                    ],
                ])
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Inventario inicial de aceite</x-slot>
                <x-slot name="description">El aceite en tanques al 1 de enero: es el punto de partida del saldo acumulado.</x-slot>
                <div class="flex items-center gap-2">
                    @if ($editable)
                        <x-filament::input.wrapper class="w-40" suffix="tn">
                            <x-filament::input type="text" inputmode="decimal" :value="$plan->opening_oil_tons === null ? '' : number_format((float) $plan->opening_oil_tons, 2, ',', '')" wire:change="guardarInventarioInicial($event.target.value)" aria-label="Inventario inicial de aceite" />
                        </x-filament::input.wrapper>
                    @else
                        <span class="text-lg font-semibold tabular-nums">{{ $tn((float) $plan->opening_oil_tons) }} tn</span>
                    @endif
                </div>
            </x-filament::section>
        @endif
    @endif
</x-filament-panels::page>
