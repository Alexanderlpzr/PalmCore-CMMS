<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #1e293b; background: #fff; }

    #header { position: fixed; top: -50px; left: 0; right: 0; height: 50px; }
    #footer { position: fixed; bottom: -30px; left: 0; right: 0; height: 30px; }

    .report-title { background: #1e3a5f; color: #fff; padding: 8px 12px; margin-bottom: 12px; border-radius: 3px; }
    .report-title h1 { font-size: 14px; font-weight: bold; }
    .report-title p { font-size: 9px; color: #93c5fd; margin-top: 2px; }

    .badge { display: inline-block; padding: 1px 6px; border-radius: 8px; font-size: 7px; font-weight: bold; }
    .badge-success { background: #dcfce7; color: #166534; }
    .badge-danger  { background: #fee2e2; color: #991b1b; }
    .badge-gray    { background: #f1f5f9; color: #475569; }

    table.data-table { width: 100%; border-collapse: collapse; font-size: 8px; }
    table.data-table th { background: #1e3a5f; color: #fff; text-align: left; padding: 5px 5px;
                          font-weight: bold; border: 1px solid #1e3a5f; font-size: 7px; }
    table.data-table td { padding: 4px 5px; border: 1px solid #e2e8f0; vertical-align: top; }
    table.data-table tr:nth-child(even) td { background: #f8fafc; }
    table.data-table tr:hover td { background: #eff6ff; }

    .stock-low { color: #dc2626; font-weight: bold; }
    .stock-ok  { color: #16a34a; }

    .summary-box { background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 3px; padding: 8px 12px;
                   margin-bottom: 12px; }
    .summary-box table td { padding: 0 16px 0 0; }
    .summary-stat { font-size: 16px; font-weight: bold; color: #1e3a5f; }
    .summary-label { font-size: 8px; color: #64748b; }
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
                <th style="width:35px;">Estado</th>
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
                <td class="{{ $isLow ? 'stock-low' : 'stock-ok' }}">{{ $totalStock }}</td>
                <td>{{ $part->minimum_stock ?? 0 }}</td>
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
