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
        <h1>Reporte de Inventario</h1>
        <p>Generado el {{ $generatedAt->format('d/m/Y \a \l\a\s H:i') }} — {{ $parts->count() }} repuestos</p>
    </div>

    {{-- Summary --}}
    @php
        $belowMinimum = $parts->filter(fn($p) => $p->warehouseStock->sum('current_stock') < $p->minimum_stock)->count();
        $totalValue = $parts->sum(fn($p) => $p->warehouseStock->sum(fn($ws) => $ws->current_stock * $ws->average_unit_cost));
        $activeCount = $parts->where('is_active', true)->count();
    @endphp

    <div class="summary-box">
        <table>
            <tr>
                <td>
                    <div class="summary-stat">{{ $parts->count() }}</div>
                    <div class="summary-label">Total repuestos</div>
                </td>
                <td>
                    <div class="summary-stat">{{ $activeCount }}</div>
                    <div class="summary-label">Activos</div>
                </td>
                <td>
                    <div class="summary-stat" style="{{ $belowMinimum > 0 ? 'color:#dc2626' : '' }}">{{ $belowMinimum }}</div>
                    <div class="summary-label">Bajo mínimo</div>
                </td>
                <td>
                    <div class="summary-stat">{{ number_format($totalValue, 2) }}</div>
                    <div class="summary-label">Valor total (costo prom.)</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- Parts Table --}}
    @if($parts->isEmpty())
        <p style="color:#94a3b8; font-style:italic; text-align:center; padding:20px;">Sin repuestos registrados.</p>
    @else
    <table class="data-table">
        <thead>
            <tr>
                <th style="width:70px;">Código</th>
                <th>Nombre</th>
                <th style="width:75px;">Categoría</th>
                <th style="width:60px;">Fabricante</th>
                <th style="width:40px;">Unidad</th>
                <th style="width:40px;">Stock</th>
                <th style="width:35px;">Mín.</th>
                <th style="width:65px;">Ubicación</th>
                <th style="width:60px;">Costo prom.</th>
                <th style="width:60px;">Valor total</th>
                <th style="width:62px;">Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach($parts as $part)
            @php
                $totalStock = $part->warehouseStock->sum('current_stock');
                $isLow = $totalStock < $part->minimum_stock;
                $avgCost = $part->warehouseStock->avg('average_unit_cost') ?? $part->unit_cost;
                $totalVal = $part->warehouseStock->sum(fn($ws) => $ws->current_stock * $ws->average_unit_cost);
                $locations = $part->warehouseStock->filter(fn($ws) => $ws->bin_location)->pluck('bin_location')->implode(', ');
            @endphp
            <tr>
                <td>{{ $part->code }}</td>
                <td>{{ $part->name }}</td>
                <td>{{ $part->category_type?->label() ?? '—' }}</td>
                <td>{{ $part->manufacturer?->name ?? '—' }}</td>
                <td>{{ $part->unit?->value ?? '—' }}</td>
                <td class="{{ $isLow ? 'stock-low' : 'stock-ok' }}">{{ rtrim(rtrim(number_format($totalStock, 2), '0'), '.') ?: '0' }}</td>
                <td>{{ rtrim(rtrim(number_format($part->minimum_stock ?? 0, 2), '0'), '.') ?: '0' }}</td>
                <td>{{ $locations ?: '—' }}</td>
                <td>{{ $avgCost ? number_format($avgCost, 2) : '—' }}</td>
                <td>{{ $totalVal > 0 ? number_format($totalVal, 2) : '—' }}</td>
                <td>
                    <span class="badge {{ $part->is_active ? 'badge-success' : 'badge-gray' }}">
                        {{ $part->is_active ? 'Activo' : 'Inactivo' }}
                    </span>
                    @if($isLow)
                        <span class="badge badge-danger">Bajo mín.</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

</div>
</body>
</html>
