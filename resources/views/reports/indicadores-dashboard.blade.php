<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
    @include('reports.partials.styles')

    {{-- La ventana propia de un bloque, junto a su título. Va en gris y en cursiva para
         que se lea como una salvedad y no como parte del nombre del bloque. --}}
    .ventana { font-weight: normal; text-transform: none; letter-spacing: 0; color: #b45309;
               font-style: italic; font-size: 8px; }
</style>
</head>
<body>

@include('reports.partials.header')
@include('reports.partials.footer')

@php
    $horas = fn (float $h) => number_format($h, 2, ',', '.');
@endphp

<div class="doc-body">

    <div class="report-title">
        <h1>Indicadores de Mantenimiento — {{ $plant->name }}</h1>
        <p>{{ $periodLabel }} · {{ $from->format('d/m/Y') }} al {{ $to->format('d/m/Y') }}</p>
    </div>

    {{-- La advertencia va arriba del todo y no en una nota al pie. Cuatro de los bloques de
         este informe no responden al período elegido, y quien lo lee asume lo contrario:
         asumiría que un Pareto de doce meses es del mes del título. Decirlo al final sería
         decirlo cuando ya se sacó la conclusión. --}}
    <div class="text-block" style="margin-bottom:12px; border-color:#fcd34d; background:#fffbeb;">
        <strong>Sobre los períodos de este informe.</strong>
        Los bloques de paros cubren {{ $periodLabel }}. Los cuatro últimos —Pareto de fallas,
        cumplimiento del plan, planificado contra correctivo y costo por equipo— se calculan
        sobre su propia ventana, que va indicada junto a cada título. No son cifras del período.
    </div>

    @if (! $hasData)
        <p class="empty">No hay paros registrados en este período.</p>
    @else
        <div class="summary-box">
            <table>
                <tr>
                    <td>
                        <div class="summary-stat">{{ $horas($horasTotales) }} h</div>
                        <div class="summary-label">HORAS PERDIDAS EN EL PERÍODO</div>
                    </td>
                    <td>
                        <div class="summary-stat">{{ $horas($horasPlanta) }} h</div>
                        <div class="summary-label">SIN EQUIPO ASIGNADO</div>
                    </td>
                    <td>
                        <div class="summary-stat">{{ count($porEquipo) }}</div>
                        <div class="summary-label">EQUIPOS INVOLUCRADOS</div>
                    </td>
                </tr>
            </table>
        </div>

        @foreach ([
            ['Quién paró la línea (Tipo I)', $porTipo],
            ['Causa física del paro', $porCategoria],
            ['Causa concreta (Tipo II)', $porCausa],
            ['Sección de planta', $porSeccion],
        ] as [$titulo, $filas])
            @if (! empty($filas))
                <div class="section">
                    <div class="section-title">{{ $titulo }}</div>
                    {{-- Ordenado de mayor a menor y con barra: lo que se busca aquí es
                         dónde se fueron las horas, y eso se ve antes por el largo que
                         leyendo una columna de números. --}}
                    @include('reports.partials.chart-bars', [
                        'filas' => collect($filas)
                            ->map(fn ($fila): array => [
                                'name' => is_array($fila) ? $fila['label'] : $fila->label,
                                'value' => (float) (is_array($fila) ? $fila['hours'] : ($fila->value ?? 0)),
                            ])
                            ->sortByDesc('value')
                            ->map(fn (array $f): array => [
                                ...$f,
                                'text' => number_format($f['value'], 1, ',', '.').' h',
                                'fill' => 'fill-warn',
                            ])
                            ->values()
                            ->all(),
                    ])
                </div>
            @endif
        @endforeach

        @if (! empty($porEquipo))
            <div class="section">
                <div class="section-title">Equipos que más pararon</div>
                <table class="data-table">
                    <tr>
                        <th style="width:15%">Código</th>
                        <th style="width:45%">Equipo</th>
                        <th style="width:12%; text-align:right">Horas</th>
                        <th style="width:16%">&nbsp;</th>
                        <th style="width:6%; text-align:right">Paros</th>
                        <th style="width:12%; text-align:right">Acumulado</th>
                    </tr>
                    @php($peor = collect($porEquipo)->max('hours') ?: 0)
                    @foreach ($porEquipo as $equipo)
                        <tr>
                            <td>{{ $equipo['code'] ?? '—' }}</td>
                            <td>{{ $equipo['name'] }}</td>
                            <td style="text-align:right">{{ $horas($equipo['hours']) }}</td>
                            <td>
                                <div class="chart-track">
                                    <div class="chart-fill fill-bad"
                                         style="width: {{ $peor > 0 ? max(2, round($equipo['hours'] / $peor * 100)) : 0 }}%;"></div>
                                </div>
                            </td>
                            <td style="text-align:right">{{ $equipo['events'] }}</td>
                            {{-- El 80% es donde hay que mirar: por encima de esa línea está
                                 el puñado de equipos que explica la mayor parte del paro. --}}
                            <td style="text-align:right; {{ $equipo['cumulative_percentage'] <= 80 ? 'font-weight:bold; color:#dc2626;' : 'color:#94a3b8;' }}">
                                {{ number_format($equipo['cumulative_percentage'], 1, ',', '.') }}%
                            </td>
                        </tr>
                    @endforeach
                </table>
            </div>
        @endif
    @endif

    {{-- A partir de aquí, cada bloque con su ventana. --}}

    @if (! empty($pareto))
        <div class="section">
            <div class="section-title">
                Pareto de fallas <span class="ventana">— {{ $paretoVentana }}, no {{ $periodLabel }}</span>
            </div>
            @include('reports.partials.chart-bars', [
                'filas' => collect($pareto)->map(fn ($punto): array => [
                    'name' => $punto->label,
                    'value' => (float) ($punto->count ?: (int) $punto->value),
                    'text' => ($punto->count ?: (int) $punto->value).' fallas',
                    'fill' => 'fill-bad',
                ])->all(),
            ])
        </div>
    @endif

    <div class="section">
        <div class="section-title">
            Cumplimiento del plan de mantenimiento <span class="ventana">— {{ $cumplimientoVentana }}</span>
        </div>
        <table class="data-table">
            <tr>
                <th style="width:25%; text-align:right">Planes</th>
                <th style="width:25%; text-align:right">Al día</th>
                <th style="width:25%; text-align:right">Vencidos</th>
                <th style="width:25%; text-align:right">Cumplimiento</th>
            </tr>
            <tr>
                <td style="text-align:right">{{ $cumplimiento['total'] }}</td>
                <td style="text-align:right">{{ $cumplimiento['on_schedule'] }}</td>
                <td style="text-align:right">{{ $cumplimiento['overdue'] }}</td>
                <td style="text-align:right">
                    @if ($cumplimiento['compliance'] === null)
                        <span class="empty">—</span>
                    @else
                        <strong>{{ number_format($cumplimiento['compliance'], 1, ',', '.') }}%</strong>
                    @endif
                </td>
            </tr>
        </table>
        @if ($cumplimiento['compliance'] !== null)
            <div class="chart-track" style="margin-top:5px;">
                <div class="chart-fill {{ $cumplimiento['compliance'] >= 90 ? 'fill-good' : ($cumplimiento['compliance'] >= 70 ? 'fill-warn' : 'fill-bad') }}"
                     style="width: {{ min(100, round($cumplimiento['compliance'], 1)) }}%;"></div>
            </div>
        @endif
    </div>

    <div class="section">
        <div class="section-title">
            Planificado contra correctivo <span class="ventana">— {{ $planificadoVentana }}, no {{ $periodLabel }}</span>
        </div>
        <table class="data-table">
            <tr>
                <th style="width:33%; text-align:right">Preventivo</th>
                <th style="width:33%; text-align:right">Correctivo</th>
                <th style="width:34%; text-align:right">Share preventivo</th>
            </tr>
            <tr>
                <td style="text-align:right">{{ $planificado['preventive'] }}</td>
                <td style="text-align:right">{{ $planificado['corrective'] }}</td>
                <td style="text-align:right">
                    @if ($planificado['preventive_pct'] === null)
                        <span class="empty">—</span>
                    @else
                        <strong>{{ number_format($planificado['preventive_pct'], 1, ',', '.') }}%</strong>
                    @endif
                </td>
            </tr>
        </table>
        {{-- Una operación sana tiende a que la parte verde crezca: más trabajo planificado
             y menos apagando incendios. Es la lectura de esta mezcla. --}}
        @if ($planificado['total'] > 0)
            <table class="chart-stack" style="margin-top:5px;">
                <tr>
                    @if ($planificado['preventive'] > 0)
                        <td class="fill-good" style="width: {{ round($planificado['preventive'] / $planificado['total'] * 100, 2) }}%;">&nbsp;</td>
                    @endif
                    @if ($planificado['corrective'] > 0)
                        <td class="fill-bad" style="width: {{ round($planificado['corrective'] / $planificado['total'] * 100, 2) }}%;">&nbsp;</td>
                    @endif
                </tr>
            </table>
            <div class="chart-legend">
                <span class="dot fill-good"></span>Preventivo ({{ $planificado['preventive'] }})
                &nbsp;&nbsp;
                <span class="dot fill-bad"></span>Correctivo ({{ $planificado['corrective'] }})
            </div>
        @endif
    </div>

    @if (! empty($costoPorEquipo))
        <div class="section">
            <div class="section-title">
                Costo por equipo <span class="ventana">— {{ $costoVentana }}, no {{ $periodLabel }}</span>
            </div>
            @include('reports.partials.chart-bars', [
                'filas' => collect($costoPorEquipo)->map(fn ($punto): array => [
                    'name' => $punto->label,
                    'value' => (float) $punto->value,
                    'text' => '$ '.number_format((float) $punto->value, 0, ',', '.'),
                    'fill' => 'fill-gray',
                ])->all(),
            ])
        </div>
    @endif

</div>
</body>
</html>
