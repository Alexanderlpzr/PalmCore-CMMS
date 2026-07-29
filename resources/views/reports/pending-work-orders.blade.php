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
            </tr>
        </table>
    </div>

    @if($workOrders->isEmpty())
        <p style="color:#94a3b8; font-style:italic; text-align:center; padding:20px;">No hay órdenes de trabajo pendientes.</p>
    @else
    <table class="data-table">
        <thead>
            <tr>
                <th style="width:130px;">Equipo</th>
                <th>Título</th>
                <th>Descripción</th>
                <th style="width:60px;">Tipo</th>
                <th style="width:70px;">Área Mtto</th>
                <th style="width:60px;">Estado</th>
                <th style="width:75px;">Inicio planif.</th>
                <th style="width:45px;">Parado</th>
            </tr>
        </thead>
        <tbody>
            @foreach($workOrders as $wo)
            <tr>
                <td>
                    <strong>{{ $wo->equipment?->name ?? 'Sin equipo' }}</strong><br>
                    <span style="color:#64748b;font-size:8px;">
                        {{ $wo->equipment?->area?->name ?? '—' }} · {{ $wo->work_order_number }}
                    </span>
                </td>
                <td>{{ $wo->title }}</td>
                <td>{{ $wo->description ?? '—' }}</td>
                <td>{{ $wo->work_order_type->label() }}</td>
                <td>
                    @if($wo->maintenance_area)
                        @php
                            $areaBadge = match ($wo->maintenance_area->color()) {
                                'success', 'warning', 'danger', 'info', 'gray' => $wo->maintenance_area->color(),
                                default => 'gray',
                            };
                        @endphp
                        <span class="badge badge-{{ $areaBadge }}">{{ $wo->maintenance_area->label() }}</span>
                    @else
                        —
                    @endif
                </td>
                <td>
                    @php
                        $statusBadge = match ($wo->status->color()) {
                            'success', 'warning', 'danger', 'info', 'gray' => $wo->status->color(),
                            default => 'gray',
                        };
                    @endphp
                    <span class="badge badge-{{ $statusBadge }}">{{ $wo->status->label() }}</span>
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
