@php
    $datos = $this->data();
    $tanques = $this->tanks();
    $nombres = $this->tankNames();
    $ultimo = $datos['latest'];
    $limite = \App\Domain\Commercial\Services\OilTankService::ACIDITY_LIMIT;
    $tn = fn (?float $v): string => $v === null ? '—' : number_format($v, 2, ',', '.');
    $acidez = fn (?float $v): string => $v === null ? '—' : number_format($v, 2, ',', '.').' %';
    $columnas = collect($datos['days'])->flatMap(fn ($d) => array_keys($d['tanks']))->unique()->values();
    $columnas = collect(array_keys($nombres))->filter(fn ($id) => $columnas->contains($id))->values();
    $celda = 'text-right tabular-nums px-2 py-1.5 border-b border-gray-100 dark:border-white/5 whitespace-nowrap';
    $celdaTh = 'text-right font-semibold px-2 py-2 border-b border-gray-200 dark:border-white/10 whitespace-nowrap';
@endphp

<x-filament-panels::page>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-fronda.kpi label="Aceite en tanques" :value="$ultimo ? $tn($ultimo['total']).' tn' : 'Sin medir'" icon="heroicon-o-beaker"
            :detail="$ultimo ? 'Medido el '.$ultimo['date']->format('d/m/Y') : 'Registre la primera medición abajo'" />
        @if ($ultimo && $ultimo['stale'])
            <x-fronda.kpi label="Disponible para vender" value="Hay que medir" icon="heroicon-o-truck" tone="warning"
                :detail="'La última medición es de hace '.$ultimo['age_days'].' días: con ella no se sabe cuánto queda'" />
        @else
            <x-fronda.kpi label="Disponible para vender" :value="$ultimo ? $tn($ultimo['available']).' tn' : '—'" icon="heroicon-o-truck"
                :tone="$ultimo && $ultimo['available'] < 0 ? 'danger' : 'neutral'"
                :detail="$ultimo ? ($ultimo['dispatched_since'] > 0 ? 'Menos '.$tn($ultimo['dispatched_since']).' tn cargadas después de medir' : 'Nada cargado desde la medición') : null" />
        @endif
        <x-fronda.kpi label="Acidez de la mezcla" :value="$ultimo ? $acidez($ultimo['acidity']) : '—'" icon="heroicon-o-exclamation-triangle"
            :tone="$ultimo && $ultimo['acidity'] !== null && $ultimo['acidity'] > $limite ? 'danger' : 'ok'"
            :detail="'Ponderada por lo que tiene cada tanque · límite '.number_format($limite, 0).' %'" />
        <x-fronda.kpi :label="'Producción de '.$this->monthStart()->locale('es')->translatedFormat('F')" :value="$tn($datos['production']).' tn'" icon="heroicon-o-chart-bar"
            :detail="$datos['expected'] !== null ? 'El plan esperaba '.$tn($datos['expected']).' tn en el mes' : 'Calculada con los tanques y los despachos'" />
    </div>

    @if ($tanques->isEmpty())
        <x-fronda.empty-state icon="heroicon-o-beaker" heading="Todavía no hay tanques">
            Créelos en «Tanques» con su nombre y capacidad: Tanque 1, Tanque 2…
        </x-fronda.empty-state>
    @elseif ($this->canRecord())
        <x-filament::section>
            <x-slot name="heading">Medición del día</x-slot>
            <x-slot name="description">
                Las toneladas y la acidez de cada tanque al cierre del día, después de los cargues. La producción se calcula sola: lo que subieron los tanques más lo que se despachó.
            </x-slot>

            <form wire:submit="guardar" class="space-y-4">
                <div class="flex flex-wrap items-end gap-4">
                    <label class="space-y-1">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Fecha</span>
                        <x-filament::input.wrapper class="w-44">
                            <x-filament::input type="date" wire:model.live="date" :max="$this->today()->toDateString()" />
                        </x-filament::input.wrapper>
                    </label>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($tanques as $tanque)
                        <div wire:key="medir-{{ $tanque->id }}" class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                            <p class="font-semibold text-gray-950 dark:text-white">{{ $tanque->name }}</p>
                            @if ($tanque->capacity_tons)
                                <p class="text-xs text-gray-500 dark:text-gray-400">Capacidad {{ $tn((float) $tanque->capacity_tons) }} tn</p>
                            @endif
                            <div class="mt-3 grid grid-cols-2 gap-3">
                                <label class="space-y-1">
                                    <span class="text-xs text-gray-600 dark:text-gray-300">Nivel</span>
                                    <x-filament::input.wrapper suffix="tn">
                                        <x-filament::input type="text" inputmode="decimal" wire:model="levels.{{ $tanque->id }}.level" />
                                    </x-filament::input.wrapper>
                                </label>
                                <label class="space-y-1">
                                    <span class="text-xs text-gray-600 dark:text-gray-300">Acidez</span>
                                    <x-filament::input.wrapper suffix="%">
                                        <x-filament::input type="text" inputmode="decimal" wire:model="levels.{{ $tanque->id }}.acidity" />
                                    </x-filament::input.wrapper>
                                </label>
                            </div>
                        </div>
                    @endforeach
                </div>

                <x-filament::button type="submit" icon="heroicon-m-check">Guardar medición</x-filament::button>
            </form>
        </x-filament::section>
    @endif

    <x-filament::section>
        <x-slot name="heading">Mediciones del mes</x-slot>
        <x-slot name="afterHeader">
            <x-filament::input.wrapper class="w-44">
                <x-filament::input type="month" wire:model.live="month" aria-label="Mes" />
            </x-filament::input.wrapper>
        </x-slot>

        @if ($datos['days'] === [])
            <p class="text-sm text-gray-500 dark:text-gray-400">No hay mediciones en este mes.</p>
        @else
            <div class="overflow-x-auto -mx-2 px-2">
                <table class="w-full text-sm border-separate border-spacing-0">
                    <thead>
                        <tr class="text-gray-600 dark:text-gray-300">
                            <th class="text-left font-semibold px-2 py-2 border-b border-gray-200 dark:border-white/10">Fecha</th>
                            @foreach ($columnas as $id)
                                <th class="{{ $celdaTh }}">{{ $nombres[$id] ?? 'Tanque' }}</th>
                            @endforeach
                            <th class="{{ $celdaTh }} border-l">Total (tn)</th>
                            <th class="{{ $celdaTh }}">Acidez</th>
                            <th class="{{ $celdaTh }}">Despachado (tn)</th>
                            <th class="{{ $celdaTh }}">Producción (tn)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($datos['days'] as $dia)
                            <tr wire:key="dia-{{ $dia['date']->toDateString() }}">
                                <td class="px-2 py-1.5 border-b border-gray-100 dark:border-white/5 whitespace-nowrap">{{ $dia['date']->format('d/m/Y') }}</td>
                                @foreach ($columnas as $id)
                                    @php $tanque = $dia['tanks'][$id] ?? null; @endphp
                                    <td class="{{ $celda }}">
                                        @if ($tanque)
                                            <span class="block">{{ $tn($tanque['level']) }}</span>
                                            <span @class(['block text-xs', 'text-danger-600 dark:text-danger-400 font-semibold' => $tanque['acidity'] !== null && $tanque['acidity'] > $limite, 'text-gray-400 dark:text-gray-500' => $tanque['acidity'] === null || $tanque['acidity'] <= $limite])>{{ $acidez($tanque['acidity']) }}</span>
                                        @else
                                            <span class="text-gray-400">sin medir</span>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="{{ $celda }} border-l font-semibold">{{ $tn($dia['total']) }}</td>
                                <td @class([$celda, 'text-danger-600 dark:text-danger-400 font-semibold' => $dia['acidity'] !== null && $dia['acidity'] > $limite])>{{ $acidez($dia['acidity']) }}</td>
                                <td class="{{ $celda }}">{{ $dia['dispatched'] > 0 ? $tn($dia['dispatched']) : '' }}</td>
                                <td @class([$celda, 'font-semibold', 'text-danger-600 dark:text-danger-400' => $dia['production'] !== null && $dia['production'] < 0])>
                                    @if ($dia['production'] === null)
                                        <span class="text-xs font-normal text-gray-400" title="Es la primera medición, o no se midieron los mismos tanques que la vez anterior">—</span>
                                    @else
                                        {{ $tn($dia['production']) }}
                                        @if ($dia['days'] > 1)
                                            <span class="block text-xs font-normal text-gray-400">en {{ $dia['days'] }} días</span>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                Producción = total de hoy − total de la medición anterior + lo despachado entre las dos (peso de salida). Una producción negativa casi siempre es un nivel mal medido o un cargue sin registrar.
            </p>
        @endif
    </x-filament::section>
</x-filament-panels::page>
