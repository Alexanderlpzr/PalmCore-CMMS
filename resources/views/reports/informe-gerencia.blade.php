<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
    @include('reports.partials.styles')
    .num { text-align: right; white-space: nowrap; }
    .small { font-size: 8px; color: #64748b; }
    .ok { color: #047857; font-weight: bold; }
    .warn { color: #b45309; font-weight: bold; }
    .bad { color: #dc2626; font-weight: bold; }
    table.data-table td, table.data-table th { font-size: 8px; padding: 3px 5px; }
    table.data-table th.num { text-align: right; }
    tr.total td { font-weight: bold; background: #ecfdf5 !important; }
    .kpi-sub { font-size: 7.5px; color: #475569; margin-top: 3px; }
    .note { font-size: 8px; color: #64748b; margin-top: 4px; line-height: 1.4; }
    .page-break { page-break-before: always; }
</style>
</head>
<body>

@include('reports.partials.header')
@include('reports.partials.footer')

@php
    $tn = fn (?float $v, int $d = 1): string => $v === null ? '—' : number_format($v, $d, ',', '.');
    $pesos = fn (?float $v): string => $v === null ? '—' : '$ '.number_format($v, 0, ',', '.');
    $millones = fn (?float $v): string => $v === null ? '—' : '$ '.number_format($v / 1_000_000, 0, ',', '.').' M';
    $pct = fn (?float $r): string => $r === null ? '—' : number_format($r * 100, 1, ',', '.').' %';
    $clase = fn (?float $r): string => match (true) { $r === null => '', $r >= 0.95 => 'ok', $r >= 0.8 => 'warn', default => 'bad' };
    $s = $summary;
    $cpo = $products['cpo'];
    $pko = $products['pko'];
@endphp

<div class="doc-body">

    <div class="report-title">
        <h1>Informe de gerencia — {{ $periodLabel }}</h1>
        <p>Ventas de aceite y palmiste, fruta, planta y cartera · Comparado contra la {{ $planLabel }}</p>
    </div>

    {{-- El mes y el año en seis números. --}}
    <table class="kpi-grid">
        <tr>
            <td style="width:33.3%">
                <div class="kpi-box">
                    <div class="kpi-value">{{ $tn($s['oil_month_dispatched']) }} tn</div>
                    <div class="kpi-label">ACEITE DESPACHADO EN {{ mb_strtoupper($monthName) }}</div>
                    <div class="kpi-sub">De {{ $tn($s['oil_month_projected']) }} tn proyectadas · <span class="{{ $clase($s['oil_month_ratio']) }}">{{ $pct($s['oil_month_ratio']) }}</span></div>
                </div>
            </td>
            <td style="width:33.3%">
                <div class="kpi-box">
                    <div class="kpi-value">{{ $tn($s['fruit_month_received']) }} tn</div>
                    <div class="kpi-label">FRUTA RECIBIDA EN {{ mb_strtoupper($monthName) }}</div>
                    <div class="kpi-sub">De {{ $tn($s['fruit_month_projected']) }} tn proyectadas · <span class="{{ $clase($s['fruit_month_ratio']) }}">{{ $pct($s['fruit_month_ratio']) }}</span></div>
                </div>
            </td>
            <td style="width:33.3%">
                <div class="kpi-box">
                    <div class="kpi-value">{{ $millones($s['invoiced_month']) }}</div>
                    <div class="kpi-label">FACTURADO EN {{ mb_strtoupper($monthName) }}</div>
                    <div class="kpi-sub">Aceite a {{ $pesos($s['oil_price_month']) }} por tonelada</div>
                </div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="kpi-box">
                    <div class="kpi-value">{{ $tn($s['oil_ytd_dispatched']) }} tn</div>
                    <div class="kpi-label">ACEITE EN EL AÑO, A {{ mb_strtoupper($monthName) }}</div>
                    <div class="kpi-sub">De {{ $tn($s['oil_ytd_projected']) }} tn · <span class="{{ $clase($s['oil_ytd_ratio']) }}">{{ $pct($s['oil_ytd_ratio']) }}</span> · palmiste {{ $tn($s['pko_ytd_dispatched']) }} tn</div>
                </div>
            </td>
            <td>
                <div class="kpi-box">
                    <div class="kpi-value">{{ $tn($s['fruit_ytd_received']) }} tn</div>
                    <div class="kpi-label">FRUTA EN EL AÑO, A {{ mb_strtoupper($monthName) }}</div>
                    <div class="kpi-sub">De {{ $tn($s['fruit_ytd_projected']) }} tn · <span class="{{ $clase($s['fruit_ytd_ratio']) }}">{{ $pct($s['fruit_ytd_ratio']) }}</span></div>
                </div>
            </td>
            <td>
                <div class="kpi-box">
                    <div class="kpi-value">{{ $millones($s['receivable']) }}</div>
                    <div class="kpi-label">CARTERA POR COBRAR HOY</div>
                    <div class="kpi-sub"><span class="{{ $s['overdue'] > 0 ? 'bad' : 'ok' }}">Vencido {{ $millones($s['overdue']) }}</span> · facturado en el año {{ $millones($s['invoiced_ytd']) }}</div>
                </div>
            </td>
        </tr>
    </table>

    {{-- La tendencia del año: aceite despachado mes a mes, en color según el cumplimiento. --}}
    <div class="section">
        <div class="section-title">Aceite despachado por mes (tn)</div>
        @include('reports.partials.chart-columns', [
            'alto' => 60,
            'filas' => collect(range(1, 12))->map(function (int $m) use ($cpo, $month, $tn) {
                $real = (float) ($cpo['dispatched_months'][$m] ?? 0);
                $proy = (float) ($cpo['projected_months'][$m] ?? 0);
                $r = $proy > 0 ? $real / $proy : null;

                return [
                    'label' => [1 => 'ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC'][$m],
                    'value' => $m > $month ? null : $real,
                    'text' => $tn($real, 0),
                    'fill' => $r === null ? 'fill-gray' : ($r >= 0.95 ? 'fill-good' : ($r >= 0.8 ? 'fill-warn' : 'fill-bad')),
                ];
            })->all(),
        ])
        <p class="note">Verde: 95 % o más de lo proyectado en el mes; ámbar: 80–95 %; rojo: menos de 80 %. Por peso de llegada y mes de cargue.</p>
    </div>

    {{-- Avance por cliente. --}}
    <div class="section">
        <div class="section-title">Aceite (CPO) por cliente</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Cliente</th>
                    <th class="num">Proyectado {{ $monthName }}</th>
                    <th class="num">Despachado {{ $monthName }}</th>
                    <th class="num">% mes</th>
                    <th class="num">Proyectado año</th>
                    <th class="num">Despachado año</th>
                    <th class="num">% año</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($cpo['rows'] as $fila)
                    <tr>
                        <td>{{ $fila['name'] }}</td>
                        <td class="num">{{ $tn($fila['month_projected']) }}</td>
                        <td class="num">{{ $tn($fila['month_dispatched']) }}</td>
                        <td class="num {{ $clase($fila['month_ratio']) }}">{{ $pct($fila['month_ratio']) }}</td>
                        <td class="num">{{ $tn($fila['ytd_projected']) }}</td>
                        <td class="num">{{ $tn($fila['ytd_dispatched']) }}</td>
                        <td class="num {{ $clase($fila['ytd_ratio']) }}">{{ $pct($fila['ytd_ratio']) }}</td>
                    </tr>
                @endforeach
                <tr class="total">
                    <td>Total</td>
                    <td class="num">{{ $tn($cpo['month_projected']) }}</td>
                    <td class="num">{{ $tn($cpo['month_dispatched']) }}</td>
                    <td class="num">{{ $pct($s['oil_month_ratio']) }}</td>
                    <td class="num">{{ $tn($cpo['ytd_projected']) }}</td>
                    <td class="num">{{ $tn($cpo['ytd_dispatched']) }}</td>
                    <td class="num">{{ $pct($s['oil_ytd_ratio']) }}</td>
                </tr>
            </tbody>
        </table>
        @if ($pko['rows'] !== [])
            <p class="note">Palmiste (PKO): {{ $tn($pko['month_dispatched']) }} tn en {{ $monthName }} y {{ $tn($pko['ytd_dispatched']) }} tn en el año, de {{ $tn($pko['ytd_projected']) }} tn proyectadas.</p>
        @endif
    </div>

    {{-- Facturación del mes. --}}
    <div class="section">
        <div class="section-title">Facturación de {{ $monthName }}</div>
        @if ($invoicing === [])
            <p class="empty">No hubo facturas en el mes.</p>
        @else
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Producto</th>
                        <th class="num">Toneladas</th>
                        <th class="num">Facturado</th>
                        <th class="num">$ por tonelada</th>
                        <th class="num">Facturado en el año</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invoicing as $fila)
                        <tr>
                            <td>{{ $fila['name'] }}</td>
                            <td>{{ $fila['product'] }}</td>
                            <td class="num">{{ $fila['tons'] > 0 ? $tn($fila['tons']) : '' }}</td>
                            <td class="num">{{ $fila['amount'] > 0 ? $pesos($fila['amount']) : '' }}</td>
                            <td class="num">{{ $fila['price'] !== null ? $pesos($fila['price']) : '' }}</td>
                            <td class="num">{{ $pesos($fila['ytd_amount']) }}</td>
                        </tr>
                    @endforeach
                    <tr class="total">
                        <td colspan="3">Total</td>
                        <td class="num">{{ $pesos($s['invoiced_month']) }}</td>
                        <td class="num">{{ $pesos($s['oil_price_month']) }}</td>
                        <td class="num">{{ $pesos($s['invoiced_ytd']) }}</td>
                    </tr>
                </tbody>
            </table>
        @endif
    </div>

    {{-- Cartera. --}}
    <div class="section">
        <div class="section-title">Cartera por antigüedad (hoy)</div>
        @if ($aging === [])
            <p class="empty">No hay facturas por cobrar.</p>
        @else
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th class="num">Al día</th>
                        <th class="num">1–30 días</th>
                        <th class="num">31–60 días</th>
                        <th class="num">61–90 días</th>
                        <th class="num">Más de 90</th>
                        <th class="num">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($aging as $fila)
                        <tr>
                            <td>{{ $fila['name'] }}</td>
                            <td class="num">{{ $fila['current'] > 0 ? $millones($fila['current']) : '' }}</td>
                            @foreach (['d30', 'd60', 'd90', 'over90'] as $t)
                                <td class="num {{ $fila[$t] > 0 ? 'bad' : '' }}">{{ $fila[$t] > 0 ? $millones($fila[$t]) : '' }}</td>
                            @endforeach
                            <td class="num">{{ $millones($fila['total']) }}</td>
                        </tr>
                    @endforeach
                    <tr class="total">
                        <td>Total</td>
                        @foreach (['current', 'd30', 'd60', 'd90', 'over90', 'total'] as $t)
                            <td class="num">{{ $millones(array_sum(array_column($aging, $t))) }}</td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
            <p class="note">Una factura vence en su fecha más el plazo de pago de cada cliente.</p>
        @endif
    </div>

    {{-- Fruta. --}}
    <div class="section">
        <div class="section-title">Fruta por proveedor (tn)</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Proveedor</th>
                    <th class="num">Proyectada {{ $monthName }}</th>
                    <th class="num">Recibida {{ $monthName }}</th>
                    <th class="num">% mes</th>
                    <th class="num">Proyectada año</th>
                    <th class="num">Recibida año</th>
                    <th class="num">% año</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($fruit as $fila)
                    <tr>
                        <td>{{ $fila['name'] }}</td>
                        <td class="num">{{ $tn($fila['month_projected']) }}</td>
                        <td class="num">{{ $tn($fila['month_received']) }}</td>
                        <td class="num {{ $clase($fila['month_ratio']) }}">{{ $pct($fila['month_ratio']) }}</td>
                        <td class="num">{{ $tn($fila['ytd_projected']) }}</td>
                        <td class="num">{{ $tn($fila['ytd_received']) }}</td>
                        <td class="num {{ $clase($fila['ytd_ratio']) }}">{{ $pct($fila['ytd_ratio']) }}</td>
                    </tr>
                @endforeach
                <tr class="total">
                    <td>Total</td>
                    <td class="num">{{ $tn($s['fruit_month_projected']) }}</td>
                    <td class="num">{{ $tn($s['fruit_month_received']) }}</td>
                    <td class="num">{{ $pct($s['fruit_month_ratio']) }}</td>
                    <td class="num">{{ $tn($s['fruit_ytd_projected']) }}</td>
                    <td class="num">{{ $tn($s['fruit_ytd_received']) }}</td>
                    <td class="num">{{ $pct($s['fruit_ytd_ratio']) }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- Planta. --}}
    <div class="section">
        <div class="section-title">Planta en {{ $monthName }}</div>
        <table class="data-table">
            <tbody>
                <tr><td>Aceite esperado según el plan (fruta proyectada × TEA {{ $plant['tea'] === null ? '—' : number_format($plant['tea'], 2, ',', '.').' %' }})</td><td class="num">{{ $tn($plant['expected_cpo']) }} tn</td></tr>
                <tr><td>Aceite que daría la fruta recibida con esa TEA</td><td class="num">{{ $tn($plant['real_cpo_estimate']) }} tn</td></tr>
                <tr><td>Aceite producido según la medición de los tanques</td><td class="num">{{ $plant['produced'] > 0 ? $tn($plant['produced']).' tn' : 'Sin mediciones en el mes' }}</td></tr>
                <tr><td>Cargues de almendra a maquila</td><td class="num">{{ $plant['toll_loads'] }} · {{ $tn($plant['toll_cake_tons']) }} tn de torta{{ $plant['toll_balance'] !== null ? ' · saldo '.$pesos($plant['toll_balance']) : '' }}</td></tr>
                <tr><td>Inventario de palmiste y torta{{ $plant['inventory'] ? ' (corte del '.$plant['inventory']['date']->format('d/m/Y').')' : '' }}</td><td class="num">{{ $plant['inventory'] ? $tn($plant['inventory']['pko']).' tn PKO · '.$tn($plant['inventory']['cake']).' tn torta' : 'Sin cortes' }}</td></tr>
                <tr><td>Aceite en tanques{{ $plant['tanks'] ? ' (medido el '.$plant['tanks']['date']->format('d/m/Y').')' : '' }}</td><td class="num">{{ $plant['tanks'] ? ($plant['tanks']['stale'] ? 'Medición de hace '.$plant['tanks']['age_days'].' días' : $tn($plant['tanks']['total']).' tn') : 'Sin medir' }}</td></tr>
            </tbody>
        </table>
    </div>

    {{-- Ofertas abiertas. --}}
    @if ($offers !== [])
        <div class="section">
            <div class="section-title">Ofertas mercantiles abiertas</div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Oferta</th>
                        <th>Cliente</th>
                        <th>Producto</th>
                        <th class="num">Ofertadas (tn)</th>
                        <th class="num">Despachadas (tn)</th>
                        <th class="num">Saldo (tn)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($offers as $oferta)
                        <tr>
                            <td>{{ $oferta['number'] }}</td>
                            <td>{{ $oferta['customer'] }}</td>
                            <td>{{ $oferta['product'] }}</td>
                            <td class="num">{{ $tn($oferta['contracted']) }}</td>
                            <td class="num">{{ $tn($oferta['delivered']) }}</td>
                            <td class="num">{{ $tn($oferta['remaining']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

</div>
</body>
</html>
