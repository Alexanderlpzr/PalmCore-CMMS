{{-- Quien expide y el recibido del trabajador que se retira, lado a lado: así el documento cabe en una hoja. --}}
@php($cc = fn ($numero) => ctype_digit((string) $numero) ? number_format((int) $numero, 0, ',', '.') : $numero)
<table class="two sign">
    <tr>
        <td>
            @if ($signature)
                <img src="{{ $signature }}" alt="">
            @else
                <div style="height: 58px;"></div>
            @endif
            <div class="line">
                <div class="who">{{ $signer?->fullName() }}</div>
                <div>{{ $signer?->position }}</div>
            </div>
        </td>
        <td>
            <div style="height: 58px;"></div>
            <div class="line">
                <div class="who">Recibe</div>
                <div>Nombre: {{ $employee->fullName() }}</div>
                <div>Cargo: {{ $employee->position }}</div>
                <div>C.C.: {{ $cc($employee->document_number) }}</div>
                <div>Fecha: {{ $o['issued_on']->format('d/m/Y') }}</div>
            </div>
        </td>
    </tr>
</table>
