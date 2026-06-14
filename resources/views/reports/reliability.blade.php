<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #1e293b; background: #fff; }

    #header { position: fixed; top: -50px; left: 0; right: 0; height: 50px; }
    #footer { position: fixed; bottom: -30px; left: 0; right: 0; height: 30px; }

    .report-title { background: #1e3a5f; color: #fff; padding: 8px 12px; margin-bottom: 12px; border-radius: 3px; }
    .report-title h1 { font-size: 14px; font-weight: bold; }
    .report-title p  { font-size: 9px; color: #93c5fd; margin-top: 2px; }

    .kpi-grid { width: 100%; margin-bottom: 14px; border-collapse: separate; border-spacing: 4px; }
    .kpi-box { background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 3px; padding: 8px; text-align: center; }
    .kpi-value { font-size: 16px; font-weight: bold; color: #1e3a5f; }
    .kpi-label { font-size: 7px; color: #64748b; margin-top: 2px; }

    table.data-table { width: 100%; border-collapse: collapse; font-size: 8px; }
    table.data-table th { background: #1e3a5f; color: #fff; text-align: left; padding: 5px;
                          font-weight: bold; border: 1px solid #1e3a5f; font-size: 7px; }
    table.data-table td { padding: 4px 5px; border: 1px solid #e2e8f0; vertical-align: middle; }
    table.data-table tr:nth-child(even) td { background: #f8fafc; }

    .avail-bar-outer { width: 60px; height: 7px; background: #e2e8f0; border-radius: 3px; display: inline-block; vertical-align: middle; }
    .avail-bar-inner { height: 7px; border-radius: 3px; }
    .bar-high   { background: #16a34a; }
    .bar-medium { background: #d97706; }
    .bar-low    { background: #dc2626; }

    .section-title { font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.05em;
                     color: #1e3a5f; border-bottom: 1px solid #e2e8f0; padding-bottom: 3px; margin-bottom: 7px; }
</style>
</head>
<body>

<div id="header">
    @include('reports.partials.header')
</div>
<div id="footer">
    @include('reports.partials.footer')
</div>

<div style="padding-top: 60px; padding-bottom: 35px;">

    <div class="report-title">
        <h1>Reporte de Confiabilidad</h1>
        <p>Generado el {{ $generatedAt->format('d/m/Y \a \l\a\s H:i') }} — {{ $kpis->count() }} equipos monitoreados</p>
    </div>

    {{-- Global KPI Summary --}}
    <table class="kpi-grid">
        <tr>
            <td>
                <div class="kpi-box">
                    <div class="kpi-value">{{ (int)($summary->total_equipment ?? 0) }}</div>
                    <div class="kpi-label">Equipos monitoreados</div>
                </div>
            </td>
            <td>
                <div class="kpi-box">
                    <div class="kpi-value">
                        @if($summary->avg_availability)
                            {{ number_format($summary->avg_availability, 1) }}%
                        @else
                            —
                        @endif
                    </div>
                    <div class="kpi-label">Disponibilidad promedio</div>
                </div>
            </td>
            <td>
                <div class="kpi-box">
                    <div class="kpi-value">
                        @if($summary->avg_mtbf)
                            {{ number_format($summary->avg_mtbf, 1) }}h
                        @else
                            —
                        @endif
                    </div>
                    <div class="kpi-label">MTBF promedio</div>
                </div>
            </td>
            <td>
                <div class="kpi-box">
                    <div class="kpi-value">
                        @if($summary->avg_mttr)
                            {{ number_format($summary->avg_mttr, 1) }}h
                        @else
                            —
                        @endif
                    </div>
                    <div class="kpi-label">MTTR promedio</div>
                </div>
            </td>
            <td>
                <div class="kpi-box">
                    <div class="kpi-value">{{ (int)($summary->total_failures ?? 0) }}</div>
                    <div class="kpi-label">Total de fallas</div>
                </div>
            </td>
            <td>
                <div class="kpi-box">
                    <div class="kpi-value">
                        {{ $summary->total_downtime ? number_format($summary->total_downtime, 1).'h' : '0h' }}
                    </div>
                    <div class="kpi-label">Horas totales de parada</div>
                </div>
            </td>
        </tr>
    </table>

    {{-- Equipment KPI Table --}}
    @if($kpis->isEmpty())
        <p style="color:#94a3b8; font-style:italic; text-align:center; padding:20px;">Sin KPIs registrados.</p>
    @else
    <div class="section-title">Detalle por Equipo</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Equipo</th>
                <th style="width:60px;">Planta</th>
                <th style="width:70px;">Disponibilidad</th>
                <th style="width:50px;">MTBF (h)</th>
                <th style="width:50px;">MTTR (h)</th>
                <th style="width:35px;">Fallas</th>
                <th style="width:55px;">Parada (h)</th>
                <th style="width:60px;">Última falla</th>
                <th style="width:60px;">Actualizado</th>
            </tr>
        </thead>
        <tbody>
            @foreach($kpis as $kpi)
            @php
                $avail = $kpi->availability_percentage ? (float)$kpi->availability_percentage : null;
                $barColor = $avail === null ? '' : ($avail >= 85 ? 'bar-high' : ($avail >= 70 ? 'bar-medium' : 'bar-low'));
                $barWidth = $avail ? round($avail * 0.6) : 0;
            @endphp
            <tr>
                <td>
                    <strong>{{ $kpi->equipment?->name ?? '—' }}</strong><br>
                    <span style="font-size:7px; color:#64748b;">{{ $kpi->equipment?->code ?? '' }}</span>
                </td>
                <td>{{ $kpi->equipment?->plant?->name ?? '—' }}</td>
                <td>
                    @if($avail !== null)
                        <div class="avail-bar-outer">
                            <div class="avail-bar-inner {{ $barColor }}" style="width:{{ $barWidth }}px;"></div>
                        </div>
                        {{ number_format($avail, 1) }}%
                    @else
                        —
                    @endif
                </td>
                <td>{{ $kpi->mtbf_hours ? number_format($kpi->mtbf_hours, 1) : '—' }}</td>
                <td>{{ $kpi->mttr_hours ? number_format($kpi->mttr_hours, 1) : '—' }}</td>
                <td>{{ $kpi->failure_count ?? 0 }}</td>
                <td>{{ $kpi->downtime_hours ? number_format($kpi->downtime_hours, 1) : '—' }}</td>
                <td>{{ $kpi->equipment?->last_failure_at?->format('d/m/Y') ?? '—' }}</td>
                <td>{{ $kpi->last_calculated_at?->format('d/m/Y') ?? '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

</div>
</body>
</html>
