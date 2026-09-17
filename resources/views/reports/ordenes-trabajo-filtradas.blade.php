<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
    @include('reports.partials.styles')

    .filtro-chip { display: inline-block; background: #ecfdf5; border: 1px solid #a7f3d0; color: #047857;
                   border-radius: 10px; padding: 2px 8px; font-size: 8px; margin-right: 4px; }
</style>
</head>
<body>

@include('reports.partials.header')
@include('reports.partials.footer')

<div class="doc-body">

    <div class="report-title">
        <h1>Órdenes de Trabajo</h1>
        <p>{{ $ordenes->count() }} OT(s) · Generado el {{ $generatedAt->format('d/m/Y H:i') }}</p>
    </div>

    {{-- Qué se estaba mirando, arriba del todo. Un informe de un subconjunto que no dice
         cuál es un informe que engaña: quien lo reciba contará las OT que ve como si
         fueran todas. --}}
    <div class="text-block" style="margin-bottom:12px;">
        <strong>Filtros aplicados:</strong>
        @forelse ($filtros as $filtro)
            <span class="filtro-chip">{{ $filtro }}</span>
        @empty
            ninguno — este informe trae todas las órdenes de trabajo.
        @endforelse
    </div>

    @if ($ordenes->isEmpty())
        <p class="empty">Ninguna orden de trabajo coincide con estos filtros.</p>
    @else
        <div class="summary-box">
            <table>
                <tr>
                    <td>
                        <div class="summary-stat">{{ $ordenes->count() }}</div>
                        <div class="summary-label">Órdenes de trabajo</div>
                    </td>
                    <td>
                        <div class="summary-stat" style="{{ $conEquipoParado > 0 ? 'color:#dc2626' : '' }}">{{ $conEquipoParado }}</div>
                        <div class="summary-label">Con equipo detenido</div>
                    </td>
                    @unless ($mostrarEstado)
                        <td>
                            <div class="summary-stat">{{ $estados->first() }}</div>
                            <div class="summary-label">Estado de todas</div>
                        </td>
                    @endunless
                </tr>
            </table>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th style="width:130px;">Equipo</th>
                    <th>Descripción</th>
                    <th style="width:60px;">Tipo</th>
                    <th style="width:70px;">Área Mtto</th>
                    <th style="width:120px;">Responsable</th>
                    @if ($mostrarEstado)
                        <th style="width:65px;">Estado</th>
                    @endif
                    <th style="width:75px;">Fecha planificada</th>
                    @if ($mostrarEjecutada)
                        <th style="width:70px;">Fecha ejecutada</th>
                    @endif
                    <th style="width:45px;">Parado</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($ordenes as $wo)
                    <tr>
                        <td>
                            <strong>{{ $wo->equipment?->name ?? 'Sin equipo' }}</strong><br>
                            <span style="color:#64748b;font-size:8px;">
                                {{ $wo->equipment?->area?->name ?? '—' }} · {{ $wo->work_order_number }}
                            </span>
                        </td>
                        <td>{{ $wo->description ?? '—' }}</td>
                        <td>{{ $wo->work_order_type?->label() ?? '—' }}</td>
                        <td>{{ $wo->maintenance_area?->label() ?? '—' }}</td>
                        <td>{{ $wo->executed_by ?: '—' }}</td>
                        @if ($mostrarEstado)
                            <td>{{ $wo->status->label() }}</td>
                        @endif
                        <td>{{ $wo->planned_start_at?->format('d/m/Y') ?? '—' }}</td>
                        @if ($mostrarEjecutada)
                            <td>{{ $wo->actual_end_at?->format('d/m/Y') ?? '—' }}</td>
                        @endif
                        <td>{{ $wo->equipment_stopped ? 'Sí' : 'No' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

</div>
</body>
</html>
