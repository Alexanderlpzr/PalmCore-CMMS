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
        <div class="text-block">{{ $plan->description }}</div>
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
