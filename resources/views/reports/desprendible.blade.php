<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
    @include('reports.partials.styles')

    /* El desprendible es un documento de dos columnas de cifras. Lo que se busca al
       recibirlo es el neto, y después el renglón que no se esperaba. */
    .money { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .lines { width: 100%; border-collapse: collapse; margin-top: 4px; }
    .lines th {
        text-align: left; font-size: 8pt; text-transform: uppercase; letter-spacing: .06em;
        padding: 4px 6px; border-bottom: 1px solid #cfdde3; color: #2f5f75;
    }
    .lines th.money { text-align: right; }
    .lines td { padding: 4px 6px; border-bottom: 1px solid #eef3f5; font-size: 9pt; }
    .lines tr:last-child td { border-bottom: 0; }
    .lines .detail { color: #568297; font-size: 8pt; }
    .subtotal td { border-top: 1px solid #cfdde3; font-weight: bold; padding-top: 6px; }
    .net-box {
        margin-top: 14px; padding: 10px 12px; background: #eef3f6;
        border: 1px solid #cfdde3; border-radius: 4px;
    }
    .net-box .label { font-size: 9pt; color: #2f5f75; text-transform: uppercase; letter-spacing: .08em; }
    .net-box .value { font-size: 16pt; font-weight: bold; text-align: right; }
    .warn {
        margin-top: 10px; padding: 8px 10px; border-left: 3px solid #9a5b06;
        background: #fdf3e2; font-size: 8pt;
    }
    .sign-row { margin-top: 34px; width: 100%; }
    .sign-row td { width: 50%; padding-top: 26px; border-top: 1px solid #011c27; font-size: 8pt; text-align: center; }
    .sign-spacer { border: 0 !important; width: 8% !important; }
</style>
</head>
<body>

@include('reports.partials.header')
@include('reports.partials.footer')

<div class="doc-body">

    <div class="report-title">
        <h1>Comprobante de pago de nómina</h1>
        <p>{{ $run->name }} — del {{ $run->period_start->format('d/m/Y') }} al {{ $run->period_end->format('d/m/Y') }}</p>
    </div>

    {{-- Identificación. Sale del renglón guardado, no del empleado: si la persona
         cambió de cargo en octubre, el comprobante de agosto sigue diciendo el de agosto. --}}
    <div class="section">
        <div class="section-title">Trabajador</div>
        <table class="grid-2">
            <tr>
                <td>
                    <div class="field-label">Nombre</div>
                    <div class="field-value">{{ $entry->employee_name }}</div>

                    <div class="field-label">Documento</div>
                    <div class="field-value">{{ $entry->document_number }}</div>
                </td>
                <td>
                    <div class="field-label">Cargo</div>
                    <div class="field-value">{{ $entry->position ?? '—' }}</div>

                    <div class="field-label">Salario básico mensual</div>
                    <div class="field-value">$ {{ number_format((float) $entry->base_salary, 0, ',', '.') }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Días del período</div>
        <table class="grid-2">
            <tr>
                <td>
                    <div class="field-label">Días trabajados</div>
                    <div class="field-value">{{ rtrim(rtrim(number_format((float) $entry->worked_days, 2, ',', '.'), '0'), ',') }}</div>
                </td>
                <td>
                    <div class="field-label">Días de novedad</div>
                    <div class="field-value">{{ rtrim(rtrim(number_format((float) $entry->novelty_days, 2, ',', '.'), '0'), ',') }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Devengado</div>
        <table class="lines">
            <thead>
                <tr>
                    <th>Concepto</th>
                    <th>Detalle</th>
                    <th class="money">Valor</th>
                </tr>
            </thead>
            <tbody>
                @foreach($earnings as $line)
                    <tr>
                        <td>{{ $line['concept'] }}</td>
                        <td class="detail">{{ $line['detail'] }}</td>
                        <td class="money">$ {{ number_format($line['amount'], 0, ',', '.') }}</td>
                    </tr>
                @endforeach
                <tr class="subtotal">
                    <td colspan="2">Total devengado</td>
                    <td class="money">$ {{ number_format((float) $entry->total_earned, 0, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Deducciones</div>
        <table class="lines">
            <thead>
                <tr>
                    <th>Concepto</th>
                    <th>Detalle</th>
                    <th class="money">Valor</th>
                </tr>
            </thead>
            <tbody>
                @forelse($deductions as $line)
                    <tr>
                        <td>{{ $line['concept'] }}</td>
                        <td class="detail">{{ $line['detail'] }}</td>
                        <td class="money">$ {{ number_format($line['amount'], 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="detail">Sin deducciones en el período.</td>
                    </tr>
                @endforelse
                <tr class="subtotal">
                    <td colspan="2">Total deducido</td>
                    <td class="money">$ {{ number_format((float) $entry->total_deducted, 0, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="net-box">
        <table style="width: 100%;">
            <tr>
                <td class="label">Neto a pagar</td>
                <td class="value">$ {{ number_format((float) $entry->net_pay, 0, ',', '.') }}</td>
            </tr>
        </table>
    </div>

    {{-- Las bases se imprimen porque son lo que se aporta y lo que se provisiona, y
         porque ninguna coincide con otra: verlas juntas es la forma de detectar que
         alguna quedó mal. --}}
    <div class="section" style="margin-top: 14px;">
        <div class="section-title">Bases de aporte y prestaciones</div>
        <table class="lines">
            <tbody>
                <tr>
                    <td>IBC de salud</td>
                    <td class="money">$ {{ number_format((float) $entry->ibc_health, 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td>IBC de pensión</td>
                    <td class="money">$ {{ number_format((float) $entry->ibc_pension, 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td>Base de prima y cesantías</td>
                    <td class="money">$ {{ number_format((float) $entry->severance_base, 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td>Base de vacaciones</td>
                    <td class="money">$ {{ number_format((float) $entry->vacation_base, 0, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    @if($entry->hasWarnings())
        <div class="warn">
            <strong>Avisos de esta liquidación:</strong>
            {{ implode(' · ', $entry->warnings) }}
        </div>
    @endif

    <table class="sign-row">
        <tr>
            <td>Vo. Bo. firma autorizada</td>
            <td class="sign-spacer"></td>
            <td>{{ $entry->employee_name }} — Doc. {{ $entry->document_number }}</td>
        </tr>
    </table>

</div>

</body>
</html>
