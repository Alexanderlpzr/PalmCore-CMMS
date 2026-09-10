@php
    use App\Domain\Reports\Support\GraficoTorta;

    $porciones = GraficoTorta::porciones($valores);
    $unidad = $unidad ?? '';
    $tamano = $tamano ?? 130;
@endphp

{{-- Una torta con su leyenda al lado.

     Va en <img> y no como <svg> en el HTML porque DomPDF ignora el segundo sin dar
     error — se comprobó leyendo el flujo de contenido del PDF, donde el sector en línea
     no dejaba ni una curva.

     La leyenda lleva el valor además del porcentaje. Una torta dice bien quién se lleva
     la mayor parte y mal cuánto es esa parte, y en una reunión se pregunta lo segundo. --}}
@if ($porciones === [])
    <p class="empty">Sin datos para este período.</p>
@else
    <table style="width:100%; border-collapse:collapse;">
        <tr>
            <td style="width:{{ $tamano + 10 }}px; vertical-align:middle; padding:0;">
                <img src="{{ GraficoTorta::svg($porciones, $tamano) }}"
                     style="width:{{ $tamano }}px; height:{{ $tamano }}px;"
                     alt="Reparto en torta">
            </td>
            <td style="vertical-align:middle; padding-left:10px;">
                <table style="width:100%; border-collapse:collapse; font-size:9px;">
                    @foreach ($porciones as $porcion)
                        <tr>
                            <td style="width:12px; padding:2px 0;">
                                <span style="display:inline-block; width:8px; height:8px; border-radius:2px;
                                             background:{{ $porcion['color'] }};"></span>
                            </td>
                            <td style="padding:2px 4px; color:#334155;">{{ $porcion['label'] }}</td>
                            <td style="padding:2px 0; text-align:right; font-weight:bold; white-space:nowrap;">
                                {{ number_format($porcion['percentage'], 1, ',', '.') }}%
                            </td>
                            <td style="padding:2px 0 2px 8px; text-align:right; color:#64748b; white-space:nowrap;">
                                {{ number_format($porcion['value'], $decimales ?? 0, ',', '.') }}{{ $unidad ? ' '.$unidad : '' }}
                            </td>
                        </tr>
                    @endforeach
                </table>
            </td>
        </tr>
    </table>
@endif
