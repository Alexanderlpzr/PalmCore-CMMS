<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #1e293b; background: #fff; }

    #header { position: fixed; top: -50px; left: 0; right: 0; height: 50px; }
    #footer { position: fixed; bottom: -30px; left: 0; right: 0; height: 30px; }

    .report-title { background: #1e3a5f; color: #fff; padding: 10px 14px; margin-bottom: 14px; border-radius: 3px; }
    .report-title h1 { font-size: 15px; font-weight: bold; }
    .report-title p { font-size: 9px; color: #93c5fd; margin-top: 2px; }

    .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 8px; font-weight: bold; }
    .badge-success { background: #dcfce7; color: #166534; }
    .badge-warning { background: #fef9c3; color: #854d0e; }
    .badge-danger  { background: #fee2e2; color: #991b1b; }
    .badge-gray    { background: #f1f5f9; color: #475569; }

    .section { margin-bottom: 14px; page-break-inside: avoid; }
    .section-title { font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.05em;
                     color: #1e3a5f; border-bottom: 1px solid #e2e8f0; padding-bottom: 3px; margin-bottom: 7px; }

    .grid-2 { width: 100%; }
    .grid-2 td { width: 50%; vertical-align: top; padding: 0 6px 0 0; }

    .field-label { font-size: 8px; color: #64748b; margin-bottom: 1px; }
    .field-value { font-size: 10px; color: #1e293b; margin-bottom: 8px; }

    .kpi-box { background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 3px; padding: 8px; text-align: center; }
    .kpi-value { font-size: 18px; font-weight: bold; color: #1e3a5f; }
    .kpi-label { font-size: 8px; color: #64748b; margin-top: 2px; }

    table.kpi-grid { width: 100%; }
    table.kpi-grid td { width: 16.6%; padding: 0 4px 0 0; vertical-align: top; }
</style>
</head>
<body>

<div id="header">
    @include('reports.partials.header')
</div>
<div id="footer">
    @include('reports.partials.footer')
</div>

<div style="padding-top: 60px; padding-bottom: 35px;">

    <div class="report-title">
        <h1>Ficha Técnica — {{ $equipment->code }}</h1>
        <p>{{ $equipment->name }}</p>
    </div>

    {{-- Status badge --}}
    <div style="margin-bottom: 14px;">
        <span class="badge badge-{{ $equipment->status->value === 'active' ? 'success' : ($equipment->status->value === 'under_maintenance' ? 'warning' : 'danger') }}">
            {{ $equipment->status->label() }}
        </span>&nbsp;
        @if($equipment->criticality)
        <span class="badge badge-{{ $equipment->criticality->value === 'critical' ? 'danger' : 'gray' }}">
            {{ $equipment->criticality->label() }}
        </span>
        @endif
    </div>

    {{-- 1. General --}}
    <div class="section">
        <div class="section-title">Identificación</div>
        <table class="grid-2">
            <tr>
                <td>
                    <div class="field-label">Código</div>
                    <div class="field-value">{{ $equipment->code }}</div>

                    <div class="field-label">Nombre</div>
                    <div class="field-value">{{ $equipment->name }}</div>

                    <div class="field-label">Modelo</div>
                    <div class="field-value">{{ $equipment->model ?? '—' }}</div>

                    <div class="field-label">N° de serie</div>
                    <div class="field-value">{{ $equipment->serial_number ?? '—' }}</div>

                    <div class="field-label">Tag de activo</div>
                    <div class="field-value">{{ $equipment->asset_tag ?? '—' }}</div>
                </td>
                <td>
                    <div class="field-label">Planta</div>
                    <div class="field-value">{{ $equipment->plant?->name ?? '—' }}</div>

                    <div class="field-label">Área</div>
                    <div class="field-value">{{ $equipment->area?->name ?? '—' }}</div>

                    <div class="field-label">Categoría</div>
                    <div class="field-value">{{ $equipment->category?->name ?? '—' }}</div>

                    <div class="field-label">Fabricante</div>
                    <div class="field-value">{{ $equipment->manufacturer?->name ?? '—' }}</div>

                    <div class="field-label">Proveedor</div>
                    <div class="field-value">{{ $equipment->supplier?->name ?? '—' }}</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- 2. Financial & Lifecycle --}}
    <div class="section">
        <div class="section-title">Datos Financieros y Ciclo de Vida</div>
        <table class="grid-2">
            <tr>
                <td>
                    <div class="field-label">Fecha de compra</div>
                    <div class="field-value">{{ $equipment->purchase_date?->format('d/m/Y') ?? '—' }}</div>

                    <div class="field-label">Fecha de instalación</div>
                    <div class="field-value">{{ $equipment->installation_date?->format('d/m/Y') ?? '—' }}</div>

                    <div class="field-label">Fecha de puesta en servicio</div>
                    <div class="field-value">{{ $equipment->commissioning_date?->format('d/m/Y') ?? '—' }}</div>

                    <div class="field-label">Vida útil (años)</div>
                    <div class="field-value">{{ $equipment->useful_life_years ?? '—' }}</div>
                </td>
                <td>
                    <div class="field-label">Precio de compra</div>
                    <div class="field-value">
                        {{ $equipment->purchase_price ? number_format($equipment->purchase_price, 2).' '.$equipment->currency_code : '—' }}
                    </div>

                    <div class="field-label">Costo de reemplazo</div>
                    <div class="field-value">
                        {{ $equipment->replacement_cost ? number_format($equipment->replacement_cost, 2).' '.$equipment->currency_code : '—' }}
                    </div>

                    <div class="field-label">Garantía hasta</div>
                    <div class="field-value">{{ $equipment->warranty_expiry_date?->format('d/m/Y') ?? '—' }}</div>

                    <div class="field-label">Última falla</div>
                    <div class="field-value">{{ $equipment->last_failure_at?->format('d/m/Y') ?? '—' }}</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- 3. Reliability KPIs --}}
    @if($equipment->kpi)
    <div class="section">
        <div class="section-title">KPIs de Confiabilidad</div>
        <table class="kpi-grid">
            <tr>
                <td>
                    <div class="kpi-box">
                        <div class="kpi-value">{{ $equipment->kpi->availability_percentage ? number_format($equipment->kpi->availability_percentage, 1).'%' : '—' }}</div>
                        <div class="kpi-label">Disponibilidad</div>
                    </div>
                </td>
                <td>
                    <div class="kpi-box">
                        <div class="kpi-value">{{ $equipment->kpi->mtbf_hours ? number_format($equipment->kpi->mtbf_hours, 1).'h' : '—' }}</div>
                        <div class="kpi-label">MTBF</div>
                    </div>
                </td>
                <td>
                    <div class="kpi-box">
                        <div class="kpi-value">{{ $equipment->kpi->mttr_hours ? number_format($equipment->kpi->mttr_hours, 1).'h' : '—' }}</div>
                        <div class="kpi-label">MTTR</div>
                    </div>
                </td>
                <td>
                    <div class="kpi-box">
                        <div class="kpi-value">{{ $equipment->kpi->failure_count ?? 0 }}</div>
                        <div class="kpi-label">Fallas</div>
                    </div>
                </td>
                <td>
                    <div class="kpi-box">
                        <div class="kpi-value">{{ $equipment->kpi->downtime_hours ? number_format($equipment->kpi->downtime_hours, 1).'h' : '0h' }}</div>
                        <div class="kpi-label">Tiempo parada</div>
                    </div>
                </td>
                <td>
                    <div class="kpi-box">
                        <div class="kpi-value" style="font-size:12px;">
                            {{ $equipment->kpi->last_calculated_at?->format('d/m/Y') ?? '—' }}
                        </div>
                        <div class="kpi-label">Última actualización</div>
                    </div>
                </td>
            </tr>
        </table>
    </div>
    @endif

    {{-- 4. Technical Specs --}}
    @if($equipment->technical_specs)
    <div class="section">
        <div class="section-title">Especificaciones Técnicas</div>
        <table class="data-table" style="width:100%; border-collapse:collapse; font-size:9px;">
            <thead>
                <tr>
                    <th style="background:#f1f5f9; color:#475569; padding:4px 6px; border:1px solid #e2e8f0; font-size:8px;">Parámetro</th>
                    <th style="background:#f1f5f9; color:#475569; padding:4px 6px; border:1px solid #e2e8f0; font-size:8px;">Valor</th>
                </tr>
            </thead>
            <tbody>
                @foreach((array)$equipment->technical_specs as $key => $value)
                <tr>
                    <td style="padding:4px 6px; border:1px solid #e2e8f0;">{{ $key }}</td>
                    <td style="padding:4px 6px; border:1px solid #e2e8f0;">{{ $value }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- 5. Notes --}}
    @if($equipment->notes)
    <div class="section">
        <div class="section-title">Notas</div>
        <div style="font-size:9px; line-height:1.5; color:#374151; background:#f8fafc;
                    border:1px solid #e2e8f0; border-radius:3px; padding:6px;">
            {{ $equipment->notes }}
        </div>
    </div>
    @endif

</div>
</body>
</html>
