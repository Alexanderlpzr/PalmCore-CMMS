<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
    @include('reports.partials.styles')
</style>
</head>
<body>

@include('reports.partials.header')
@include('reports.partials.footer')

<div class="doc-body">

    <div class="report-title">
        <h1>Órdenes de Trabajo Pendientes</h1>
        <p>Generado el {{ $generatedAt->format('d/m/Y \a \l\a\s H:i') }} — {{ $workOrders->count() }} OT(s) pendiente(s)</p>
    </div>

    @php
        $needsTechnician = $workOrders->filter(fn ($wo) => $wo->status->value === 'draft' && $wo->technicians->isEmpty())->count();
        $stopped = $workOrders->where('equipment_stopped', true)->count();
    @endphp

    <div class="summary-box">
        <table>
            <tr>
                <td>
                    <div class="summary-stat">{{ $workOrders->count() }}</div>
                    <div class="summary-label">Total pendientes</div>
                </td>
                <td>
                    <div class="summary-stat" style="{{ $stopped > 0 ? 'color:#dc2626' : '' }}">{{ $stopped }}</div>
                    <div class="summary-label">Con equipo detenido</div>
                </td>
                <td>
                    <div class="summary-stat" style="{{ $needsTechnician > 0 ? 'color:#dc2626' : '' }}">{{ $needsTechnician }}</div>
                    <div class="summary-label">Sin técnico asignado</div>
                </td>
            </tr>
        </table>
    </div>

    @if($workOrders->isEmpty())
        <p style="color:#94a3b8; font-style:italic; text-align:center; padding:20px;">No hay órdenes de trabajo pendientes.</p>
    @else
    <table class="data-table">
        <thead>
            <tr>
                <th style="width:90px;">N.º OT</th>
                <th>Título</th>
                <th style="width:70px;">Equipo</th>
                <th style="width:70px;">Planta / Área</th>
                <th style="width:55px;">Tipo</th>
                <th style="width:55px;">Prioridad</th>
                <th style="width:60px;">Estado</th>
                <th>Técnico(s)</th>
                <th style="width:70px;">Inicio planif.</th>
                <th style="width:45px;">Parado</th>
            </tr>
        </thead>
        <tbody>
            @foreach($workOrders as $wo)
            <tr>
                <td>{{ $wo->work_order_number }}</td>
                <td>{{ $wo->title }}</td>
                <td>{{ $wo->equipment?->code ?? '—' }}</td>
                <td>
                    {{ $wo->equipment?->plant?->name ?? '—' }}
                    @if($wo->equipment?->area) / {{ $wo->equipment->area->name }} @endif
                </td>
                <td>{{ $wo->work_order_type->label() }}</td>
                <td>{{ $wo->priority->label() }}</td>
                <td>
                    @php
                        $statusBadge = match ($wo->status->color()) {
                            'success', 'warning', 'danger', 'info', 'gray' => $wo->status->color(),
                            default => 'gray',
                        };
                    @endphp
                    <span class="badge badge-{{ $statusBadge }}">{{ $wo->status->label() }}</span>
                </td>
                <td>
                    {{ $wo->technicians->map(fn ($t) => $t->user?->name)->filter()->implode(', ') ?: '—' }}
                    @if($wo->status->value === 'draft' && $wo->technicians->isEmpty())
                        <span class="badge badge-danger">Falta técnico</span>
                    @endif
                </td>
                <td>{{ $wo->planned_start_at?->format('d/m/Y H:i') ?? '—' }}</td>
                <td>{{ $wo->equipment_stopped ? 'Sí' : 'No' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

</div>
</body>
</html>
