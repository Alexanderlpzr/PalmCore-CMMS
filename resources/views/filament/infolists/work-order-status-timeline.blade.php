@php
    use App\Domain\Maintenance\Enums\WorkOrderStatus;

    /** @var \App\Models\WorkOrder $record */
    $record = $getRecord();
    $status = $record->status;

    // Main happy-path lifecycle. OnHold and Cancelled are handled as variants.
    $steps = [
        ['value' => 'draft',       'label' => 'Borrador'],
        ['value' => 'planned',     'label' => 'Planificada'],
        ['value' => 'in_progress', 'label' => 'En Ejecución'],
        ['value' => 'completed',   'label' => 'Completada'],
        ['value' => 'verified',    'label' => 'Verificada'],
        ['value' => 'closed',      'label' => 'Cerrada'],
    ];

    $order = array_column($steps, 'value');

    $isCancelled = $status === WorkOrderStatus::Cancelled;
    $isOnHold = $status === WorkOrderStatus::OnHold;

    // On-hold sits at the "in_progress" position on the track.
    $currentValue = $isOnHold ? 'in_progress' : $status->value;
    $currentIndex = array_search($currentValue, $order, true);
    if ($currentIndex === false) {
        $currentIndex = 0;
    }

    // Progress fill width (% of track covered up to the current node).
    $progressPct = count($steps) > 1 ? ($currentIndex / (count($steps) - 1)) * 100 : 0;
@endphp

<div class="ot-timeline {{ $isCancelled ? 'is-cancelled' : '' }} {{ $isOnHold ? 'is-onhold' : '' }}"
     role="group" aria-label="Progreso de la orden de trabajo">

    @if ($isCancelled)
        <div class="ot-timeline__banner" role="status">
            <span class="ot-timeline__banner-dot"></span>
            Esta orden de trabajo fue <strong>cancelada</strong>.
        </div>
    @elseif ($isOnHold)
        <div class="ot-timeline__banner ot-timeline__banner--hold" role="status">
            <span class="ot-timeline__banner-dot"></span>
            En espera — la ejecución está <strong>pausada</strong>.
        </div>
    @endif

    <div class="ot-timeline__track" style="--progress: {{ $isCancelled ? 0 : $progressPct }}%">
        <div class="ot-timeline__line" aria-hidden="true"></div>
        <div class="ot-timeline__line-fill" aria-hidden="true"></div>

        @foreach ($steps as $i => $step)
            @php
                $state = $isCancelled
                    ? 'muted'
                    : ($i < $currentIndex ? 'done' : ($i === $currentIndex ? 'active' : 'todo'));
            @endphp
            <div class="ot-timeline__step ot-timeline__step--{{ $state }}"
                 style="--i: {{ $i }}"
                 @if ($state === 'active') aria-current="step" @endif>
                <span class="ot-timeline__node">
                    @if ($state === 'done')
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                    @else
                        <span class="ot-timeline__dot"></span>
                    @endif
                </span>
                <span class="ot-timeline__label">{{ $step['label'] }}</span>
            </div>
        @endforeach
    </div>
</div>

<style>
    .ot-timeline {
        --ot-accent: #0b6e62;
        --ot-accent-soft: #d7ebe6;
        --ot-hold: #b6791a;
        --ot-cancel: #b83a2c;
        --ot-line: #e2e6e1;
        --ot-todo: #9aa8a0;
        --ot-node-bg: #ffffff;
        --ot-label: #46554c;
        --ot-label-active: #16211c;
        padding: 0.5rem 0.25rem 0.25rem;
    }
    .dark .ot-timeline {
        --ot-accent: #4cb8a6;
        --ot-accent-soft: #16332d;
        --ot-hold: #d9a441;
        --ot-cancel: #e0705f;
        --ot-line: #2a3a33;
        --ot-todo: #5f6f66;
        --ot-node-bg: #14201b;
        --ot-label: #aebbb2;
        --ot-label-active: #e7efe9;
    }

    .ot-timeline__banner {
        display: flex; align-items: center; gap: 0.5rem;
        font-size: 0.85rem; font-weight: 500;
        color: var(--ot-cancel);
        background: color-mix(in srgb, var(--ot-cancel) 12%, transparent);
        border-radius: 8px; padding: 0.5rem 0.75rem; margin-bottom: 0.9rem;
    }
    .ot-timeline__banner--hold { color: var(--ot-hold); background: color-mix(in srgb, var(--ot-hold) 14%, transparent); }
    .ot-timeline__banner-dot {
        width: 9px; height: 9px; border-radius: 50%; background: currentColor;
        animation: ot-blink 1.4s ease-in-out infinite;
    }

    .ot-timeline__track {
        position: relative;
        display: flex; justify-content: space-between; align-items: flex-start;
        gap: 0.25rem;
    }
    /* Base line + animated progress fill sit behind the nodes. */
    .ot-timeline__line, .ot-timeline__line-fill {
        position: absolute; top: 13px; left: 0; right: 0; height: 3px; border-radius: 3px;
    }
    .ot-timeline__line { background: var(--ot-line); }
    .ot-timeline__line-fill {
        right: auto; width: var(--progress); background: var(--ot-accent);
        transition: width 900ms cubic-bezier(.65,0,.35,1);
    }

    .ot-timeline__step {
        position: relative; z-index: 1;
        display: flex; flex-direction: column; align-items: center; gap: 0.4rem;
        flex: 1 1 0; min-width: 0;
        text-align: center;
        animation: ot-rise 480ms ease both;
        animation-delay: calc(var(--i) * 70ms);
    }
    .ot-timeline__node {
        width: 28px; height: 28px; border-radius: 50%;
        display: grid; place-items: center;
        background: var(--ot-node-bg);
        border: 2px solid var(--ot-line);
        color: #fff;
        transition: border-color 300ms ease, background 300ms ease, transform 300ms ease;
    }
    .ot-timeline__dot { width: 8px; height: 8px; border-radius: 50%; background: var(--ot-todo); transition: background 300ms ease; }
    .ot-timeline__label {
        font-size: 0.72rem; line-height: 1.2; color: var(--ot-label);
        max-width: 8ch; word-break: break-word;
    }

    /* Completed steps */
    .ot-timeline__step--done .ot-timeline__node { background: var(--ot-accent); border-color: var(--ot-accent); }
    .ot-timeline__step--done .ot-timeline__label { color: var(--ot-label); }

    /* Current step — filled ring + gentle pulse */
    .ot-timeline__step--active .ot-timeline__node {
        background: var(--ot-node-bg); border-color: var(--ot-accent);
        transform: scale(1.12);
        animation: ot-pulse 1.8s ease-in-out infinite;
    }
    .ot-timeline__step--active .ot-timeline__dot { background: var(--ot-accent); }
    .ot-timeline__step--active .ot-timeline__label { color: var(--ot-label-active); font-weight: 700; }

    /* On-hold / cancelled recolour the active node */
    .is-onhold .ot-timeline__step--active .ot-timeline__node { border-color: var(--ot-hold); animation-name: ot-pulse-hold; }
    .is-onhold .ot-timeline__step--active .ot-timeline__dot { background: var(--ot-hold); }
    .is-cancelled .ot-timeline__node { border-color: var(--ot-line); background: var(--ot-node-bg); }
    .is-cancelled .ot-timeline__label { color: var(--ot-todo); text-decoration: line-through; }

    @keyframes ot-rise { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }
    @keyframes ot-pulse {
        0%, 100% { box-shadow: 0 0 0 0 color-mix(in srgb, var(--ot-accent) 45%, transparent); }
        50%      { box-shadow: 0 0 0 7px color-mix(in srgb, var(--ot-accent) 0%, transparent); }
    }
    @keyframes ot-pulse-hold {
        0%, 100% { box-shadow: 0 0 0 0 color-mix(in srgb, var(--ot-hold) 45%, transparent); }
        50%      { box-shadow: 0 0 0 7px color-mix(in srgb, var(--ot-hold) 0%, transparent); }
    }
    @keyframes ot-blink { 0%, 100% { opacity: 1; } 50% { opacity: 0.35; } }

    @media (prefers-reduced-motion: reduce) {
        .ot-timeline__step,
        .ot-timeline__line-fill { animation: none !important; transition: none !important; }
        .ot-timeline__step--active .ot-timeline__node { animation: none !important; }
        .ot-timeline__banner-dot { animation: none !important; }
    }

    @media (max-width: 640px) {
        .ot-timeline__label { font-size: 0.64rem; }
        .ot-timeline__node { width: 24px; height: 24px; }
    }
</style>
