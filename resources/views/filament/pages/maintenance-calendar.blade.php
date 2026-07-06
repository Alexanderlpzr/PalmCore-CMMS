@php
    // Filament status colour name → CSS modifier used by the scoped styles below.
    $chipMod = fn (string $color) => 'mcal-chip--'.$color;
@endphp

<x-filament-panels::page>
    {{-- Toolbar --}}
    <div class="mcal-toolbar">
        <div class="mcal-nav">
            <x-filament::button color="gray" size="sm" icon="heroicon-m-chevron-left" wire:click="previousMonth" />
            <x-filament::button color="gray" size="sm" wire:click="goToToday">Hoy</x-filament::button>
            <x-filament::button color="gray" size="sm" icon="heroicon-m-chevron-right" wire:click="nextMonth" />
            <h2 class="mcal-month">{{ $monthLabel }}</h2>
            <span class="mcal-count">· {{ $totalScheduled }} OT programadas</span>
        </div>

        <div class="mcal-filters">
            <select wire:model.live="plantId" class="mcal-select">
                <option value="">Todas las plantas</option>
                @foreach ($plantOptions as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
            <select wire:model.live="statusFilter" class="mcal-select">
                <option value="">Todos los estados</option>
                @foreach ($statusOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <select wire:model.live="technicianId" class="mcal-select">
                <option value="">Todos los técnicos</option>
                @foreach ($technicianOptions as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
            <label class="mcal-toggle">
                <input type="checkbox" wire:model.live="showPreventive"> Preventivos
            </label>
        </div>
    </div>

    {{-- Calendar --}}
    <div class="mcal-scroll">
        <div class="mcal-inner">
            <div class="mcal-weekdays">
                @foreach ($weekdays as $wd)
                    <div class="mcal-weekday">{{ $wd }}</div>
                @endforeach
            </div>

            <div class="mcal-grid">
                @foreach ($weeks as $week)
                    @foreach ($week as $day)
                        <div class="mcal-cell {{ $day['inMonth'] ? '' : 'mcal-cell--out' }}">
                            <div class="mcal-daynum {{ $day['isToday'] ? 'mcal-daynum--today' : '' }}">{{ $day['day'] }}</div>

                            @foreach ($day['preventives'] as $pm)
                                <div class="mcal-chip {{ $pm['overdue'] ? 'mcal-chip--pm-overdue' : 'mcal-chip--pm' }}"
                                     title="Preventivo{{ $pm['overdue'] ? ' VENCIDO' : '' }}: {{ $pm['name'] }} ({{ $pm['equipment'] }})">
                                    🗓 {{ $pm['equipment'] }} · {{ $pm['name'] }}
                                </div>
                            @endforeach

                            @foreach ($day['workOrders'] as $wo)
                                <a href="{{ $wo['url'] }}" class="mcal-chip {{ $chipMod($wo['color']) }}"
                                   title="{{ $wo['number'] }} — {{ $wo['title'] }} [{{ $wo['statusLabel'] }}]">
                                    <strong>{{ $wo['equipment'] ?? 'OT' }}</strong> · {{ $wo['title'] }}
                                </a>
                            @endforeach
                        </div>
                    @endforeach
                @endforeach
            </div>
        </div>
    </div>

    {{-- Legend --}}
    <div class="mcal-legend">
        <span class="mcal-legend-label">Estados:</span>
        @foreach ($statusOptions as $value => $label)
            @php $c = \App\Domain\Maintenance\Enums\WorkOrderStatus::from($value)->color(); @endphp
            <span class="mcal-legend-item"><span class="mcal-dot {{ $chipMod($c) }}"></span>{{ $label }}</span>
        @endforeach
        <span class="mcal-legend-item"><span class="mcal-dot mcal-chip--pm"></span>Preventivo</span>
        <span class="mcal-legend-item"><span class="mcal-dot mcal-chip--pm-overdue"></span>Preventivo vencido</span>
    </div>

    {{-- Workload + unscheduled --}}
    <div class="mcal-panels">
        <x-filament::section>
            <x-slot name="heading">Carga por técnico (mes visible)</x-slot>
            <x-slot name="description">Solo OT activas (no cerradas/canceladas)</x-slot>

            @if (count($workload) === 0)
                <p class="mcal-empty">Ningún técnico con OT activas este mes.</p>
            @else
                <div class="mcal-load">
                    @foreach ($workload as $tech)
                        <div class="mcal-load-row">
                            <span class="mcal-load-name">{{ $tech['name'] }}</span>
                            <span class="mcal-load-metrics">
                                <span>{{ $tech['count'] }} OT</span>
                                <span class="mcal-load-hours">{{ number_format($tech['hours'], 1) }} h</span>
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Sin programar</x-slot>
            <x-slot name="description">OT abiertas sin fecha planificada</x-slot>

            @if ($unscheduled->isEmpty())
                <p class="mcal-empty">Todo lo abierto tiene fecha. 🎉</p>
            @else
                <div class="mcal-unsched">
                    @foreach ($unscheduled as $wo)
                        <a href="{{ \App\Filament\Resources\Maintenance\WorkOrder\WorkOrderResource::getUrl('view', ['record' => $wo->getKey()]) }}"
                           class="mcal-unsched-row">
                            <span class="mcal-unsched-title"><strong>{{ $wo->equipment?->code ?? 'OT' }}</strong> · {{ $wo->title }}</span>
                            <x-filament::badge :color="$wo->status->color()">{{ $wo->status->label() }}</x-filament::badge>
                        </a>
                    @endforeach
                </div>
            @endif
        </x-filament::section>
    </div>

    <style>
        .mcal-toolbar { display:flex; flex-direction:column; gap:1rem; }
        @media (min-width:1024px){ .mcal-toolbar{ flex-direction:row; align-items:center; justify-content:space-between; } }
        .mcal-nav { display:flex; align-items:center; gap:.5rem; }
        .mcal-month { font-size:1.125rem; font-weight:600; margin:0 0 0 .5rem; color:var(--gray-950,#0d1116); }
        .mcal-count { font-size:.85rem; color:#6b7280; }
        .mcal-filters { display:flex; flex-wrap:wrap; align-items:center; gap:.5rem; }
        .mcal-select { border:1px solid #d1d5db; border-radius:.5rem; padding:.35rem .6rem; font-size:.85rem; background:#fff; color:#111827; }
        .mcal-toggle { display:flex; align-items:center; gap:.4rem; font-size:.85rem; color:#4b5563; }

        .mcal-scroll { overflow-x:auto; }
        .mcal-inner { min-width:52rem; }
        .mcal-weekdays { display:grid; grid-template-columns:repeat(7,1fr); }
        .mcal-weekday { padding:.5rem 0; text-align:center; font-size:.7rem; font-weight:600; letter-spacing:.05em; text-transform:uppercase; color:#6b7280; }
        .mcal-grid { display:grid; grid-template-columns:repeat(7,1fr); gap:1px; background:#e5e7eb; border:1px solid #e5e7eb; border-radius:.75rem; overflow:hidden; }
        .mcal-cell { min-height:7rem; background:#fff; padding:.4rem; display:flex; flex-direction:column; gap:.25rem; }
        .mcal-cell--out { opacity:.45; }
        .mcal-daynum { align-self:flex-start; display:grid; place-items:center; height:1.5rem; min-width:1.5rem; padding:0 .3rem; border-radius:9999px; font-size:.72rem; font-weight:600; color:#6b7280; }
        .mcal-daynum--today { background:var(--primary-600,#059669); color:#fff; }

        .mcal-chip { display:block; border-radius:.375rem; padding:.15rem .4rem; font-size:.68rem; font-weight:500; line-height:1.35;
            white-space:nowrap; overflow:hidden; text-overflow:ellipsis; text-decoration:none; box-shadow:inset 0 0 0 1px rgba(0,0,0,.04); }
        a.mcal-chip:hover { filter:brightness(.96); }
        .mcal-chip strong { font-weight:700; }

        /* Status colours (light) */
        .mcal-chip--gray    { background:#f1f3f0; color:#3f4a43; }
        .mcal-chip--info    { background:#dbeafe; color:#1d4ed8; }
        .mcal-chip--warning { background:#fef3c7; color:#92620a; }
        .mcal-chip--orange  { background:#ffedd5; color:#9a3412; }
        .mcal-chip--success { background:#d1fae5; color:#047857; }
        .mcal-chip--pm         { background:#e0f2fe; color:#0369a1; }
        .mcal-chip--pm-overdue { background:#fee2e2; color:#b91c1c; }

        .mcal-legend { display:flex; flex-wrap:wrap; align-items:center; gap:.25rem 1rem; font-size:.72rem; color:#6b7280; }
        .mcal-legend-label { font-weight:600; }
        .mcal-legend-item { display:inline-flex; align-items:center; gap:.3rem; }
        .mcal-dot { width:.65rem; height:.65rem; border-radius:9999px; box-shadow:inset 0 0 0 1px rgba(0,0,0,.06); }

        .mcal-panels { display:grid; gap:1rem; }
        @media (min-width:1024px){ .mcal-panels{ grid-template-columns:1fr 1fr; } }
        .mcal-empty { font-size:.85rem; color:#6b7280; }
        .mcal-load-row, .mcal-unsched-row { display:flex; align-items:center; justify-content:space-between; }
        .mcal-load-row { padding:.5rem 0; border-top:1px solid #f0f1ee; }
        .mcal-load-row:first-child { border-top:0; }
        .mcal-load-name { font-size:.85rem; font-weight:500; color:#374151; }
        .mcal-load-metrics { display:flex; align-items:center; gap:.75rem; font-size:.85rem; color:#6b7280; }
        .mcal-load-hours { font-weight:600; color:#374151; font-variant-numeric:tabular-nums; }
        .mcal-unsched { display:flex; flex-direction:column; gap:.4rem; }
        .mcal-unsched-row { gap:.5rem; padding:.5rem .75rem; border:1px solid #f0f1ee; border-radius:.5rem; text-decoration:none; }
        .mcal-unsched-row:hover { background:#f9fafb; }
        .mcal-unsched-title { font-size:.85rem; color:#374151; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }

        /* Dark theme (Filament toggles .dark on <html>) */
        .dark .mcal-month { color:#fff; }
        .dark .mcal-select { background:rgba(255,255,255,.05); border-color:rgba(255,255,255,.1); color:#fff; }
        .dark .mcal-grid { background:rgba(255,255,255,.08); border-color:rgba(255,255,255,.08); }
        .dark .mcal-cell { background:#111827; }
        .dark .mcal-weekday, .dark .mcal-count, .dark .mcal-toggle, .dark .mcal-legend, .dark .mcal-empty, .dark .mcal-load-metrics { color:#9ca3af; }
        .dark .mcal-daynum { color:#9ca3af; }
        .dark .mcal-load-name, .dark .mcal-load-hours, .dark .mcal-unsched-title { color:#e5e7eb; }
        .dark .mcal-load-row, .dark .mcal-unsched-row { border-color:rgba(255,255,255,.1); }
        .dark .mcal-unsched-row:hover { background:rgba(255,255,255,.05); }
        .dark .mcal-chip--gray    { background:rgba(148,163,184,.2); color:#cbd5e1; }
        .dark .mcal-chip--info    { background:rgba(59,130,246,.22); color:#bfdbfe; }
        .dark .mcal-chip--warning { background:rgba(245,158,11,.22); color:#fde68a; }
        .dark .mcal-chip--orange  { background:rgba(249,115,22,.22); color:#fed7aa; }
        .dark .mcal-chip--success { background:rgba(16,185,129,.22); color:#a7f3d0; }
        .dark .mcal-chip--pm         { background:rgba(14,165,233,.22); color:#bae6fd; }
        .dark .mcal-chip--pm-overdue { background:rgba(239,68,68,.22); color:#fecaca; }
    </style>
</x-filament-panels::page>
