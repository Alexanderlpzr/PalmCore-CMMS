@php
    use App\Domain\Reports\Services\FormatoHorasExtrasPdfService as Formato;

    $columnas = Formato::COLUMNS;
    $titulos = Formato::COLUMN_TITLES;
    $h = fn (float $horas): string => $horas > 0 ? rtrim(rtrim(number_format($horas, 2, ',', '.'), '0'), ',') : '';
    $pesos = fn (float $valor): string => '$ '.number_format($valor, 0, ',', '.');
    $hora = fn ($momento): string => $momento ? $momento->format('g:ia') : '';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
    {{-- El formato de la planta tal como se imprime en Excel: encabezado con el control
         documental, datos del trabajador, una fila por día y las columnas con su nombre
         completo. Domingos y festivos en rojo, como en la hoja. --}}
    @page { margin: 22px 24px 30px 24px; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'DejaVu Sans', sans-serif; color: #111; font-size: 7pt; }
    table { border-collapse: collapse; width: 100%; }
    .box td, .box th { border: 1px solid #222; }
    .head td { padding: 3px 5px; }
    .logo { width: 150px; text-align: center; }
    .logo img { max-height: 46px; max-width: 140px; }
    .title { text-align: center; font-size: 11pt; font-weight: bold; }
    .control { width: 150px; font-size: 7pt; font-weight: bold; }
    .who td { padding: 2px 5px; font-size: 7.5pt; }
    .who .label { font-weight: bold; width: 130px; background: #e6e6e6; }
    .who .value { text-align: center; }
    .grid { margin-top: 6px; }
    .grid th { background: #d9d9d9; font-size: 6.3pt; font-weight: bold; padding: 3px 2px; text-align: center; vertical-align: middle; }
    .grid th.bono { background: #f2e2bf; }
    .grid td { padding: 2px 3px; font-size: 6.8pt; }
    .date { text-align: center; white-space: nowrap; }
    .red { color: #d00000; }
    .num, .time { text-align: center; white-space: nowrap; }
    .bono { background: #fbf4e4; }
    .notes { font-size: 6.3pt; text-align: center; }
    .totals td { font-weight: bold; background: #efefef; }
    .calc { margin-top: 8px; width: 62%; }
    .calc th { background: #d9d9d9; font-size: 6.5pt; padding: 2px 4px; text-align: right; }
    .calc td { font-size: 7pt; padding: 2px 4px; text-align: right; }
    .calc .left { text-align: left; }
    .note { margin-top: 5px; font-size: 6.5pt; color: #444; }
    .warn { margin-top: 5px; padding: 4px 6px; border-left: 3px solid #9a5b06; background: #fdf3e2; font-size: 6.8pt; }
    .sign { margin-top: 26px; }
    .sign td { width: 30%; padding-top: 4px; border-top: 1px solid #111; font-size: 7pt; text-align: center; }
    .sign td.gap { border: 0; width: 5%; }
</style>
</head>
<body>

<table class="box head">
    <tr>
        <td rowspan="3" class="logo">
            @if ($logoBase64)
                <img src="{{ $logoBase64 }}" alt="">
            @else
                <strong>{{ $tenant?->name }}</strong>
            @endif
        </td>
        <td rowspan="3" class="title">FORMATO DE HORAS EXTRAS</td>
        <td class="control">CÓDIGO: {{ Formato::FORM_CODE }}</td>
    </tr>
    <tr><td class="control">VERSIÓN: {{ Formato::FORM_VERSION }}</td></tr>
    <tr><td class="control">FECHA: {{ Formato::FORM_DATE }}</td></tr>
</table>

<table class="box who" style="margin-top: 4px;">
    <tr>
        <td class="label">NOMBRE TRABAJADOR</td>
        <td class="value">{{ mb_strtoupper($employee->fullName()) }}</td>
        <td class="label" style="width: 100px;">IDENTIFICACIÓN</td>
        <td class="value" style="width: 120px;">{{ $employee->document_number }}</td>
    </tr>
    <tr>
        <td class="label">CARGO</td>
        <td class="value" colspan="3">{{ mb_strtoupper($employee->position ?? '') }}</td>
    </tr>
    <tr>
        <td class="label">JEFE INMEDIATO</td>
        <td class="value" colspan="3">{{ mb_strtoupper($supervisor ?? '') }}</td>
    </tr>
    <tr>
        <td class="label">PERIODO</td>
        <td class="value" colspan="3">Horas del {{ $from->format('d/m/Y') }} al {{ $to->format('d/m/Y') }} — nómina de {{ $periodStart->locale('es')->translatedFormat('F \d\e Y') }}</td>
    </tr>
</table>

<table class="box grid">
    <thead>
        <tr>
            <th style="width: 118px;">FECHA</th>
            <th>HORA INICIO</th>
            <th>FIN HORARIO LABORAL</th>
            <th>HORA SALIDA</th>
            @foreach ($columnas as $bolsa => $sigla)
                <th>{{ $titulos[$bolsa] }}</th>
            @endforeach
            <th style="width: 120px;">JUSTIFICACIÓN</th>
            @foreach ($bonusColumns as $bolsa)
                <th class="bono">BONO {{ $columnas[$bolsa] }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $fila)
            <tr>
                <td @class(['date', 'red' => $fila['surcharged']])>{{ $fila['date']->locale('es')->translatedFormat('l, j \d\e F \d\e Y') }}</td>
                <td class="time">{{ $hora($fila['entry']) }}</td>
                <td class="time">{{ $hora($fila['scheduledEnd']) }}</td>
                <td class="time">{{ $hora($fila['exit']) }}</td>
                @foreach (array_keys($columnas) as $bolsa)
                    <td class="num">{{ $h($fila['legal'][$bolsa]) }}</td>
                @endforeach
                <td class="notes">{{ $fila['notes'] }}</td>
                @foreach ($bonusColumns as $bolsa)
                    <td class="num bono">{{ $h($fila['bonus'][$bolsa]) }}</td>
                @endforeach
            </tr>
        @endforeach
        <tr class="totals">
            <td colspan="4">TOTAL HORAS</td>
            @foreach (array_keys($columnas) as $bolsa)
                <td class="num">{{ $h($legalTotals[$bolsa]) }}</td>
            @endforeach
            <td></td>
            @foreach ($bonusColumns as $bolsa)
                <td class="num bono">{{ $h($bonusTotals[$bolsa]) }}</td>
            @endforeach
        </tr>
    </tbody>
</table>

@if ($showValues)
    {{-- La calculadora de la hoja: salario entre el divisor, y cada clase con su factor. --}}
    <table class="box calc">
        <thead>
            <tr>
                <th class="left">Valor hora: {{ $pesos($salary) }} ÷ {{ number_format($divisor, 0, ',', '.') }} = {{ $pesos($hourValue) }}</th>
                <th>Factor</th>
                <th>Valor hora</th>
                <th>Horas</th>
                <th>Horas extras y recargos</th>
                @if ($bonusRule)
                    <th>Horas bono</th>
                    <th>Bonificación</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach ($calculator as $fila)
                <tr>
                    <td class="left">{{ $fila['label'] }} — {{ $fila['concept'] }}</td>
                    <td>{{ number_format($fila['factor'], 2, ',', '.') }}</td>
                    <td>{{ $pesos($fila['rate']) }}</td>
                    <td>{{ $h($fila['legalHours']) ?: '—' }}</td>
                    <td>{{ $fila['legalAmount'] > 0 ? $pesos($fila['legalAmount']) : '—' }}</td>
                    @if ($bonusRule)
                        <td>{{ $h($fila['bonusHours']) ?: '—' }}</td>
                        <td>{{ $fila['bonusAmount'] > 0 ? $pesos($fila['bonusAmount']) : '—' }}</td>
                    @endif
                </tr>
            @endforeach
            <tr class="totals">
                <td class="left" colspan="4">TOTAL</td>
                <td>{{ $pesos($legalAmount) }}</td>
                @if ($bonusRule)
                    <td></td>
                    <td>{{ $pesos($bonusAmount) }}</td>
                @endif
            </tr>
        </tbody>
    </table>
@endif

@if ($bonusRule && $bonusColumns)
    <p class="note">Bonificación constitutiva: las horas de los días del mes anterior y las extras que pasan del tope diario se pagan con el mismo valor como bonificación, que cuenta para seguridad social y prestaciones.</p>
@endif

@if ($unconfirmed > 0)
    <div class="warn">{{ $unconfirmed }} {{ $unconfirmed === 1 ? 'día sigue' : 'días siguen' }} sin confirmar en «Horas por confirmar». Lo que no se confirme no se paga.</div>
@endif

<table class="sign">
    <tr>
        <td>Vo. Bo. Jefe inmediato{{ $supervisor ? ' — '.$supervisor : '' }}</td>
        <td class="gap"></td>
        <td>{{ $employee->fullName() }} — Trabajador</td>
        <td class="gap"></td>
        <td>Talento humano</td>
    </tr>
</table>

</body>
</html>
