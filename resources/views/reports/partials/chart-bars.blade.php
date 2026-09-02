@php
    // La barra más larga manda la escala. Si todo vale cero no hay nada que comparar y
    // todas quedan vacías, que es más honesto que estirarlas al máximo.
    $mayor = collect($filas)->max('value') ?: 0;
    // El mínimo de 1,5% es para que un valor pequeño se vea, pero NO se aplica al cero:
    // dibujarle una astilla de barra afirmaría algo donde no hay nada, que es el mismo
    // error que poner cero donde falta el dato.
    $ancho = fn (float $v): float => ($mayor > 0 && $v > 0) ? max(1.5, round($v / $mayor * 100, 1)) : 0;
@endphp

{{-- Barras horizontales con su número al lado.

     El número va siempre, no solo cuando la barra es corta: en una impresión en blanco
     y negro —que es como acaba la mitad de los informes de una reunión— el largo se lee
     mal y el color no se lee. La barra ordena de un vistazo; el número es el dato. --}}
<table class="chart">
    @foreach ($filas as $fila)
        <tr>
            <td class="chart-name">{{ $fila['name'] }}</td>
            <td class="chart-plot">
                <div class="chart-track">
                    <div class="chart-fill {{ $fila['fill'] ?? 'fill-good' }}"
                         style="width: {{ $ancho((float) $fila['value']) }}%;"></div>
                </div>
            </td>
            <td class="chart-value">{{ $fila['text'] }}</td>
        </tr>
    @endforeach
</table>
