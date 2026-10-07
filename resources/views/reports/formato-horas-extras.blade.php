@php
    $columnas = \App\Domain\Reports\Services\FormatoHorasExtrasPdfService::COLUMNS;
    $h = fn (float $horas): string => $horas > 0 ? rtrim(rtrim(number_format($horas, 2, ',', '.'), '0'), ',') : '';
    $pesos = fn (float $valor): string => '$ '.number_format($valor, 0, ',', '.');
    $hora = fn ($momento): string => $momento ? $momento->locale('es')->translatedFormat('g:i a') : '';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
    @include('reports.partials.styles')

    /* El formato de la planta, en apaisado: una fila por día y una columna por clase de
       hora, como el de Excel. Las de bonificación van sombreadas y solo aparecen si en
       el periodo hay algo que pagar así. */
    .grid { width: 100%; border-collapse: collapse; margin-top: 4px; }
    .grid th {
        font-size: 6.5pt; text-transform: uppercase; letter-spacing: .04em; padding: 3px 2px;
        border-bottom: 1px solid #cfdde3; color: #2f5f75; text-align: center; vertical-align: bottom;
    }
    .grid td { font-size: 7pt; padding: 2px 3px; border-bottom: 1px solid #eef3f5; }
    .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .time { text-align: center; white-space: nowrap; }
    .bono { background: #f6f1e6; }
    .surcharged .date { color: #9a1c1c; font-weight: bold; }
    .totals td { border-top: 1px solid #cfdde3; font-weight: bold; }
    .notes { font-size: 6.5pt; color: #568297; }
    .calc { width: 100%; border-collapse: collapse; margin-top: 4px; }
    .calc th { font-size: 7pt; text-transform: uppercase; padding: 3px 4px; border-bottom: 1px solid #cfdde3; color: #2f5f75; text-align: right; }
    .calc th.left, .calc td.left { text-align: left; }
    .calc td { font-size: 7.5pt; padding: 2px 4px; border-bottom: 1px solid #eef3f5; text-align: right; }
    .warn { margin-top: 8px; padding: 6px 8px; border-left: 3px solid #9a5b06; background: #fdf3e2; font-size: 7.5pt; }
    .sign-row { margin-top: 30px; width: 100%; }
    .sign-row td { width: 30%; padding-top: 24px; border-top: 1px solid #011c27; font-size: 7.5pt; text-align: center; }
    .sign-spacer { border: 0 !important; width: 5% !important; }
</style>
</head>
<body>

@include('reports.partials.header')
@include('reports.partials.footer')

<div class="doc-body">

    <div class="report-title">
        <h1>Formato de horas extras</h1>
        <p>Horas del {{ $from->format('d/m/Y') }} al {{ $to->format('d/m/Y') }} — nómina de {{ $periodStart->locale('es')->translatedFormat('F \d\e Y') }}</p>
    </div>

    <div class="section">
        <table class="grid-2">
            <tr>
                <td>
                    <div class="field-label">Nombre del trabajador</div>
                    <div class="field-value">{{ $employee->fullName() }}</div>
                </td>
                <td>
                    <div class="field-label">Identificación</div>
                    <div class="field-value">{{ $employee->document_number }}</div>
                </td>
                <td>
                    <div class="field-label">Cargo</div>
                    <div class="field-value">{{ $employee->position ?? '—' }}</div>
                </td>
                <td>
                    <div class="field-label">Salario</div>
                    <div class="field-value">{{ $pesos($salary) }}</div>
                </td>
            </tr>
        </table>
    </div>

    <table class="grid">
        <thead>
            <tr>
                <th rowspan="2" style="text-align: left;">Fecha</th>
                <th rowspan="2">Hora inicio</th>
                <th rowspan="2">Fin horario laboral</th>
                <th rowspan="2">Hora salida</th>
                <th colspan="{{ count($columnas) }}">Horas extras y recargos</th>
                @if ($bonusColumns)
                    <th colspan="{{ count($bonusColumns) }}" class="bono">Bonificación</th>
                @endif
                <th rowspan="2" style="text-align: left;">Justificación</th>
            </tr>
            <tr>
                @foreach ($columnas as $sigla)
                    <th>{{ $sigla }}</th>
                @endforeach
                @foreach ($bonusColumns as $bolsa)
                    <th class="bono">{{ $columnas[$bolsa] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $fila)
                <tr @class(['surcharged' => $fila['surcharged']])>
                    <td class="date">{{ $fila['date']->locale('es')->translatedFormat('D d/m') }}</td>
                    <td class="time">{{ $hora($fila['entry']) }}</td>
                    <td class="time">{{ $hora($fila['scheduledEnd']) }}</td>
                    <td class="time">{{ $hora($fila['exit']) }}</td>
                    @foreach (array_keys($columnas) as $bolsa)
                        <td class="num">{{ $h($fila['legal'][$bolsa]) }}</td>
                    @endforeach
                    @foreach ($bonusColumns as $bolsa)
                        <td class="num bono">{{ $h($fila['bonus'][$bolsa]) }}</td>
                    @endforeach
                    <td class="notes">{{ $fila['notes'] }}</td>
                </tr>
            @endforeach
            <tr class="totals">
                <td colspan="4">Total horas</td>
                @foreach (array_keys($columnas) as $bolsa)
                    <td class="num">{{ $h($legalTotals[$bolsa]) }}</td>
                @endforeach
                @foreach ($bonusColumns as $bolsa)
                    <td class="num bono">{{ $h($bonusTotals[$bolsa]) }}</td>
                @endforeach
                <td></td>
            </tr>
        </tbody>
    </table>

    {{-- La calculadora de la hoja: salario entre el divisor, y cada clase con su factor.
         Aquí sale de los parámetros vigentes, no de una fórmula copiada en cada hoja. --}}
    <div class="section" style="margin-top: 10px;">
        <div class="section-title">
            Valor hora: {{ $pesos($salary) }} ÷ {{ number_format($divisor, 0, ',', '.') }} = {{ $pesos($hourValue) }}
        </div>
        <table class="calc">
            <thead>
                <tr>
                    <th class="left">Clase</th>
                    <th>Factor</th>
                    <th>Valor por hora</th>
                    <th>Horas</th>
                    <th>Horas extras y recargos</th>
                    @if ($bonusRule)
                        <th>Horas</th>
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
                    <td class="left" colspan="4">Total</td>
                    <td>{{ $pesos($legalAmount) }}</td>
                    @if ($bonusRule)
                        <td></td>
                        <td>{{ $pesos($bonusAmount) }}</td>
                    @endif
                </tr>
            </tbody>
        </table>
    </div>

    @if ($bonusRule)
        <p class="notes" style="margin-top: 6px;">
            Bonificación constitutiva: las horas de los días del mes anterior y las extras que pasan del tope diario se pagan con el mismo valor como bonificación, que cuenta para seguridad social y prestaciones.
        </p>
    @endif

    @if ($unconfirmed > 0)
        <div class="warn">
            {{ $unconfirmed }} {{ $unconfirmed === 1 ? 'día sigue' : 'días siguen' }} sin confirmar en «Horas por confirmar». Lo que no se confirme no se paga.
        </div>
    @endif

    <table class="sign-row">
        <tr>
            <td>{{ $employee->fullName() }} — Trabajador</td>
            <td class="sign-spacer"></td>
            <td>Vo. Bo. Jefe inmediato</td>
            <td class="sign-spacer"></td>
            <td>Talento humano</td>
        </tr>
    </table>

</div>

</body>
</html>
