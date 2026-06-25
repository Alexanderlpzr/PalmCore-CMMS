@php($impersonation = session(\App\Services\ImpersonationService::SESSION_KEY))

@if ($impersonation)
    <div
        role="alert"
        style="position: sticky; top: 0; z-index: 9999; display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 0.75rem 1.25rem; padding: 0.6rem 1rem; background: #b45309; color: #fff; font-weight: 600; box-shadow: 0 2px 8px rgba(0,0,0,0.25);"
    >
        <span style="display: inline-flex; align-items: center; gap: 0.5rem;">
            <span aria-hidden="true">⚠️</span>
            Estás actuando como {{ $impersonation['impersonated_name'] }}@if (! empty($impersonation['tenant_name'])) ({{ $impersonation['tenant_name'] }})@endif
        </span>

        <form method="POST" action="{{ route('impersonation.leave') }}" style="margin: 0;">
            @csrf
            <button
                type="submit"
                style="background: #fff; color: #b45309; border: 0; border-radius: 0.375rem; padding: 0.3rem 0.85rem; font-weight: 700; cursor: pointer;"
            >
                Salir de la impersonación
            </button>
        </form>
    </div>
@endif
