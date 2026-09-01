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
    $num = fn (?float $v, int $dec = 0) => $v === null
        ? '<span class="empty">sin dato</span>'
        : number_format($v, $dec, ',', '.');
@endphp

<div class="doc-body">

    <div class="report-title">
        <h1>Consumo de Energía — {{ $plant->name }}</h1>
        <p>{{ $periodLabel }} · {{ $from->format('d/m/Y') }} al {{ $to->format('d/m/Y') }}</p>
    </div>

    @if (! $hasData)
        <p class="empty">
            Este período no tiene ninguna lectura de contador ni cierre mensual con energía.
        </p>
    @else
        <table class="kpi-grid">
            <tr>
                <td style="width:33.3%">
                    <div class="kpi-box">
                        <div class="kpi-value">{!! $num($resumen['kwh_total']) !!}</div>
                        <div class="kpi-label">KWh TOTAL</div>
                    </div>
                </td>
                <td style="width:33.3%">
                    <div class="kpi-box">
                        <div class="kpi-value">{!! $num($resumen['kwh_per_ton'], 2) !!}</div>
                        <div class="kpi-label">KWh / RFF</div>
                    </div>
                </td>
                <td style="width:33.3%">
                    <div class="kpi-box">
                        <div class="kpi-value">
                            {!! $resumen['clean_energy_percentage'] === null ? $num(null) : $num($resumen['clean_energy_percentage'], 2).'%' !!}
                        </div>
                        <div class="kpi-label">ENERGÍA LIMPIA</div>
                    </div>
                </td>
            </tr>
        </table>

        <div class="section">
            <div class="section-title">De dónde salieron los kWh</div>
            <table class="data-table">
                <tr>
                    <th style="width:50%">Fuente</th>
                    <th style="width:25%; text-align:right">KWh</th>
                    <th style="width:25%; text-align:right">Participación</th>
                </tr>
                @foreach ([
                    'kwh_grid' => 'Red pública',
                    'kwh_genset' => 'Planta eléctrica',
                    'kwh_turbine' => 'Turbina',
                ] as $campo => $etiqueta)
                    <tr>
                        <td>{{ $etiqueta }}</td>
                        <td style="text-align:right">{!! $num($resumen[$campo]) !!}</td>
                        <td style="text-align:right">
                            @if ($resumen[$campo] !== null && $resumen['kwh_total'] > 0)
                                {{ number_format($resumen[$campo] / $resumen['kwh_total'] * 100, 1, ',', '.') }}%
                            @else
                                <span class="empty">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                <tr>
                    <td><strong>Total</strong></td>
                    <td style="text-align:right"><strong>{!! $num($resumen['kwh_total']) !!}</strong></td>
                    <td style="text-align:right"><strong>100%</strong></td>
                </tr>
            </table>
            {{-- «Sin dato» y cero no son lo mismo: cero kWh de turbina afirma que la planta
                 funcionó a diésel, y no saberlo dice que nadie pasó a leer el contador. --}}
            <p style="font-size:8px; color:#64748b; margin-top:4px;">
                Fruta procesada en el período: {{ number_format($resumen['processed_tons'], 2, ',', '.') }} t.
                Una fuente «sin dato» es un contador sin lecturas en el rango, no un consumo de cero.
            </p>
        </div>

        @if ($meses !== [])
            {{-- El total solo dice a cuánto salió el promedio; la reunión pregunta cuál fue
                 el mes malo. Cada mes trae su propio KWh/RFF porque cada uno tiene su
                 denominador de fruta: el del rango no se puede repartir entre ellos. --}}
            <div class="section">
                <div class="section-title">Mes a mes</div>
                <table class="data-table">
                    <tr>
                        <th>Mes</th>
                        <th style="text-align:right">RFF (t)</th>
                        <th style="text-align:right">KWh/RFF</th>
                        <th style="text-align:right">KWh total</th>
                        <th style="text-align:right">Red</th>
                        <th style="text-align:right">Planta</th>
                        <th style="text-align:right">Turbina</th>
                    </tr>
                    @foreach ($meses as $mes)
                        <tr>
                            <td>{{ $mes['label'] }}</td>
                            <td style="text-align:right">{{ number_format($mes['processed_tons'], 2, ',', '.') }}</td>
                            <td style="text-align:right"><strong>{!! $num($mes['kwh_per_ton'], 2) !!}</strong></td>
                            <td style="text-align:right">{!! $num($mes['kwh_total']) !!}</td>
                            <td style="text-align:right">{!! $num($mes['kwh_grid']) !!}</td>
                            <td style="text-align:right">{!! $num($mes['kwh_genset']) !!}</td>
                            <td style="text-align:right">{!! $num($mes['kwh_turbine']) !!}</td>
                        </tr>
                    @endforeach
                </table>
                <p style="font-size:8px; color:#64748b; margin-top:4px;">
                    El KWh/RFF del período ({!! $num($resumen['kwh_per_ton'], 2) !!}) es el total de kWh
                    partido por el total de fruta, no el promedio de los meses de arriba: promediarlos
                    daría el mismo peso a un mes flojo que a uno de plena cosecha.
                </p>
            </div>
        @endif
    @endif

</div>
</body>
</html>
