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
        {{-- La ejecución contra el techo. Cuando se pasa, la barra se llena en rojo y se
             queda al 100%: un 130% dibujado saliéndose de la caja no se lee, y el número
             de al lado ya dice cuánto se pasó. --}}
        <div class="chart-track" style="height:14px; margin-bottom:8px;">
            <div class="chart-fill {{ $excedido ? 'fill-bad' : ($porcentaje !== null && $porcentaje >= 85 ? 'fill-warn' : 'fill-good') }}"
                 style="height:14px; width: {{ min(100, max(1, (int) round($porcentaje ?? 0))) }}%;"></div>
        </div>

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
            @include('reports.partials.chart-bars', [
                'filas' => collect($porCategoria)->map(fn (float $monto, string $categoria): array => [
                    'name' => \App\Domain\Reports\Services\PresupuestoPdfService::categoryLabel($categoria),
                    'value' => $monto,
                    'text' => '$ '.number_format($monto, 0, ',', '.'),
                    'fill' => 'fill-good',
                ])->values()->all(),
            ])

            <table class="data-table" style="margin-top:6px;">
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

            {{-- El gasto de cada mes, para ver si viene creciendo. La tabla de abajo trae
                 el presupuesto de cada uno; aquí solo lo ejecutado, que es la serie que
                 tiene sentido comparar consigo misma. --}}
            @include('reports.partials.chart-columns', [
                'filas' => collect($meses)->map(fn (array $m): array => [
                    'label' => mb_substr($m['label'], 0, 3),
                    'value' => $m['total'],
                    'text' => number_format($m['total'] / 1000, 0, ',', '.').'k',
                    'fill' => $m['is_over_budget'] ? 'fill-bad' : 'fill-good',
                ])->all(),
            ])
            <p style="font-size:8px; color:#64748b; margin:2px 0 8px;">
                Ejecutado por mes, en miles. En rojo, los meses que se pasaron del presupuesto.
            </p>

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
