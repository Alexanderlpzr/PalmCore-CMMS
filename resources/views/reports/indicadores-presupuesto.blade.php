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

@php
    $money = fn (?float $v) => $v === null
        ? '<span class="empty">sin cargar</span>'
        : '$ '.number_format($v, 0, ',', '.');
@endphp

<div class="doc-body">

    <div class="report-title">
        <h1>Presupuesto de Mantenimiento — {{ $plant->name }}</h1>
        <p>{{ $periodLabel }} · {{ $from->format('d/m/Y') }} al {{ $to->format('d/m/Y') }}</p>
    </div>

    <table class="kpi-grid">
        <tr>
            <td style="width:33.3%">
                <div class="kpi-box">
                    <div class="kpi-value">{!! $money($presupuesto) !!}</div>
                    <div class="kpi-label">PRESUPUESTADO</div>
                </div>
            </td>
            <td style="width:33.3%">
                <div class="kpi-box">
                    <div class="kpi-value">{!! $money($gastado) !!}</div>
                    <div class="kpi-label">EJECUTADO</div>
                </div>
            </td>
            <td style="width:33.3%">
                <div class="kpi-box">
                    <div class="kpi-value">
                        {!! $porcentaje === null ? '<span class="empty">—</span>' : number_format($porcentaje, 1, ',', '.').'%' !!}
                    </div>
                    <div class="kpi-label">EJECUCIÓN</div>
                </div>
            </td>
        </tr>
    </table>

    {{-- Un presupuesto sin cargar se dice, no se imprime como cero: cero afirma que no se
         asignó nada, que es distinto de que nadie lo haya cargado todavía. --}}
    @if ($presupuesto === null)
        <p class="empty" style="margin-bottom:12px;">
            Ningún mes de este período tiene presupuesto asignado, así que no hay contra qué
            comparar el gasto.
        </p>
    @else
        <div class="summary-box">
            <table>
                <tr>
                    <td>
                        <div class="summary-stat">{!! $money($restante) !!}</div>
                        <div class="summary-label">{{ $excedido ? 'EXCEDIDO' : 'DISPONIBLE' }}</div>
                    </td>
                    <td>
                        <div class="summary-stat">{{ $gastos }}</div>
                        <div class="summary-label">GASTOS REGISTRADOS</div>
                    </td>
                    <td>
                        @if ($excedido)
                            <span class="badge badge-danger">Por encima del presupuesto</span>
                        @else
                            <span class="badge badge-success">Dentro del presupuesto</span>
                        @endif
                    </td>
                </tr>
            </table>
        </div>
    @endif

    <div class="section">
        <div class="section-title">En qué se fue</div>
        @if ($porCategoria === [])
            <p class="empty">No hay gastos registrados en este período.</p>
        @else
            <table class="data-table">
                <tr>
                    <th style="width:55%">Categoría</th>
                    <th style="width:25%; text-align:right">Monto</th>
                    <th style="width:20%; text-align:right">Participación</th>
                </tr>
                @foreach ($porCategoria as $categoria => $monto)
                    <tr>
                        <td>{{ \App\Domain\Reports\Services\PresupuestoPdfService::categoryLabel($categoria) }}</td>
                        <td style="text-align:right">{!! $money($monto) !!}</td>
                        <td style="text-align:right">
                            {{ $gastado > 0 ? number_format($monto / $gastado * 100, 1, ',', '.').'%' : '—' }}
                        </td>
                    </tr>
                @endforeach
            </table>
            <div class="cost-total">Total ejecutado: {!! $money($gastado) !!}</div>
        @endif
    </div>

    @if ($meses !== [])
        <div class="section">
            <div class="section-title">Mes a mes</div>
            <table class="data-table">
                <tr>
                    <th>Mes</th>
                    <th style="text-align:right">Presupuestado</th>
                    <th style="text-align:right">Ejecutado</th>
                    <th style="text-align:right">Diferencia</th>
                    <th style="text-align:right">Ejecución</th>
                </tr>
                @foreach ($meses as $mes)
                    <tr>
                        <td>{{ $mes['label'] }}</td>
                        <td style="text-align:right">{!! $money($mes['budget']) !!}</td>
                        <td style="text-align:right">{!! $money($mes['total']) !!}</td>
                        <td style="text-align:right">{!! $money($mes['remaining']) !!}</td>
                        <td style="text-align:right">
                            @if ($mes['percent_used'] === null)
                                <span class="empty">—</span>
                            @else
                                <span class="badge {{ $mes['is_over_budget'] ? 'badge-danger' : 'badge-success' }}">
                                    {{ number_format($mes['percent_used'], 1, ',', '.') }}%
                                </span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </table>
        </div>
    @endif

</div>
</body>
</html>
