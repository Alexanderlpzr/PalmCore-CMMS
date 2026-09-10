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
        <h1>Paradas de Planta — {{ $plant?->name ?? 'Todas las plantas' }}</h1>
        <p>{{ $paros->count() }} paro(s) · {{ number_format($horasTotales, 2, ',', '.') }} horas · Generado el {{ $generatedAt->format('d/m/Y H:i') }}</p>
    </div>

    {{-- Qué se estaba mirando, arriba del todo.

         Un informe de un subconjunto que no dice cuál es un informe que engaña: quien lo
         reciba por correo dentro de un mes no tiene forma de saber que faltan paros a
         propósito, y contará los que ve como si fueran todos. --}}
    <div class="text-block" style="margin-bottom:12px;">
        <strong>Filtros aplicados:</strong>
        @forelse ($filtros as $filtro)
            <span class="filtro-chip">{{ $filtro }}</span>
        @empty
            ninguno — este informe trae todos los paros registrados.
        @endforelse
    </div>

    @if ($paros->isEmpty())
        <p class="empty">Ningún paro coincide con estos filtros.</p>
    @else
        {{-- Los cuatro repartos, en torta: cada uno reparte las mismas horas perdidas y lo
             que se busca es quién se llevó la mayor parte. Salen de los mismos paros que
             la tabla de abajo, así que no pueden contradecirla. --}}
        <table class="grid-2">
            <tr>
                <td style="width:50%; vertical-align:top; padding-right:8px;">
                    <div class="section">
                        <div class="section-title">Quién paró la línea (Tipo I)</div>
                        @include('reports.partials.chart-pie', ['valores' => $porTipo, 'unidad' => 'h', 'decimales' => 1, 'tamano' => 110])
                    </div>
                    <div class="section">
                        <div class="section-title">Causa física</div>
                        @include('reports.partials.chart-pie', ['valores' => $porCategoria, 'unidad' => 'h', 'decimales' => 1, 'tamano' => 110])
                    </div>
                </td>
                <td style="width:50%; vertical-align:top;">
                    <div class="section">
                        <div class="section-title">Sección de planta</div>
                        @include('reports.partials.chart-pie', ['valores' => $porSeccion, 'unidad' => 'h', 'decimales' => 1, 'tamano' => 110])
                    </div>
                    <div class="section">
                        <div class="section-title">Equipos que más pararon</div>
                        @include('reports.partials.chart-pie', ['valores' => $porEquipo, 'unidad' => 'h', 'decimales' => 1, 'tamano' => 110])
                    </div>
                </td>
            </tr>
        </table>

        <div class="section">
            <div class="section-title">Causa concreta (Tipo II)</div>
            @include('reports.partials.chart-pie', ['valores' => $porCausa, 'unidad' => 'h', 'decimales' => 1, 'tamano' => 110])
        </div>

        <div class="section" style="page-break-inside: auto;">
            <div class="section-title">El detalle, paro a paro</div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:12%">Inicio</th>
                        <th style="width:7%">Fin</th>
                        <th style="width:6%; text-align:right">Horas</th>
                        <th style="width:11%">Tipo I</th>
                        <th style="width:12%">Tipo II</th>
                        <th style="width:12%">Sección</th>
                        <th style="width:18%">Equipo</th>
                        <th style="width:22%">Causa / observación</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($paros as $paro)
                        <tr>
                            <td>{{ $paro->started_at->format('d/m/Y H:i') }}</td>
                            <td>{{ $paro->ended_at?->format('H:i') ?? '—' }}</td>
                            <td style="text-align:right">
                                {{ number_format($paro->started_at->diffInMinutes($paro->ended_at ?? now()) / 60, 2, ',', '.') }}
                            </td>
                            <td>{{ $paro->reported_type?->label() ?? '—' }}</td>
                            <td>{{ $paro->stoppage_reason?->label() ?? '—' }}</td>
                            <td>{{ $paro->section?->label() ?? '—' }}</td>
                            <td>{{ $paro->equipment?->name ?? '—' }}</td>
                            <td>{{ $paro->notes ?: '' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="cost-total">Total: {{ number_format($horasTotales, 2, ',', '.') }} horas perdidas</div>
        </div>
    @endif

</div>
</body>
</html>
