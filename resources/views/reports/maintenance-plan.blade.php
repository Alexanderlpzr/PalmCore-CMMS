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
    table.data-table td { padding: 5px 6px; border: 1px solid #e2e8f0; vertical-align: top; }
    table.data-table tr:nth-child(even) td { background: #f8fafc; }

    .task-num { width: 28px; font-weight: bold; color: #1e3a5f; }
    .checkbox-col { width: 20px; text-align: center; }
    .checkbox { display: inline-block; width: 10px; height: 10px; border: 1px solid #94a3b8; border-radius: 2px; }
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
        <h1>Plan de Mantenimiento — {{ $plan->plan_number }}</h1>
        <p>{{ $plan->name }}</p>
    </div>

    <div style="margin-bottom: 14px;">
        <span class="badge {{ $plan->is_active ? 'badge-success' : 'badge-gray' }}">
            {{ $plan->is_active ? 'Activo' : 'Inactivo' }}
        </span>&nbsp;
        <span class="badge badge-gray">{{ $plan->trigger_source->label() }}</span>
    </div>

    {{-- 1. General --}}
    <div class="section">
        <div class="section-title">Información General</div>
        <table class="grid-2">
            <tr>
                <td>
                    <div class="field-label">Equipo</div>
                    <div class="field-value">{{ $plan->equipment?->name ?? '—' }} ({{ $plan->equipment?->code ?? '—' }})</div>

                    <div class="field-label">Planta / Área</div>
                    <div class="field-value">
                        {{ $plan->equipment?->plant?->name ?? '—' }}
                        @if($plan->equipment?->area) / {{ $plan->equipment->area->name }} @endif
                    </div>

                    <div class="field-label">Responsable</div>
                    <div class="field-value">{{ $plan->responsibleUser?->name ?? '—' }}</div>

                    <div class="field-label">Duración estimada</div>
                    <div class="field-value">
                        @if($plan->estimated_duration_minutes)
                            {{ intdiv($plan->estimated_duration_minutes, 60) }}h {{ $plan->estimated_duration_minutes % 60 }}min
                        @else
                            —
                        @endif
                    </div>
                </td>
                <td>
                    <div class="field-label">Tipo de disparador</div>
                    <div class="field-value">{{ $plan->trigger_source->label() }}</div>

                    @if($plan->time_frequency)
                    <div class="field-label">Frecuencia</div>
                    <div class="field-value">{{ $plan->time_frequency->label() }}</div>
                    @endif

                    @if($plan->meter_interval)
                    <div class="field-label">Intervalo de horómetro</div>
                    <div class="field-value">{{ number_format($plan->meter_interval) }} h</div>
                    @endif

                    <div class="field-label">Última generación</div>
                    <div class="field-value">{{ $plan->last_generated_at?->format('d/m/Y') ?? 'Nunca' }}</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- 2. Description --}}
    @if($plan->description)
    <div class="section">
        <div class="section-title">Descripción</div>
        <div style="font-size:9px; line-height:1.5; color:#374151; background:#f8fafc;
                    border:1px solid #e2e8f0; border-radius:3px; padding:6px;">
            {{ $plan->description }}
        </div>
    </div>
    @endif

    {{-- 3. Tasks Checklist --}}
    @if($plan->tasks->isNotEmpty())
    <div class="section">
        <div class="section-title">Tareas ({{ $plan->tasks->count() }} tareas)</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th class="checkbox-col">✓</th>
                    <th class="task-num">#</th>
                    <th>Tarea</th>
                    <th style="width:80px;">Tipo</th>
                    <th style="width:60px;">Duración est.</th>
                    <th style="width:100px;">Observaciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach($plan->tasks as $index => $task)
                <tr>
                    <td class="checkbox-col"><div class="checkbox"></div></td>
                    <td class="task-num">{{ $index + 1 }}</td>
                    <td>{{ $task->title }}<br>
                        @if($task->description)
                        <span style="font-size:8px; color:#64748b;">{{ $task->description }}</span>
                        @endif
                    </td>
                    <td>{{ $task->task_type ?? '—' }}</td>
                    <td>{{ $task->estimated_minutes ? $task->estimated_minutes.'min' : '—' }}</td>
                    <td></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- 4. Execution record --}}
    <div class="section">
        <div class="section-title">Registro de Ejecución</div>
        <table style="width:100%;">
            <tr>
                <td style="width:33%; padding-right:8px;">
                    <div class="field-label">Fecha de ejecución</div>
                    <div style="height:20px; border-bottom:1px solid #94a3b8; margin-top:4px;"></div>
                </td>
                <td style="width:33%; padding-right:8px;">
                    <div class="field-label">Técnico ejecutor</div>
                    <div style="height:20px; border-bottom:1px solid #94a3b8; margin-top:4px;"></div>
                </td>
                <td style="width:33%;">
                    <div class="field-label">Firma y sello</div>
                    <div style="height:40px; border:1px solid #e2e8f0; margin-top:4px; border-radius:3px;"></div>
                </td>
            </tr>
        </table>
    </div>

</div>
</body>
</html>
