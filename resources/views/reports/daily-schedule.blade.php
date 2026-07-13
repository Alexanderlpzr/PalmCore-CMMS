<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
    @include('reports.partials.styles')

    {{-- El técnico escribe encima del papel: las columnas en blanco son parte del
         documento, no un descuido. Sin ellas el planificador imprime esto y sigue
         llevando su cuaderno. --}}
    .write-in { background: #f8fafc; }
    .tech-header { background: #f1f5f9; padding: 6px 10px; margin: 12px 0 0; border-left: 3px solid #059669; }
    .tech-header h2 { font-size: 11px; font-weight: bold; }
    .tech-header span { font-size: 9px; color: #64748b; }
    .unassigned { border-left-color: #dc2626; }
    .contractor { border-left-color: #2563eb; }
    .sign-row { margin-top: 8px; font-size: 9px; color: #64748b; }
</style>
</head>
<body>

@include('reports.partials.header')
@include('reports.partials.footer')

<div class="doc-body">

    <div class="report-title">
        <h1>Programa de Mantenimiento — {{ $day->translatedFormat('l d \d\e F \d\e Y') }}</h1>
        <p>
            {{ $plant?->name ?? 'Todas las plantas' }} ·
            {{ $work_orders->count() }} OT(s) ·
            Generado el {{ $generatedAt->format('d/m/Y H:i') }}
        </p>
    </div>

    <div class="summary-box">
        <table>
            <tr>
                <td>
                    <div class="summary-stat">{{ $work_orders->count() }}</div>
                    <div class="summary-label">OT programadas</div>
                </td>
                <td>
                    <div class="summary-stat">{{ $planned_hours !== null ? number_format($planned_hours, 1).' h' : '—' }}</div>
                    <div class="summary-label">Horas planificadas</div>
                </td>
                <td>
                    <div class="summary-stat" style="{{ $stopped_count > 0 ? 'color:#dc2626' : '' }}">{{ $stopped_count }}</div>
                    <div class="summary-label">Requieren parar equipo</div>
                </td>
                <td>
                    <div class="summary-stat" style="{{ $overdue_count > 0 ? 'color:#dc2626' : '' }}">{{ $overdue_count }}</div>
                    <div class="summary-label">Atrasadas</div>
                </td>
                <td>
                    <div class="summary-stat" style="{{ $unassigned_count > 0 ? 'color:#dc2626' : '' }}">{{ $unassigned_count }}</div>
                    <div class="summary-label">Sin técnico</div>
                </td>
            </tr>
        </table>
    </div>

    @if($work_orders->isEmpty())
        <p style="color:#94a3b8; font-style:italic; text-align:center; padding:20px;">
            No hay trabajo programado para este día.
        </p>
    @else

        @foreach($groups as $group)
            <div class="tech-header {{ $group['technician'] === null ? 'unassigned' : '' }} {{ $group['is_contractor'] ? 'contractor' : '' }}">
                <h2>
                    {{ $group['technician'] ?? 'SIN ASIGNAR — el planificador reparte' }}
                    @if($group['is_contractor'])
                        <span class="badge badge-info">Contratista</span>
                    @endif
                </h2>
                <span>{{ $group['work_orders']->count() }} OT(s)</span>
            </div>

            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:85px;">N.º OT</th>
                        <th style="width:50px;">Prior.</th>
                        <th style="width:70px;">Equipo</th>
                        <th style="width:70px;">Área</th>
                        <th>Trabajo</th>
                        <th style="width:40px;">Paro</th>
                        <th style="width:45px;">Rep.</th>
                        <th style="width:40px;">Est.</th>
                        <th style="width:55px;" class="write-in">Inicio</th>
                        <th style="width:55px;" class="write-in">Fin</th>
                        <th style="width:80px;" class="write-in">Observaciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($group['work_orders'] as $wo)
                        @php
                            // Pedido y todavía sin reservar ni entregar: la OT no arranca.
                            $missingParts = $wo->parts
                                ->filter(fn ($part) => $part->status === App\Domain\Maintenance\Enums\WorkOrderPartStatus::Requested)
                                ->count();
                        @endphp
                        <tr>
                            <td>
                                {{ $wo->work_order_number }}
                                @if($schedule->isOverdue($wo, $day))
                                    <span class="badge badge-danger">Atrasada</span>
                                @endif
                            </td>
                            <td>{{ $wo->priority->label() }}</td>
                            <td>{{ $wo->equipment?->code ?? '—' }}</td>
                            <td>{{ $wo->equipment?->area?->name ?? '—' }}</td>
                            <td>
                                {{ $wo->title }}
                                {{-- El técnico tiene que saberlo antes de caminar hasta la máquina. --}}
                                @foreach($wo->required_permit_types ?? [] as $permit)
                                    @if($type = App\Domain\Maintenance\Enums\WorkPermitType::tryFrom($permit))
                                        <span class="badge badge-danger">{{ $type->label() }}</span>
                                    @endif
                                @endforeach
                            </td>
                            <td>{{ $wo->equipment_stopped ? 'SÍ' : 'No' }}</td>
                            {{-- Un repuesto que no está en bodega es una OT que no arranca:
                                 el técnico tiene que verlo antes de caminar hasta la máquina. --}}
                            <td>
                                @if($missingParts > 0)
                                    <span class="badge badge-danger">Faltan {{ $missingParts }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $wo->plannedHours() !== null ? number_format($wo->plannedHours(), 1).' h' : '—' }}</td>
                            <td class="write-in"></td>
                            <td class="write-in"></td>
                            <td class="write-in"></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <p class="sign-row">Firma del técnico: ______________________________</p>
        @endforeach

    @endif

</div>
</body>
</html>
