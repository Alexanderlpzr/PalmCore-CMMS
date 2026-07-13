<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
    @include('reports.partials.styles')

    .bar { height: 8px; background: #dc2626; border-radius: 2px; }
    .bar-track { background: #f1f5f9; border-radius: 2px; width: 100%; }
    .note { font-size: 8px; color: #64748b; margin-top: 6px; font-style: italic; }
</style>
</head>
<body>

@include('reports.partials.header')
@include('reports.partials.footer')

<div class="doc-body">

    <div class="report-title">
        <h1>Horas Perdidas por Paros — {{ $plant->name }}</h1>
        <p>
            {{ $from->format('d/m/Y') }} — {{ $to->format('d/m/Y') }} ·
            Generado el {{ $generatedAt->format('d/m/Y H:i') }}
        </p>
    </div>

    <div class="summary-box">
        <table>
            <tr>
                <td>
                    <div class="summary-stat">{{ number_format($totalHours, 1) }} h</div>
                    <div class="summary-label">Horas perdidas</div>
                </td>
                <td>
                    <div class="summary-stat">{{ number_format($kpis['programmed_hours'], 1) }} h</div>
                    <div class="summary-label">Horas programadas</div>
                </td>
                <td>
                    <div class="summary-stat">
                        {{ $kpis['efficiency_percentage'] !== null ? number_format($kpis['efficiency_percentage'], 2).' %' : '—' }}
                    </div>
                    <div class="summary-label">Eficiencia</div>
                </td>
                <td>
                    <div class="summary-stat">{{ number_format($kpis['maintenance_lost_hours'], 1) }} h</div>
                    <div class="summary-label">De mantenimiento</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- La planta tiene un solo reloj: si dos máquinas se paran a la vez, la hora se
         perdió una sola vez. Por eso la columna de equipos puede sumar más que el
         total, y decirlo es parte del reporte. --}}
    <p class="note">
        Las horas de cada equipo son la unión de sus propios paros, recortada al periodo.
        Dos equipos parados a la vez cuestan a la planta una sola hora, así que la suma por
        equipo puede superar el total: la planta tiene un solo reloj.
    </p>

    <h2 style="font-size:12px; font-weight:bold; margin:14px 0 6px;">Pareto por equipo</h2>

    @if($byEquipment === [])
        <p style="color:#94a3b8; font-style:italic; padding:12px 0;">
            Ningún equipo registró paros con afectación a producción en este periodo.
        </p>
    @else
        @php $worst = $byEquipment[0]['hours'] ?: 1; @endphp
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width:26px;">#</th>
                    <th style="width:70px;">Código</th>
                    <th>Equipo</th>
                    <th style="width:45px;">Paros</th>
                    <th style="width:55px;">Horas</th>
                    <th style="width:110px;">Peso</th>
                    <th style="width:50px;">Acum.</th>
                </tr>
            </thead>
            <tbody>
                @foreach($byEquipment as $i => $row)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $row['code'] ?? '—' }}</td>
                        <td>{{ $row['name'] }}</td>
                        <td>{{ $row['events'] }}</td>
                        <td><strong>{{ number_format($row['hours'], 2) }}</strong></td>
                        <td>
                            <div class="bar-track">
                                <div class="bar" style="width: {{ max(2, round($row['hours'] / $worst * 100)) }}%;"></div>
                            </div>
                        </td>
                        {{-- El 80 % está donde hay que mirar. --}}
                        <td style="{{ $row['cumulative_percentage'] <= 80 ? 'font-weight:bold; color:#dc2626;' : 'color:#94a3b8;' }}">
                            {{ number_format($row['cumulative_percentage'], 0) }} %
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if($plantWideHours > 0)
        <p class="note">
            Además, {{ number_format($plantWideHours, 2) }} h de paros de planta (falta de fruta,
            corte de energía, arranque) que no pertenecen a ningún equipo y no se reparten entre ellos.
        </p>
    @endif

    <h2 style="font-size:12px; font-weight:bold; margin:16px 0 6px;">Por Tipo I</h2>

    <table class="data-table">
        <thead>
            <tr>
                <th>Tipo I</th>
                <th style="width:90px;">Responsable</th>
                <th style="width:70px;">Horas</th>
            </tr>
        </thead>
        <tbody>
            @forelse($byCategory as $row)
                <tr>
                    <td>{{ $row['label'] }}</td>
                    <td>
                        <span class="badge {{ $row['is_maintenance'] ? 'badge-danger' : 'badge-gray' }}">
                            {{ $row['is_maintenance'] ? 'Mantenimiento' : 'Otras áreas' }}
                        </span>
                    </td>
                    <td><strong>{{ number_format($row['hours'], 2) }}</strong></td>
                </tr>
            @empty
                <tr><td colspan="3" style="color:#94a3b8; font-style:italic;">Sin paros en el periodo.</td></tr>
            @endforelse
        </tbody>
    </table>

</div>
</body>
</html>
