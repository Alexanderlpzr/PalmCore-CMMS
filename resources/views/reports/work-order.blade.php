<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
    @include('reports.partials.styles')
</style>
</head>
<body>

{{-- Running header/footer — the partials already provide the #header/#footer
     div DomPDF positions; wrapping them again created a duplicate id and
     silently broke rendering (see reports.partials.styles for the fix). --}}
@include('reports.partials.header')
@include('reports.partials.footer')

{{-- Main content — padding accounts for fixed header/footer --}}
<div class="doc-body">

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
                        @if($signatureImages[$sig->id] ?? null)
                            <img src="{{ $signatureImages[$sig->id] }}" style="max-height:40px;" alt="Firma">
                        @else
                            <div class="empty" style="height:40px; line-height:40px; font-size:8px;">Sin firma registrada</div>
                        @endif
                        <div class="signature-name">
                            <div style="font-weight:bold; color:#1e293b; font-size:9px;">{{ $sig->user?->name ?? '—' }}</div>
                            @if($sig->user?->email)
                                <div style="font-size:7px; color:#94a3b8;">{{ $sig->user->email }}</div>
                            @endif
                            <div style="font-size:7px; color:#64748b; margin-top:2px;">{{ $sig->signature_type->label() }} · {{ $sig->signed_at?->format('d/m/Y H:i') }}</div>
                            @if($signatureLocations[$sig->id] ?? null)
                                @php($loc = $signatureLocations[$sig->id])
                                <div style="font-size:7px; color:#94a3b8;">Ubicación: {{ number_format($loc->latitude, 4) }}, {{ number_format($loc->longitude, 4) }} (±{{ round($loc->accuracy) }} m)</div>
                            @endif
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
