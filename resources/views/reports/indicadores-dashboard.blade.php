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
    $parte = fn (float $h) => $horasTotales > 0 ? number_format($h / $horasTotales * 100, 1, ',', '.').'%' : '—';
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
                    <table class="data-table">
                        <tr>
                            <th style="width:60%">Concepto</th>
                            <th style="width:20%; text-align:right">Horas</th>
                            <th style="width:20%; text-align:right">Participación</th>
                        </tr>
                        @foreach ($filas as $fila)
                            @php
                                $etiqueta = is_array($fila) ? $fila['label'] : $fila->label;
                                $valor = (float) (is_array($fila) ? $fila['hours'] : ($fila->value ?? 0));
                            @endphp
                            <tr>
                                <td>{{ $etiqueta }}</td>
                                <td style="text-align:right">{{ $horas($valor) }}</td>
                                <td style="text-align:right">{{ $parte($valor) }}</td>
                            </tr>
                        @endforeach
                    </table>
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
                        <th style="width:15%; text-align:right">Horas</th>
                        <th style="width:10%; text-align:right">Paros</th>
                        <th style="width:15%; text-align:right">Acumulado</th>
                    </tr>
                    @foreach ($porEquipo as $equipo)
                        <tr>
                            <td>{{ $equipo['code'] ?? '—' }}</td>
                            <td>{{ $equipo['name'] }}</td>
                            <td style="text-align:right">{{ $horas($equipo['hours']) }}</td>
                            <td style="text-align:right">{{ $equipo['events'] }}</td>
                            <td style="text-align:right">{{ number_format($equipo['cumulative_percentage'], 1, ',', '.') }}%</td>
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
            <table class="data-table">
                <tr>
                    <th style="width:70%">Equipo</th>
                    <th style="width:30%; text-align:right">Fallas</th>
                </tr>
                @foreach ($pareto as $punto)
                    <tr>
                        <td>{{ $punto->label }}</td>
                        <td style="text-align:right">{{ $punto->count ?: (int) $punto->value }}</td>
                    </tr>
                @endforeach
            </table>
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
    </div>

    @if (! empty($costoPorEquipo))
        <div class="section">
            <div class="section-title">
                Costo por equipo <span class="ventana">— {{ $costoVentana }}, no {{ $periodLabel }}</span>
            </div>
            <table class="data-table">
                <tr>
                    <th style="width:70%">Equipo</th>
                    <th style="width:30%; text-align:right">Costo</th>
                </tr>
                @foreach ($costoPorEquipo as $punto)
                    <tr>
                        <td>{{ $punto->label }}</td>
                        <td style="text-align:right">$ {{ number_format((float) $punto->value, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </table>
        </div>
    @endif

</div>
</body>
</html>
