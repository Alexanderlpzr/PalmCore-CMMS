<x-filament-widgets::widget class="fi-wi-welcome">
    <div style="border-radius: 1rem; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.12); border: 1px solid #e5e7eb;">

        {{-- Banner --}}
        <div style="background: linear-gradient(135deg, #0F4C5C 0%, #0a3545 60%, #062535 100%); padding: 1.5rem 2rem; display: flex; align-items: center; gap: 1.5rem; flex-wrap: wrap;">

            <img src="{{ asset('images/logo.png') }}"
                 alt="Fronda CMMS"
                 style="height: 3.5rem; width: auto; max-width: 200px; object-fit: contain; flex-shrink: 0; filter: drop-shadow(0 2px 6px rgba(0,0,0,0.3));">

            <div style="flex: 1; min-width: 180px;">
                <p style="color: #5fc8a0; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.12em; margin: 0 0 0.2rem;">
                    Bienvenido de vuelta
                </p>
                <h1 style="color: #ffffff; font-size: 1.4rem; font-weight: 700; margin: 0 0 0.2rem; line-height: 1.2;">
                    {{ $userName }}
                </h1>
                <p style="color: rgba(255,255,255,0.55); font-size: 0.8rem; margin: 0;">
                    {{ $tenantName }} &mdash; {{ now()->isoFormat('dddd, D [de] MMMM [de] YYYY') }}
                </p>
            </div>

            <div style="flex-shrink: 0; text-align: right;">
                <span style="display: block; color: rgba(255,255,255,0.35); font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.1em;">
                    Menos complejidad,
                </span>
                <span style="display: block; color: rgba(255,255,255,0.65); font-size: 0.8rem; font-weight: 600;">
                    más resultados.
                </span>
            </div>

        </div>

        {{-- KPIs --}}
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); background: #ffffff; border-top: 1px solid #f3f4f6;">

            <div style="padding: 1.25rem 1rem; text-align: center; border-right: 1px solid #f3f4f6;">
                <p style="font-size: 2rem; font-weight: 900; color: #0F4C5C; margin: 0; line-height: 1;">{{ $openWorkOrders }}</p>
                <p style="font-size: 0.65rem; font-weight: 600; color: #9ca3af; margin: 0.4rem 0 0; text-transform: uppercase; letter-spacing: 0.06em;">OT abiertas</p>
            </div>

            <div style="padding: 1.25rem 1rem; text-align: center; border-right: 1px solid #f3f4f6;">
                <p style="font-size: 2rem; font-weight: 900; color: #2E8B57; margin: 0; line-height: 1;">{{ $inProgressWorkOrders }}</p>
                <p style="font-size: 0.65rem; font-weight: 600; color: #9ca3af; margin: 0.4rem 0 0; text-transform: uppercase; letter-spacing: 0.06em;">En ejecución</p>
            </div>

            <div style="padding: 1.25rem 1rem; text-align: center; border-right: 1px solid #f3f4f6;">
                <p style="font-size: 2rem; font-weight: 900; color: #d97706; margin: 0; line-height: 1;">{{ $pendingRequests }}</p>
                <p style="font-size: 0.65rem; font-weight: 600; color: #9ca3af; margin: 0.4rem 0 0; text-transform: uppercase; letter-spacing: 0.06em;">Solicitudes pendientes</p>
            </div>

            <div style="padding: 1.25rem 1rem; text-align: center;">
                <p style="font-size: 2rem; font-weight: 900; color: #374151; margin: 0; line-height: 1;">{{ $activeEquipment }}</p>
                <p style="font-size: 0.65rem; font-weight: 600; color: #9ca3af; margin: 0.4rem 0 0; text-transform: uppercase; letter-spacing: 0.06em;">Equipos activos</p>
            </div>

        </div>

    </div>
</x-filament-widgets::widget>
