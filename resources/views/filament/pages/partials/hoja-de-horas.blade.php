@php
    use App\Domain\Reports\Services\FormatoHorasExtrasPdfService as Formato;
    use App\Support\FrondaPalette;

    $data = $this->data;
    $employee = $this->employee;
    $names = $this->names;
    $position = $this->position();
    $total = count($names);
    $showValues = $this->showValues();
    $tenant = $this->tenant;
    $verde = FrondaPalette::Brand[600];
    $hora = fn ($momento): string => $momento ? $momento->format('h:i a') : '';
    $horas = fn (float $valor): string => $valor > 0 ? rtrim(rtrim(number_format($valor, 2, ',', '.'), '0'), ',') : '';
    $pesos = fn (float $valor): string => '$ '.number_format($valor, 0, ',', '.');
    $celda = 'border border-gray-200 px-2 py-1.5 dark:border-white/10';
    $rotulo = 'border border-gray-200 bg-gray-50 px-2 py-1.5 text-left text-xs font-semibold text-gray-700 dark:border-white/10 dark:bg-white/5 dark:text-gray-200';
@endphp

{{--
    La hoja del trabajador en pantalla, con la misma forma del formato TH-NOM-F-001 que
    se descarga en Excel. Entre trabajadores se navega sin cerrar la ventana.
--}}
<div class="space-y-4">
    {{-- Pasar de hoja: anterior, la lista y siguiente. --}}
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="flex items-center gap-2">
            <x-filament::button size="sm" color="gray" icon="heroicon-m-chevron-left" wire:click="previous" :disabled="$position === 0">
                Anterior
            </x-filament::button>
            <x-filament::input.wrapper class="w-72">
                <x-filament::input.select wire:change="show($event.target.value)" aria-label="Trabajador">
                    @foreach ($names as $id => $nombre)
                        <option value="{{ $id }}" @selected($id === $this->employeeId)>{{ $nombre }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
            <x-filament::button size="sm" color="gray" icon="heroicon-m-chevron-right" icon-position="after" wire:click="next" :disabled="$position >= $total - 1">
                Siguiente
            </x-filament::button>
            <span class="text-xs text-gray-500 dark:text-gray-400">{{ $position + 1 }} de {{ $total }}</span>
        </div>
        <div class="flex items-center gap-2">
            <x-filament::button size="sm" color="gray" icon="heroicon-o-table-cells" wire:click="downloadExcel">Excel</x-filament::button>
            <x-filament::button size="sm" color="gray" icon="heroicon-o-document-arrow-down" wire:click="downloadPdf">PDF</x-filament::button>
        </div>
    </div>

    @if ($data && $employee)
        <div wire:loading.class="opacity-50" class="space-y-4 text-sm">
            {{-- Encabezado con el control documental --}}
            <table class="w-full border-collapse">
                <tr>
                    <td rowspan="2" class="px-3 py-2 text-center font-bold text-white" style="background-color: {{ $verde }}">
                        <p class="text-base">{{ mb_strtoupper($tenant?->legalName() ?? '') }}</p>
                        <p class="text-xs font-semibold">{{ Formato::FORM_TITLE }}</p>
                    </td>
                    <td class="{{ $rotulo }} w-48 text-center">CÓDIGO: {{ Formato::FORM_CODE }}</td>
                </tr>
                <tr>
                    <td class="{{ $rotulo }} text-center">VERSIÓN: {{ Formato::FORM_VERSION }} · FECHA: {{ Formato::FORM_DATE }}</td>
                </tr>
            </table>

            {{-- El trabajador --}}
            <table class="w-full border-collapse">
                <tr>
                    <th class="{{ $rotulo }} w-44">NOMBRE TRABAJADOR</th>
                    <td class="{{ $celda }} text-center font-medium">{{ mb_strtoupper($employee->fullName()) }}</td>
                    <th class="{{ $rotulo }} w-36">IDENTIFICACIÓN</th>
                    <td class="{{ $celda }} w-40 text-right tabular-nums">{{ $employee->document_number }}</td>
                </tr>
                <tr>
                    <th class="{{ $rotulo }}">CARGO</th>
                    <td class="{{ $celda }} text-center" @if (! $showValues) colspan="3" @endif>{{ mb_strtoupper($employee->position ?? '') }}</td>
                    @if ($showValues)
                        <th class="{{ $rotulo }}">SALARIO</th>
                        <td class="{{ $celda }} text-right tabular-nums">{{ $pesos((float) $data['salary']) }}</td>
                    @endif
                </tr>
                <tr>
                    <th class="{{ $rotulo }}">JEFE INMEDIATO</th>
                    <td class="{{ $celda }} text-center" colspan="3">{{ mb_strtoupper($data['supervisor'] ?? '') ?: '—' }}</td>
                </tr>
                <tr>
                    <th class="{{ $rotulo }}">PERÍODO</th>
                    <td class="{{ $celda }} text-center" colspan="3">
                        Horas del {{ $data['from']->format('d/m/Y') }} al {{ $data['to']->format('d/m/Y') }} — nómina de {{ $data['periodStart']->locale('es')->translatedFormat('F \d\e Y') }}
                    </td>
                </tr>
            </table>

            @if ($data['unconfirmed'] > 0)
                <p class="rounded-lg bg-warning-50 px-3 py-2 text-xs font-medium text-warning-700 dark:bg-warning-500/10 dark:text-warning-400">
                    {{ $data['unconfirmed'] }} {{ $data['unconfirmed'] === 1 ? 'día sigue' : 'días siguen' }} sin confirmar: lo que no se confirme no se paga.
                </p>
            @endif

            {{-- La cuadrícula: todos los días del corte --}}
            <div class="-mx-2 overflow-x-auto px-2">
                <table class="w-full min-w-[56rem] border-collapse text-xs">
                    <thead>
                        <tr class="text-white" style="background-color: {{ $verde }}">
                            <th class="border border-white/30 px-2 py-2 text-left font-semibold">FECHA</th>
                            <th class="border border-white/30 px-2 py-2 font-semibold">HORA INICIO</th>
                            <th class="border border-white/30 px-2 py-2 font-semibold">FIN HORARIO LABORAL</th>
                            <th class="border border-white/30 px-2 py-2 font-semibold">HORA SALIDA</th>
                            @foreach (array_keys($data['columns']) as $bolsa)
                                <th class="border border-white/30 px-2 py-2 font-semibold" title="{{ Formato::COLUMN_TITLES[$bolsa] }}">{{ Formato::COLUMN_TITLES[$bolsa] }}</th>
                            @endforeach
                            @foreach ($data['bonusColumns'] as $bolsa)
                                <th class="border border-white/30 px-2 py-2 font-semibold">BONO {{ Formato::COLUMNS[$bolsa] }}</th>
                            @endforeach
                            <th class="border border-white/30 px-2 py-2 text-left font-semibold">JUSTIFICACIÓN</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($data['rows'] as $dia)
                            <tr @class(['bg-gray-50/60 dark:bg-white/[0.02]' => $dia['surcharged']])>
                                <td @class([$celda, 'whitespace-nowrap', 'font-medium text-danger-600 dark:text-danger-400' => $dia['surcharged']])>
                                    {{ $dia['date']->locale('es')->translatedFormat('l, j \d\e F') }}
                                </td>
                                <td class="{{ $celda }} whitespace-nowrap text-center tabular-nums">{{ $hora($dia['entry']) }}</td>
                                <td class="{{ $celda }} whitespace-nowrap text-center tabular-nums">{{ $hora($dia['scheduledEnd']) }}</td>
                                <td class="{{ $celda }} whitespace-nowrap text-center tabular-nums">{{ $hora($dia['exit']) }}</td>
                                @foreach (array_keys($data['columns']) as $bolsa)
                                    <td class="{{ $celda }} text-center tabular-nums">{{ $horas((float) $dia['legal'][$bolsa]) }}</td>
                                @endforeach
                                @foreach ($data['bonusColumns'] as $bolsa)
                                    <td class="{{ $celda }} bg-gray-50 text-center tabular-nums dark:bg-white/5">{{ $horas((float) $dia['bonus'][$bolsa]) }}</td>
                                @endforeach
                                <td @class([$celda, 'text-danger-600 dark:text-danger-400' => $dia['surcharged'] && $dia['notes'] !== ''])>{{ $dia['notes'] }}</td>
                            </tr>
                        @endforeach
                        <tr class="font-bold" style="background-color: #D5EBDD; color: #0f3d22">
                            <td class="{{ $celda }}" colspan="4">TOTAL HORAS</td>
                            @foreach (array_keys($data['columns']) as $bolsa)
                                <td class="{{ $celda }} text-center tabular-nums">{{ $horas((float) $data['legalTotals'][$bolsa]) }}</td>
                            @endforeach
                            @foreach ($data['bonusColumns'] as $bolsa)
                                <td class="{{ $celda }} text-center tabular-nums">{{ $horas((float) $data['bonusTotals'][$bolsa]) }}</td>
                            @endforeach
                            <td class="{{ $celda }}"></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- Los totales por clase (horas extras y bono juntos), como al pie del formato --}}
            @php
                $suma = fn (string $bolsa): float => round((float) ($data['legalTotals'][$bolsa] ?? 0) + (float) ($data['bonusTotals'][$bolsa] ?? 0), 2);
            @endphp
            <table class="w-full border-collapse">
                @foreach ([
                    ['Total horas extras diurnas', 'overtime_day', 'Total horas extras nocturnas', 'overtime_night'],
                    ['Total horas extras dominicales diurnas', 'overtime_sunday_day', 'Total horas extras dominicales nocturnas', 'overtime_sunday_night'],
                    ['Recargo nocturno', 'night_surcharge', 'Recargo nocturno dominical', 'night_sunday_surcharge'],
                ] as [$izq, $bolsaIzq, $der, $bolsaDer])
                    <tr>
                        <th class="{{ $rotulo }}">{{ $izq }}</th>
                        <td class="{{ $celda }} w-24 text-center font-bold tabular-nums">{{ $horas($suma($bolsaIzq)) ?: '0' }}</td>
                        <th class="{{ $rotulo }}">{{ $der }}</th>
                        <td class="{{ $celda }} w-24 text-center font-bold tabular-nums">{{ $horas($suma($bolsaDer)) ?: '0' }}</td>
                    </tr>
                @endforeach
            </table>

            {{-- Los valores en pesos: solo para quien ve el sueldo del trabajador --}}
            @if ($showValues && $data['calculator'] !== [])
                <div class="-mx-2 overflow-x-auto px-2">
                    <table class="w-full min-w-[40rem] border-collapse">
                        <thead>
                            <tr class="text-xs text-white" style="background-color: {{ $verde }}">
                                <th class="border border-white/30 px-2 py-2 text-left">Concepto</th>
                                <th class="border border-white/30 px-2 py-2">Factor</th>
                                <th class="border border-white/30 px-2 py-2 text-right">Valor hora</th>
                                <th class="border border-white/30 px-2 py-2 text-right">Horas</th>
                                <th class="border border-white/30 px-2 py-2 text-right">Valor horas extras y recargos</th>
                                @if ($data['bonusAmount'] > 0)
                                    <th class="border border-white/30 px-2 py-2 text-right">Horas bono</th>
                                    <th class="border border-white/30 px-2 py-2 text-right">Bonificación</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($data['calculator'] as $fila)
                                <tr>
                                    <td class="{{ $celda }}">{{ $fila['label'] }} — {{ $fila['concept'] }}</td>
                                    <td class="{{ $celda }} text-center tabular-nums">{{ rtrim(rtrim(number_format($fila['factor'], 2, ',', '.'), '0'), ',') }}</td>
                                    <td class="{{ $celda }} text-right tabular-nums">{{ $pesos($fila['rate']) }}</td>
                                    <td class="{{ $celda }} text-right tabular-nums">{{ $horas($fila['legalHours']) ?: '0' }}</td>
                                    <td class="{{ $celda }} text-right tabular-nums">{{ $pesos($fila['legalAmount']) }}</td>
                                    @if ($data['bonusAmount'] > 0)
                                        <td class="{{ $celda }} text-right tabular-nums">{{ $horas($fila['bonusHours']) ?: '0' }}</td>
                                        <td class="{{ $celda }} text-right tabular-nums">{{ $pesos($fila['bonusAmount']) }}</td>
                                    @endif
                                </tr>
                            @endforeach
                            <tr class="font-bold" style="background-color: #D5EBDD; color: #0f3d22">
                                <td class="{{ $celda }}" colspan="3">TOTAL · valor hora {{ $pesos((float) $data['hourValue']) }} (salario ÷ {{ number_format((float) $data['divisor'], 0, ',', '.') }} h)</td>
                                <td class="{{ $celda }} text-right tabular-nums">{{ $horas(array_sum(array_column($data['calculator'], 'legalHours'))) ?: '0' }}</td>
                                <td class="{{ $celda }} text-right tabular-nums">{{ $pesos((float) $data['legalAmount']) }}</td>
                                @if ($data['bonusAmount'] > 0)
                                    <td class="{{ $celda }} text-right tabular-nums">{{ $horas(array_sum(array_column($data['calculator'], 'bonusHours'))) ?: '0' }}</td>
                                    <td class="{{ $celda }} text-right tabular-nums">{{ $pesos((float) $data['bonusAmount']) }}</td>
                                @endif
                            </tr>
                        </tbody>
                    </table>
                </div>
            @endif

            {{-- Firmas --}}
            <div class="grid grid-cols-1 gap-6 pt-6 text-xs text-gray-600 sm:grid-cols-3 dark:text-gray-300">
                <p class="border-t border-gray-400 pt-1">Vo. Bo. Jefe inmediato{{ $data['supervisor'] ? ' — '.$data['supervisor'] : '' }}</p>
                <p class="border-t border-gray-400 pt-1">{{ $employee->fullName() }} — Trabajador</p>
                <p class="border-t border-gray-400 pt-1">{{ $tenant?->overtimeFormSigner() ? $tenant->overtimeFormSigner().' — Talento Humano' : 'Talento humano' }}</p>
            </div>
        </div>
    @else
        <p class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">No hay trabajador para mostrar.</p>
    @endif
</div>
