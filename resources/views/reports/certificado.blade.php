@php
    use App\Domain\HumanResources\Enums\CertificateType;
    use App\Domain\HumanResources\Support\EmployeeProfileOptions;
    use App\Domain\HumanResources\Support\SpanishWords as W;
    use App\Domain\Reports\Services\CertificatePdfService;

    $legal = $tenant->legalName();
    $nit = $tenant->tax_id ? 'NIT '.$tenant->tax_id : null;
    $nombre = $employee->fullName();
    $sexo = $employee->sex;
    $senor = match ($sexo) { 'F' => 'la señora', 'M' => 'el señor', default => 'el (la) señor(a)' };
    $identificado = match ($sexo) { 'F' => 'identificada', 'M' => 'identificado', default => 'identificado(a)' };
    $documento = mb_strtolower(EmployeeProfileOptions::DOCUMENT_TYPES[$employee->document_type ?? 'CC'] ?? 'cédula de ciudadanía');
    $numero = ctype_digit((string) $employee->document_number) ? number_format((int) $employee->document_number, 0, ',', '.') : (string) $employee->document_number;
    $expedida = $employee->document_issue_place ? ' expedida en '.$employee->document_issue_place : '';
    $quien = $identificado.' con '.$documento.' N° '.$numero.$expedida;
    $cargo = $employee->position ?: 'sin cargo registrado';
    $ciudad = $settings['city'] ?: 'la ciudad de la empresa';
    $dia = fn ($fecha) => $fecha->format('d/m/Y');
    $marca = fn (bool $si): string => $si ? '☒' : '☐';
    $contrato = $employee->contract_type;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
    {{-- Los formatos de talento humano tal como se imprimen en Word: encabezado con el
         control documental, el texto en carta y el pie con la razón social. --}}
    @page { margin: 34px 54px 96px 54px; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'DejaVu Sans', sans-serif; color: #111; font-size: 9.6pt; line-height: 1.5; }
    table { border-collapse: collapse; width: 100%; }
    .head td { border: 1px solid #333; padding: 3px 6px; vertical-align: middle; }
    .head .logo { width: 120px; text-align: center; }
    .head .logo img { max-height: 58px; max-width: 110px; }
    .head .name { text-align: center; font-weight: bold; font-size: 11pt; }
    .head .title { text-align: center; font-weight: bold; font-size: 9pt; }
    .head .k { width: 62px; font-weight: bold; font-size: 7.5pt; }
    .head .v { width: 92px; font-size: 7.5pt; text-align: center; }
    h1 { text-align: center; font-size: 12pt; margin: 22px 0 14px; letter-spacing: 0.5px; }
    p { text-align: justify; margin-bottom: 12px; }
    .grid td { border: 1px solid #333; padding: 4px 8px; vertical-align: top; }
    .grid .k { width: 32%; font-weight: bold; background: #f0f0f0; }
    .boxes { margin: 4px 0 14px; }
    .boxes td { padding: 2px 0; font-size: 9.4pt; }
    .sign { margin-top: 30px; }
    .sign img { max-height: 58px; max-width: 200px; display: block; margin-bottom: -6px; }
    .sign .line { width: 240px; border-top: 1px solid #111; padding-top: 3px; }
    .sign .who { font-weight: bold; }
    .two td { width: 50%; vertical-align: bottom; padding-right: 20px; }
    .foot { position: fixed; left: 0; right: 0; bottom: -78px; text-align: center; font-size: 7pt; color: #333; border-top: 1px solid #999; padding-top: 4px; line-height: 1.35; }
    .foot .small { color: #777; }
</style>
</head>
<body>

<table class="head">
    <tr>
        <td rowspan="4" class="logo">
            @if ($logoBase64)
                <img src="{{ $logoBase64 }}" alt="">
            @endif
        </td>
        <td rowspan="2" class="name">{{ mb_strtoupper($tenant->name) }}</td>
        <td class="k">Código:</td>
        <td class="v">{{ $type->code() }}</td>
    </tr>
    <tr>
        <td class="k">Versión:</td>
        <td class="v">{{ $type->version() }}</td>
    </tr>
    <tr>
        <td rowspan="2" class="title">{{ $type->heading() }}</td>
        <td class="k">Fecha:</td>
        <td class="v">{{ $type->formDate() }}</td>
    </tr>
    <tr>
        <td class="k">Página:</td>
        <td class="v">1 de 1</td>
    </tr>
</table>

@switch($type)
    @case(CertificateType::ActiveEmployment)
    @case(CertificateType::FormerEmployment)
        <h1>CERTIFICACIÓN LABORAL</h1>
        <p>El Área de Gestión Humana de {{ mb_strtoupper($legal) }} certifica:</p>
        <p>
            Que {{ $senor }} <strong>{{ $nombre }}</strong>, {{ $quien }},
            @if ($type === CertificateType::ActiveEmployment)
                labora en {{ $legal }}{{ $nit ? ', '.$nit : '' }}, desde el {{ W::date($o['start_date']) }},
            @else
                laboró en {{ $legal }}{{ $nit ? ', '.$nit : '' }}, desde el {{ W::date($o['start_date']) }} hasta el {{ W::date($o['end_date']) }},
            @endif
            desempeñando el cargo de <strong>{{ $cargo }}</strong>, con contrato de trabajo a término:
        </p>
        <table class="boxes">
            <tr>
                <td>{{ $marca($contrato === 'indefinido') }} Indefinido</td>
                <td>{{ $marca($contrato === 'fijo') }} Fijo</td>
                <td>{{ $marca($contrato === 'obra_labor') }} Obra o labor</td>
                <td>{{ $marca($contrato === 'aprendizaje') }} Aprendizaje</td>
            </tr>
        </table>
        @if ($type === CertificateType::ActiveEmployment && ($o['salary'] ?? null))
            <p><strong>Salario mensual:</strong> {{ W::capitalize(W::money((float) $o['salary'])) }}{{ filled($o['salary_note'] ?? null) ? ' '.trim($o['salary_note']) : '' }}.</p>
        @endif
        <p>Durante este tiempo demostró ser una persona responsable, respetuosa y dedicada a su trabajo.</p>
        <p>La presente certificación se expide en {{ $ciudad }}, a {{ W::daysOfMonth($o['issued_on']) }}.</p>
        <p>Atentamente,</p>
        @include('reports.partials.certificado-firma')
        @break

    @case(CertificateType::SeveranceWithdrawal)
        <h1>AUTORIZACIÓN DE RETIRO TOTAL DE CESANTÍAS</h1>
        <table class="grid" style="margin-bottom: 16px;">
            <tr><td class="k">Fecha</td><td>{{ $dia($o['issued_on']) }}</td></tr>
            <tr><td class="k">Señores</td><td>{{ $o['fund_name'] ?: 'Fondo de cesantías' }}</td></tr>
            <tr><td class="k">Asunto</td><td>Autorización de retiro total de cesantías</td></tr>
        </table>
        <p>El Área de Gestión Humana de {{ mb_strtoupper($legal) }}{{ $nit ? ', '.$nit : '' }}, por medio de la presente:</p>
        <p>
            <strong>Autoriza</strong> el retiro total de las cesantías consignadas a nombre de {{ $senor }} <strong>{{ $nombre }}</strong>,
            {{ $quien }}, quien laboró en la empresa desde el {{ W::date($o['start_date']) }} hasta el {{ W::date($o['end_date']) }}.
        </p>
        <p>Cordialmente,</p>
        @include('reports.partials.certificado-recibe')
        @break

    @case(CertificateType::Clearance)
        <h1>PAZ Y SALVO</h1>
        <p>
            Yo, <strong>{{ $nombre }}</strong>, {{ $quien }}, hago constar que la empresa {{ $legal }}{{ $tenant->tax_id ? ', identificada con NIT '.$tenant->tax_id : '' }},
            para la cual laboré en el cargo de <strong>{{ $cargo }}</strong>, desde el {{ W::date($o['start_date']) }} hasta el {{ W::date($o['end_date']) }},
            cumplió con todos los compromisos legales, laborales y prestacionales establecidos por la normatividad vigente.
        </p>
        <p>
            En consecuencia, declaro que la empresa se encuentra a <strong>PAZ Y SALVO</strong> por todo concepto conmigo hasta la fecha.
            Asimismo, manifiesto que al momento de mi retiro recibí a satisfacción los siguientes documentos:
        </p>
        <table class="boxes">
            @foreach (CertificatePdfService::DELIVERED_DOCUMENTS as $clave => $etiqueta)
                <tr><td>{{ $marca(in_array($clave, $o['delivered'], true)) }} {{ $etiqueta }}</td></tr>
            @endforeach
            <tr><td>{{ $marca(filled($o['delivered_other'] ?? null)) }} Otro: {{ filled($o['delivered_other'] ?? null) ? $o['delivered_other'] : '____________________________________________' }}</td></tr>
        </table>
        <p>Para constancia se firma el presente documento en {{ $ciudad }}, el {{ W::day($o['issued_on']) }} de {{ W::month($o['issued_on']) }} de {{ W::number($o['issued_on']->year) }} ({{ $o['issued_on']->year }}).</p>
        <table class="two sign">
            <tr>
                <td>
                    <div style="height: 58px;"></div>
                    <div class="line">
                        <div class="who">Trabajador que se retira</div>
                        <div>Nombre: {{ $nombre }}</div>
                        <div>C.C.: {{ $numero }}</div>
                        <div>Cargo: {{ $cargo }}</div>
                    </div>
                </td>
                <td>
                    @if ($signature)
                        <img src="{{ $signature }}" alt="">
                    @else
                        <div style="height: 58px;"></div>
                    @endif
                    <div class="line">
                        <div class="who">Por la empresa</div>
                        <div>Nombre: {{ $signer?->fullName() }}</div>
                        <div>C.C.: {{ $signer && ctype_digit((string) $signer->document_number) ? number_format((int) $signer->document_number, 0, ',', '.') : $signer?->document_number }}</div>
                        <div>Cargo: {{ $signer?->position }}</div>
                    </div>
                </td>
            </tr>
        </table>
        @break

    @case(CertificateType::ExitExam)
        <h1>AUTORIZACIÓN DE REALIZACIÓN EXAMEN DE EGRESO</h1>
        <table class="grid" style="margin-bottom: 16px;">
            <tr><td class="k">Fecha</td><td>{{ $dia($o['issued_on']) }}</td></tr>
            <tr><td class="k">Trabajador</td><td>{{ $nombre }}</td></tr>
            <tr><td class="k">C.C.</td><td>{{ $numero }}</td></tr>
            <tr><td class="k">Cargo y fecha de retiro</td><td>{{ $cargo }} — Retiro: {{ $dia($o['end_date']) }}</td></tr>
        </table>
        <p>
            El departamento de Gestión Humana de {{ $legal }} le informa que cuenta con cinco (5) días hábiles a partir de la fecha de su retiro
            para practicarse el examen médico ocupacional de egreso en la siguiente IPS.
        </p>
        <table class="grid" style="margin-bottom: 16px;">
            <tr><td class="k">IPS y dirección</td><td>{{ $o['exit_exam_provider'] ?: '—' }}</td></tr>
            <tr><td class="k">Tipo de examen y énfasis</td><td>Examen médico ocupacional de egreso</td></tr>
            <tr><td class="k">Plazo máximo para presentarse</td><td>{{ $o['exit_exam_deadline'] ? $dia($o['exit_exam_deadline']) : '—' }}</td></tr>
        </table>
        <p>
            Estos exámenes médicos de retiro son por cuenta de la empresa. De no realizarse los exámenes médicos en el tiempo correspondiente,
            se dará por entendido que usted renuncia a este derecho.
        </p>
        <p style="margin-bottom: 2px;"><strong>Decisión</strong></p>
        <table class="boxes">
            <tr><td>☐ Acepto practicarme el examen médico de egreso.</td></tr>
            <tr><td>☐ No deseo practicarme el examen médico de egreso.</td></tr>
        </table>
        <p>Cordialmente,</p>
        @include('reports.partials.certificado-recibe')
        @break
@endswitch

<div class="foot">
    <div>{{ mb_strtoupper($legal) }}{{ $tenant->tax_id ? ' - NIT. '.$tenant->tax_id : '' }}</div>
    @if ($settings['contact_email'])
        <div>Correo electrónico: {{ $settings['contact_email'] }}</div>
    @endif
    @if ($tenant->address)
        <div>{{ $tenant->address }}</div>
    @endif
    <div class="small">Este documento es propiedad de {{ $tenant->name }}. Verifique que es la versión vigente antes de usarlo.</div>
</div>

</body>
</html>
