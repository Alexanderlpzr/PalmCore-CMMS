@php
    use App\Domain\Shared\Enums\SubscriptionStatus;

    if (! app()->bound('subscription.status')) {
        return;
    }

    /** @var SubscriptionStatus $status */
    $status = app('subscription.status');
    $message = $status->bannerMessage();

    if ($message === null) {
        return;
    }

    $tenant     = \Filament\Facades\Filament::getTenant();
    $expiresAt  = $tenant?->subscription_expires_at;
    $daysLeft   = $tenant?->daysUntilExpiry();
    $bgColor    = $status->bannerHexColor();
    $billingEmail = config('palmcore.billing_email', 'hola@palmcore.app');
@endphp

<div style="
    position: sticky;
    top: 0;
    z-index: 9998;
    background: {{ $bgColor }};
    color: #fff;
    padding: 0.5rem 1.5rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    font-size: 0.875rem;
    font-family: inherit;
">
    <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
        <strong>{{ $status->label() }}</strong>
        <span>{{ $message }}</span>

        @if ($expiresAt !== null && $daysLeft !== null)
            @if ($daysLeft > 0)
                <span style="opacity: 0.85;">
                    Vence el {{ $expiresAt->format('d/m/Y') }} ({{ $daysLeft }} {{ Str::plural('día', $daysLeft) }})
                </span>
            @else
                <span style="opacity: 0.85;">
                    Venció el {{ $expiresAt->format('d/m/Y') }}
                </span>
            @endif
        @elseif ($status === SubscriptionStatus::Trial && $expiresAt === null)
            <span style="opacity: 0.85;">Sin fecha de vencimiento configurada.</span>
        @endif
    </div>

    <a
        href="mailto:{{ $billingEmail }}"
        style="
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.5);
            color: #fff;
            padding: 0.25rem 0.875rem;
            border-radius: 0.375rem;
            text-decoration: none;
            font-weight: 600;
            white-space: nowrap;
            font-size: 0.8rem;
        "
    >
        Renovar suscripción
    </a>
</div>
