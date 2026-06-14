<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #1e293b; background: #fff; }

    #header { position: fixed; top: -50px; left: 0; right: 0; height: 50px; }
    #footer { position: fixed; bottom: -30px; left: 0; right: 0; height: 30px; }
    .page { content: counter(page); }
    .topage { content: counter(pages); }

    .report-title { background: #1e3a5f; color: #fff; padding: 10px 14px; margin-bottom: 14px; border-radius: 3px; }
    .report-title h1 { font-size: 15px; font-weight: bold; }
    .report-title p { font-size: 9px; color: #93c5fd; margin-top: 2px; }

    .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 8px; font-weight: bold; }
    .badge-success { background: #dcfce7; color: #166534; }
    .badge-warning { background: #fef9c3; color: #854d0e; }
    .badge-danger  { background: #fee2e2; color: #991b1b; }
    .badge-info    { background: #dbeafe; color: #1e40af; }
    .badge-gray    { background: #f1f5f9; color: #475569; }

    .section { margin-bottom: 14px; page-break-inside: avoid; }
    .section-title { font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.05em;
                     color: #1e3a5f; border-bottom: 1px solid #e2e8f0; padding-bottom: 3px; margin-bottom: 7px; }

    .grid-2 { width: 100%; }
    .grid-2 td { width: 50%; vertical-align: top; padding: 0 6px 0 0; }

    .field-label { font-size: 8px; color: #64748b; margin-bottom: 1px; }
    .field-value { font-size: 10px; color: #1e293b; margin-bottom: 8px; }

    table.data-table { width: 100%; border-collapse: collapse; font-size: 9px; }
    table.data-table th { background: #f1f5f9; color: #475569; text-align: left; padding: 4px 6px;
                          font-weight: bold; border: 1px solid #e2e8f0; font-size: 8px; }
    table.data-table td { padding: 4px 6px; border: 1px solid #e2e8f0; vertical-align: top; }
    table.data-table tr:nth-child(even) td { background: #f8fafc; }

    .cost-total { background: #1e3a5f; color: #fff; padding: 6px 10px; text-align: right;
                  font-size: 11px; font-weight: bold; margin-top: 4px; border-radius: 3px; }

    .signature-box { border: 1px solid #e2e8f0; padding: 8px; min-height: 50px; text-align: center;
                     border-radius: 3px; }
    .signature-name { font-size: 8px; color: #475569; margin-top: 4px; border-top: 1px solid #e2e8f0;
                      padding-top: 3px; }

    .text-block { font-size: 9px; line-height: 1.5; color: #374151; background: #f8fafc;
                  border: 1px solid #e2e8f0; border-radius: 3px; padding: 6px; }

    .empty { color: #94a3b8; font-style: italic; }
</style>
</head>
<body>

{{-- Running header --}}
<div id="header">
    @include('reports.partials.header')
</div>

{{-- Running footer --}}
<div id="footer">
    @include('reports.partials.footer')
</div>

{{-- Main content — padding accounts for fixed header/footer --}}
<div style="padding-top: 60px; padding-bottom: 35px;">

    {{-- Report title bar --}}
    <div class="report-title">
        <h1>Orden de Trabajo — {{ $workOrder->work_order_number }}</h1>
        <p>{{ $workOrder->title }}</p>
    </div>

    {{-- Status / Priority badges --}}
    <div style="margin-bottom: 14px;">
        <span class="badge badge-info">{{ $workOrder->status->label() }}</span>&nbsp;
        <span class="badge badge-gray">{{ $workOrder->priority->label() }}</span>&nbsp;
        <span class="badge badge-gray">{{ $workOrder->work_order_type->label() }}</span>
    </div>

    {{-- 1. Basic Info --}}
    <div class="section">
        <div class="section-title">Información General</div>
        <table class="grid-2">
            <tr>
                <td>
                    <div class="field-label">Equipo</div>
                    <div class="field-value">{{ $workOrder->equipment?->name ?? '—' }}</div>

                    <div class="field-label">Planta / Área</div>
                    <div class="field-value">
                        {{ $workOrder->equipment?->plant?->name ?? '—' }}
                        @if($workOrder->equipment?->area)
                            / {{ $workOrder->equipment->area->name }}
                        @endif
                    </div>

                    <div class="field-label">Creado por</div>
                    <div class="field-value">{{ $workOrder->createdBy?->name ?? '—' }}</div>

                    <div class="field-label">Supervisor asignado</div>
                    <div class="field-value">{{ $workOrder->assignedSupervisor?->name ?? '—' }}</div>
                </td>
                <td>
                    <div class="field-label">Inicio planificado</div>
                    <div class="field-value">{{ $workOrder->planned_start_at?->format('d/m/Y H:i') ?? '—' }}</div>

                    <div class="field-label">Fin planificado</div>
                    <div class="field-value">{{ $workOrder->planned_end_at?->format('d/m/Y H:i') ?? '—' }}</div>

                    <div class="field-label">Inicio real</div>
                    <div class="field-value">{{ $workOrder->actual_start_at?->format('d/m/Y H:i') ?? '—' }}</div>

                    <div class="field-label">Fin real</div>
                    <div class="field-value">{{ $workOrder->actual_end_at?->format('d/m/Y H:i') ?? '—' }}</div>

                    <div class="field-label">Equipo detenido</div>
                    <div class="field-value">
                        {{ $workOrder->equipment_stopped ? 'Sí' : 'No' }}
                        @if($workOrder->downtime_minutes)
                            — {{ number_format($workOrder->downtime_minutes / 60, 1) }} h
                        @endif
                    </div>
                </td>
            </tr>
        </table>
    </div>

    {{-- 2. Description & Instructions --}}
    @if($workOrder->description || $workOrder->instructions)
    <div class="section">
        <div class="section-title">Descripción e Instrucciones</div>
        @if($workOrder->description)
            <div class="field-label">Descripción</div>
            <div class="text-block" style="margin-bottom:8px;">{{ $workOrder->description }}</div>
        @endif
        @if($workOrder->instructions)
            <div class="field-label">Instrucciones</div>
            <div class="text-block">{{ $workOrder->instructions }}</div>
        @endif
    </div>
    @endif

    {{-- 3. Technicians --}}
    @if($workOrder->technicians->isNotEmpty())
    <div class="section">
        <div class="section-title">Técnicos Asignados</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Técnico</th>
                    <th>Rol</th>
                    <th>Horas Plan.</th>
                    <th>Tarifa/h</th>
                </tr>
            </thead>
            <tbody>
                @foreach($workOrder->technicians as $tech)
                <tr>
                    <td>{{ $tech->user?->name ?? '—' }}</td>
                    <td>{{ $tech->role ?? '—' }}</td>
                    <td>{{ $tech->planned_hours ? number_format($tech->planned_hours, 1) : '—' }}</td>
                    <td>{{ $tech->hourly_rate ? number_format($tech->hourly_rate, 2) : '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- 4. Time Logs --}}
    @if($workOrder->timeLogs->isNotEmpty())
    <div class="section">
        <div class="section-title">Registro de Tiempos</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Técnico</th>
                    <th>Inicio</th>
                    <th>Fin</th>
                    <th>Horas</th>
                    <th>Descripción</th>
                </tr>
            </thead>
            <tbody>
                @foreach($workOrder->timeLogs as $log)
                <tr>
                    <td>{{ $log->user?->name ?? '—' }}</td>
                    <td>{{ $log->started_at?->format('d/m/Y H:i') ?? '—' }}</td>
                    <td>{{ $log->ended_at?->format('d/m/Y H:i') ?? '—' }}</td>
                    <td>{{ $log->hours ? number_format($log->hours, 2) : '—' }}</td>
                    <td>{{ $log->description ?? '' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- 5. Parts Used --}}
    @if($workOrder->parts->isNotEmpty())
    <div class="section">
        <div class="section-title">Repuestos Utilizados</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Descripción</th>
                    <th>Cant.</th>
                    <th>Unidad</th>
                    <th>Costo Unit.</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($workOrder->parts as $part)
                <tr>
                    <td>{{ $part->part_code ?? $part->sparePart?->code ?? '—' }}</td>
                    <td>{{ $part->description ?? $part->sparePart?->name ?? '—' }}</td>
                    <td>{{ $part->quantity }}</td>
                    <td>{{ $part->unit ?? $part->sparePart?->unit->value ?? '—' }}</td>
                    <td>{{ $part->unit_cost ? number_format($part->unit_cost, 2) : '—' }}</td>
                    <td>{{ $part->total_cost ? number_format($part->total_cost, 2) : '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- 6. Costs Summary --}}
    @if($workOrder->actual_cost_labor || $workOrder->actual_cost_parts || $workOrder->actual_cost_external)
    <div class="section">
        <div class="section-title">Resumen de Costos</div>
        <table style="width:300px; margin-left: auto;">
            <tr>
                <td class="field-label" style="padding:3px 8px;">Mano de obra:</td>
                <td style="text-align:right; padding:3px 8px; font-size:10px;">
                    {{ number_format((float)$workOrder->actual_cost_labor, 2) }} {{ $workOrder->currency_code ?? 'USD' }}
                </td>
            </tr>
            <tr>
                <td class="field-label" style="padding:3px 8px;">Repuestos:</td>
                <td style="text-align:right; padding:3px 8px; font-size:10px;">
                    {{ number_format((float)$workOrder->actual_cost_parts, 2) }} {{ $workOrder->currency_code ?? 'USD' }}
                </td>
            </tr>
            <tr>
                <td class="field-label" style="padding:3px 8px;">Externos:</td>
                <td style="text-align:right; padding:3px 8px; font-size:10px;">
                    {{ number_format((float)$workOrder->actual_cost_external, 2) }} {{ $workOrder->currency_code ?? 'USD' }}
                </td>
            </tr>
        </table>
        <div class="cost-total" style="width:300px; margin-left: auto; margin-top:4px;">
            Total: {{ number_format($workOrder->totalActualCost(), 2) }} {{ $workOrder->currency_code ?? 'USD' }}
        </div>
    </div>
    @endif

    {{-- 7. Failure Analysis --}}
    @if($workOrder->failure_cause || $workOrder->work_performed || $workOrder->root_cause)
    <div class="section">
        <div class="section-title">Análisis de Falla</div>
        @if($workOrder->failure_cause)
            <div class="field-label">Causa de falla</div>
            <div class="text-block" style="margin-bottom:8px;">{{ $workOrder->failure_cause }}</div>
        @endif
        @if($workOrder->work_performed)
            <div class="field-label">Trabajo realizado</div>
            <div class="text-block" style="margin-bottom:8px;">{{ $workOrder->work_performed }}</div>
        @endif
        @if($workOrder->root_cause)
            <div class="field-label">Causa raíz</div>
            <div class="text-block">{{ $workOrder->root_cause }}</div>
        @endif
    </div>
    @endif

    {{-- 8. Comments --}}
    @if($workOrder->comments->where('is_internal', false)->isNotEmpty())
    <div class="section">
        <div class="section-title">Comentarios</div>
        @foreach($workOrder->comments->where('is_internal', false) as $comment)
        <div style="border-left:3px solid #2563eb; padding:4px 8px; margin-bottom:6px; background:#f8fafc;">
            <div style="font-size:8px; color:#64748b;">
                {{ $comment->user?->name ?? '—' }} — {{ $comment->created_at->format('d/m/Y H:i') }}
            </div>
            <div style="font-size:9px; margin-top:2px;">{{ $comment->body }}</div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- 9. Signatures --}}
    @if($workOrder->signatures->isNotEmpty())
    <div class="section">
        <div class="section-title">Firmas</div>
        <table style="width:100%;">
            <tr>
                @foreach($workOrder->signatures as $sig)
                <td style="width:{{ intdiv(100, $workOrder->signatures->count()) }}%; padding: 0 8px 0 0; vertical-align:top;">
                    <div class="signature-box">
                        @if($sig->signature_path ?? null)
                            <img src="{{ $sig->signature_path }}" style="max-height:40px;" alt="Firma">
                        @else
                            <div style="height:40px;"></div>
                        @endif
                        <div class="signature-name">
                            {{ $sig->user?->name ?? '—' }}<br>
                            <span style="font-size:7px; color:#94a3b8;">{{ $sig->signature_type }}</span>
                        </div>
                    </div>
                </td>
                @endforeach
            </tr>
        </table>
    </div>
    @endif

</div>
</body>
</html>
