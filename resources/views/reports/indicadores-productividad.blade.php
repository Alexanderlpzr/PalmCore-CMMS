<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
    @include('reports.partials.styles')
</style>
</head>
<body>

@include('reports.partials.header')
@include('reports.partials.footer')

<div class="doc-body">

    <div class="report-title">
        <h1>Productividad y Eficiencia — {{ $plant->name }}</h1>
        <p>{{ $periodLabel }} · {{ $from->format('d/m/Y') }} al {{ $to->format('d/m/Y') }}</p>
    </div>

    @if (! $hasData)
        <p class="empty">
            No hay jornadas cargadas en este período. Sin horas programadas no hay
            denominador, y los indicadores quedarían sin significado.
        </p>
    @else
        {{-- Los tres indicadores, arriba. Un guion cuando no se pueden calcular: sin horas
             base, una eficiencia del cero por ciento afirmaría que la planta estuvo parada
             todo el mes, que es una cosa distinta de no saberlo.

             Los dos porcentajes llevan barra porque tienen techo conocido —el 100%— y
             contra ese techo se leen. La productividad en t/h no la lleva: no hay un
             máximo contra el que compararla, y una barra inventaría una escala. --}}
        <table class="kpi-grid">
            <tr>
                <td style="width:33.3%">
                    <div class="kpi-box">
                        <div class="kpi-value">
                            {{ $kpis['efficiency_percentage'] !== null ? number_format($kpis['efficiency_percentage'], 2, ',', '.').'%' : '—' }}
                        </div>
                        <div class="kpi-label">EFICIENCIA</div>
                        @if ($kpis['efficiency_percentage'] !== null)
                            <div class="chart-track" style="margin-top:5px;">
                                <div class="chart-fill {{ $kpis['efficiency_percentage'] >= 85 ? 'fill-good' : ($kpis['efficiency_percentage'] >= 70 ? 'fill-warn' : 'fill-bad') }}"
                                     style="width: {{ min(100, round($kpis['efficiency_percentage'], 1)) }}%;"></div>
                            </div>
                        @endif
                    </div>
                </td>
                <td style="width:33.3%">
                    <div class="kpi-box">
                        <div class="kpi-value">
                            {{ $kpis['productivity_tons_per_hour'] !== null ? number_format($kpis['productivity_tons_per_hour'], 2, ',', '.') : '—' }}
                        </div>
                        <div class="kpi-label">PRODUCTIVIDAD (t/h)</div>
                    </div>
                </td>
                <td style="width:33.3%">
                    <div class="kpi-box">
                        <div class="kpi-value">
                            {{ $kpis['availability_percentage'] !== null ? number_format($kpis['availability_percentage'], 2, ',', '.').'%' : '—' }}
                        </div>
                        <div class="kpi-label">DISPONIBILIDAD</div>
                        @if ($kpis['availability_percentage'] !== null)
                            <div class="chart-track" style="margin-top:5px;">
                                <div class="chart-fill {{ $kpis['availability_percentage'] >= 90 ? 'fill-good' : ($kpis['availability_percentage'] >= 75 ? 'fill-warn' : 'fill-bad') }}"
                                     style="width: {{ min(100, round($kpis['availability_percentage'], 1)) }}%;"></div>
                            </div>
                        @endif
                    </div>
                </td>
            </tr>
        </table>

        {{-- El denominador, a la vista. Una eficiencia del 88% no dice lo mismo si el aseo
             se llevó veinte horas que si se llevó doscientas, y sin este bloque el número
             de arriba no se puede discutir en la reunión. --}}
        <div class="section">
            <div class="section-title">Cómo se reparten las horas</div>

            {{-- Las horas pagadas, repartidas. Es la lectura que la tabla de abajo no da de
                 un vistazo: cuánto de lo que se pagó acabó moliendo fruta y cuánto se fue
                 en aseo, en paro por mantenimiento y en lo demás. --}}
            @if ($kpis['programmed_hours'] > 0)
                @php
                    $reparto = [
                        ['n' => 'Prensado',              'v' => $kpis['effective_hours'],        'f' => 'fill-good'],
                        ['n' => 'Aseo',                  'v' => $kpis['cleaning_hours'],         'f' => 'fill-cool'],
                        ['n' => 'Paro por mantenimiento','v' => $kpis['maintenance_lost_hours'], 'f' => 'fill-bad'],
                        ['n' => 'Otras pérdidas',        'v' => $kpis['other_lost_hours'],       'f' => 'fill-warn'],
                    ];
                    $conHoras = array_values(array_filter($reparto, fn (array $r): bool => $r['v'] > 0));
                @endphp

                <table class="chart-stack">
                    <tr>
                        @foreach ($conHoras as $r)
                            <td class="{{ $r['f'] }}"
                                style="width: {{ round($r['v'] / $kpis['programmed_hours'] * 100, 2) }}%;">&nbsp;</td>
                        @endforeach
                    </tr>
                </table>
                <div class="chart-legend">
                    @foreach ($conHoras as $r)
                        <span class="dot {{ $r['f'] }}"></span>{{ $r['n'] }}
                        ({{ number_format($r['v'] / $kpis['programmed_hours'] * 100, 1, ',', '.') }}%)@if (! $loop->last) &nbsp;&nbsp; @endif
                    @endforeach
                </div>
            @endif

            <table class="data-table" style="margin-top:6px;">
                <tr>
                    <th style="width:60%">Concepto</th>
                    <th style="width:20%; text-align:right">Horas</th>
                    <th style="width:20%">&nbsp;</th>
                </tr>
                <tr>
                    <td>Horas pagadas (HP)</td>
                    <td style="text-align:right">{{ number_format($kpis['programmed_hours'], 2, ',', '.') }}</td>
                    <td>del calendario de producción</td>
                </tr>
                <tr>
                    <td>Horas de aseo (HASEO)</td>
                    <td style="text-align:right">−{{ number_format($kpis['cleaning_hours'], 2, ',', '.') }}</td>
                    <td>paros de mantenimiento programados</td>
                </tr>
                <tr>
                    <td><strong>Horas base (HP − HASEO)</strong></td>
                    <td style="text-align:right"><strong>{{ number_format($baseHours, 2, ',', '.') }}</strong></td>
                    <td><strong>el denominador de los dos primeros indicadores</strong></td>
                </tr>
                <tr>
                    <td>Paro por mantenimiento (HMTTO)</td>
                    <td style="text-align:right">{{ number_format($kpis['maintenance_lost_hours'], 2, ',', '.') }}</td>
                    <td>no programado</td>
                </tr>
                <tr>
                    <td>Otras pérdidas</td>
                    <td style="text-align:right">{{ number_format($kpis['other_lost_hours'], 2, ',', '.') }}</td>
                    <td>ajenas a mantenimiento</td>
                </tr>
                <tr>
                    <td><strong>Horas de prensado (HPREN)</strong></td>
                    <td style="text-align:right"><strong>{{ number_format($kpis['effective_hours'], 2, ',', '.') }}</strong></td>
                    <td><strong>lo que la planta de verdad molió</strong></td>
                </tr>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Fruta y fallas</div>
            <table class="grid-2">
                <tr>
                    <td>
                        <div class="field-label">Fruta procesada (RFF)</div>
                        <div class="field-value">{{ number_format($kpis['processed_tons'], 2, ',', '.') }} t</div>
                        <div class="field-label">Fallas en el período</div>
                        <div class="field-value">{{ $kpis['failure_count'] }}</div>
                    </td>
                    <td>
                        <div class="field-label">MTBF — horas entre fallas</div>
                        <div class="field-value">{{ $kpis['mtbf_hours'] !== null ? number_format($kpis['mtbf_hours'], 2, ',', '.').' h' : '—' }}</div>
                        <div class="field-label">MTTR — horas de paro por falla</div>
                        <div class="field-value">{{ $kpis['mttr_hours'] !== null ? number_format($kpis['mttr_hours'], 2, ',', '.').' h' : '—' }}</div>
                    </td>
                </tr>
            </table>
        </div>

        {{-- La distancia entre las dos cifras de reparación es la frase que justifica un
             stock de repuestos críticos: «reparamos en dos horas pero la máquina estuvo
             nueve abajo». Publicar solo la de llave haría mejorar el indicador sin que la
             planta mejorara nada. --}}
        @if ($kpis['mttr_wrench_hours'] !== null)
            <div class="section">
                <div class="section-title">Reparación: llave contra espera</div>
                <table class="data-table">
                    <tr>
                        <th style="width:34%">Horas de llave</th>
                        <th style="width:33%">Horas de espera</th>
                        <th style="width:33%">MTTR solo de llave</th>
                    </tr>
                    <tr>
                        <td>{{ number_format($kpis['wrench_hours'], 2, ',', '.') }} h</td>
                        <td>{{ number_format($kpis['waiting_hours'], 2, ',', '.') }} h</td>
                        <td>{{ number_format($kpis['mttr_wrench_hours'], 2, ',', '.') }} h</td>
                    </tr>
                </table>
                <p style="font-size:8px; color:#64748b; margin-top:4px;">
                    Sobre {{ $kpis['classified_failure_count'] }} falla(s) con tiempos
                    clasificados. El MTTR de arriba ({{ number_format($kpis['mttr_hours'], 2, ',', '.') }} h)
                    incluye la espera del repuesto, porque la máquina estuvo abajo igual.
                </p>
            </div>
        @endif
    @endif

</div>
</body>
</html>
