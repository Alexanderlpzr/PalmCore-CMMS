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
