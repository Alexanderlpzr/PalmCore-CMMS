@php
    use App\Domain\Platform\Enums\HealthStatus;

    $statusClasses = [
        'success' => 'text-emerald-700 bg-emerald-50 dark:text-emerald-300 dark:bg-emerald-900/30',
        'warning' => 'text-amber-700 bg-amber-50 dark:text-amber-300 dark:bg-amber-900/30',
        'danger' => 'text-red-700 bg-red-50 dark:text-red-300 dark:bg-red-900/30',
        'gray' => 'text-gray-600 bg-gray-100 dark:text-gray-300 dark:bg-gray-800',
    ];
@endphp

<x-filament-panels::page>

    {{-- El semáforo: lo primero que se ve al entrar --}}
    <div class="rounded-xl border p-5 shadow-sm
        {{ $overall === HealthStatus::Ok
            ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-900/20'
            : ($overall === HealthStatus::Critical
                ? 'border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-900/20'
                : 'border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-900/20') }}">
        <div class="flex items-center gap-3">
            <x-filament::icon :icon="$overall->icon()" class="h-7 w-7 shrink-0" />
            <div>
                <p class="text-base font-semibold text-gray-900 dark:text-white">
                    @switch($overall)
                        @case(HealthStatus::Ok) La plataforma está sana. @break
                        @case(HealthStatus::Critical) Hay algo roto que necesita atención ahora. @break
                        @case(HealthStatus::Warning) Hay algo que conviene mirar hoy. @break
                        @default Hay chequeos que no se pudieron ejecutar.
                    @endswitch
                </p>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Última comprobación: {{ now()->format('d/m/Y H:i') }}
                </p>
            </div>
        </div>
    </div>

    {{-- Salud del sistema --}}
    <section>
        <h2 class="mb-3 text-lg font-semibold text-gray-900 dark:text-white">Salud del sistema</h2>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
            @foreach ($checks as $check)
                <div class="flex items-start justify-between gap-4 border-b border-gray-100 p-4 last:border-0 dark:border-gray-800">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $check['label'] }}</p>
                        @if ($check['detail'])
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $check['detail'] }}</p>
                        @endif
                    </div>
                    <div class="flex shrink-0 items-center gap-3">
                        <span class="text-sm text-gray-600 dark:text-gray-300">{{ $check['value'] }}</span>
                        <span class="rounded-lg px-2 py-1 text-xs font-semibold {{ $statusClasses[$check['status']->color()] }}">
                            {{ $check['status']->label() }}
                        </span>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Empresas --}}
    <section>
        <h2 class="mb-3 text-lg font-semibold text-gray-900 dark:text-white">Empresas</h2>

        <div class="mb-4 grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total</p>
                <p class="mt-1 text-3xl font-bold text-gray-900 dark:text-white">{{ $totalTenants }}</p>
            </div>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm dark:border-emerald-800 dark:bg-emerald-900/20">
                <p class="text-sm font-medium text-emerald-700 dark:text-emerald-400">Activas</p>
                <p class="mt-1 text-3xl font-bold text-emerald-800 dark:text-emerald-300">{{ $activeTenants }}</p>
            </div>
            <div class="rounded-xl border border-blue-200 bg-blue-50 p-5 shadow-sm dark:border-blue-800 dark:bg-blue-900/20">
                <p class="text-sm font-medium text-blue-700 dark:text-blue-400">En prueba</p>
                <p class="mt-1 text-3xl font-bold text-blue-800 dark:text-blue-300">{{ $trialTenants }}</p>
            </div>
            <div class="rounded-xl border border-red-200 bg-red-50 p-5 shadow-sm dark:border-red-800 dark:bg-red-900/20">
                <p class="text-sm font-medium text-red-700 dark:text-red-400">Suspendidas</p>
                <p class="mt-1 text-3xl font-bold text-red-800 dark:text-red-300">{{ $suspendedTenants }}</p>
            </div>
        </div>

        {{-- Un CMMS no muere con un error: muere el mes en que nadie vuelve a entrar. --}}
        <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <table class="w-full text-sm">
                <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                    <tr>
                        <th class="p-3 text-left font-semibold">Empresa</th>
                        <th class="p-3 text-right font-semibold">Usuarios activos</th>
                        <th class="p-3 text-right font-semibold">Equipos</th>
                        <th class="p-3 text-right font-semibold">OT abiertas</th>
                        <th class="p-3 text-right font-semibold">OT vencidas</th>
                        <th class="p-3 text-right font-semibold">Alertas críticas</th>
                        <th class="p-3 text-left font-semibold">Última actividad</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tenants as $row)
                        <tr class="border-b border-gray-100 last:border-0 dark:border-gray-800">
                            <td class="p-3">
                                <span class="font-medium text-gray-900 dark:text-white">{{ $row['tenant']->name }}</span>
                                @if ($row['is_dormant'])
                                    <span class="ml-2 rounded-lg bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">
                                        Inactiva
                                    </span>
                                @endif
                            </td>
                            <td class="p-3 text-right text-gray-700 dark:text-gray-300">
                                {{ $row['active_users'] }} / {{ $row['users'] }}
                            </td>
                            <td class="p-3 text-right text-gray-700 dark:text-gray-300">{{ $row['equipment'] }}</td>
                            <td class="p-3 text-right text-gray-700 dark:text-gray-300">{{ $row['open_work_orders'] }}</td>
                            <td class="p-3 text-right {{ $row['overdue_work_orders'] > 0 ? 'font-semibold text-amber-700 dark:text-amber-400' : 'text-gray-700 dark:text-gray-300' }}">
                                {{ $row['overdue_work_orders'] }}
                            </td>
                            <td class="p-3 text-right {{ $row['critical_alerts'] > 0 ? 'font-semibold text-red-700 dark:text-red-400' : 'text-gray-700 dark:text-gray-300' }}">
                                {{ $row['critical_alerts'] }}
                            </td>
                            <td class="p-3 text-gray-600 dark:text-gray-400">
                                @if ($row['last_activity_at'])
                                    {{ $row['last_activity_at']->format('d/m/Y') }}
                                    <span class="text-xs text-gray-400">({{ $row['days_since_activity'] }} d)</span>
                                @else
                                    Nunca entró nadie
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

</x-filament-panels::page>
