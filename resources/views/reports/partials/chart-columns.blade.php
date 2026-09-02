@php
    $alto = $alto ?? 70;
    $mayor = collect($filas)->max('value') ?: 0;
    // Un mínimo de 2 px para que un mes flojo se vea como una columna corta y no como un
    // mes sin dato: son cosas distintas, y la ausencia se pinta abajo con un guion.
    $px = fn (?float $v): int => ($mayor > 0 && $v !== null) ? max(2, (int) round($v / $mayor * $alto)) : 0;
@endphp

{{-- Columnas para una serie mensual.

     Es la forma que deja ver la tendencia, que es justo lo que una tabla de números no
     da: en la reunión se pregunta si el mes viene subiendo, no cuánto valió cada uno.
     La tabla sigue debajo para quien quiera la cifra exacta. --}}
<table class="chart-cols">
    <tr>
        @foreach ($filas as $fila)
            <td style="height: {{ $alto + 14 }}px;">
                @if ($fila['value'] === null)
                    <div class="chart-col-value">—</div>
                @else
                    <div class="chart-col-value">{{ $fila['text'] }}</div>
                    <div class="chart-col {{ $fila['fill'] ?? 'fill-good' }}"
                         style="height: {{ $px((float) $fila['value']) }}px;"></div>
                @endif
            </td>
        @endforeach
    </tr>
    <tr>
        @foreach ($filas as $fila)
            <td class="chart-col-label">{{ $fila['label'] }}</td>
        @endforeach
    </tr>
</table>
