<x-filament-panels::page>
    {{-- Modal to display new token one time --}}
    <x-filament::modal id="show-new-token" width="lg">
        <x-slot name="heading">Token generado</x-slot>

        <div class="space-y-4">
            <x-filament::section>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Copia este token ahora. <strong>No se volverá a mostrar.</strong>
                </p>
                @if ($newPlainTextToken)
                    <div class="mt-3 rounded-lg bg-gray-100 dark:bg-gray-800 p-3 font-mono text-xs break-all select-all">
                        {{ $newPlainTextToken }}
                    </div>
                @endif
            </x-filament::section>
        </div>

        <x-slot name="footerActions">
            <x-filament::button
                color="gray"
                x-on:click="$dispatch('close-modal', { id: 'show-new-token' })"
                wire:click="$set('newPlainTextToken', null)"
            >
                Cerrar
            </x-filament::button>
        </x-slot>
    </x-filament::modal>

    {{-- Token list --}}
    <x-filament::section>
        <x-slot name="heading">Tokens activos</x-slot>
        <x-slot name="description">
            Tokens de acceso para esta organización. Úsalos en el header <code class="text-xs">Authorization: Bearer {token}</code>.
        </x-slot>

        @php
            $tokens = $this->getTokens();
        @endphp

        @if (empty($tokens))
            <p class="text-sm text-gray-500 dark:text-gray-400 py-4 text-center">
                No hay tokens activos. Crea uno con el botón "Crear Token".
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700 text-left">
                            <th class="pb-3 pr-4 font-semibold text-gray-700 dark:text-gray-300">Nombre</th>
                            <th class="pb-3 pr-4 font-semibold text-gray-700 dark:text-gray-300">Permisos</th>
                            <th class="pb-3 pr-4 font-semibold text-gray-700 dark:text-gray-300">Último uso</th>
                            <th class="pb-3 pr-4 font-semibold text-gray-700 dark:text-gray-300">Expira</th>
                            <th class="pb-3 font-semibold text-gray-700 dark:text-gray-300">Creado</th>
                            <th class="pb-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($tokens as $token)
                            <tr>
                                <td class="py-3 pr-4 font-medium text-gray-900 dark:text-white">
                                    {{ $token->name }}
                                </td>
                                <td class="py-3 pr-4">
                                    @php
                                        $abilities = json_decode($token->abilities ?? '["*"]', true);
                                    @endphp
                                    <div class="flex flex-wrap gap-1">
                                        @foreach ($abilities as $ability)
                                            <span class="inline-flex items-center rounded-full bg-primary-50 dark:bg-primary-900 px-2 py-0.5 text-xs font-medium text-primary-700 dark:text-primary-300">
                                                {{ $ability }}
                                            </span>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="py-3 pr-4 text-gray-500 dark:text-gray-400">
                                    {{ $token->last_used_at?->diffForHumans() ?? 'Nunca' }}
                                </td>
                                <td class="py-3 pr-4 text-gray-500 dark:text-gray-400">
                                    @if ($token->expires_at)
                                        @if ($token->expires_at->isPast())
                                            <span class="text-danger-600 dark:text-danger-400">
                                                Expirado ({{ $token->expires_at->diffForHumans() }})
                                            </span>
                                        @else
                                            {{ $token->expires_at->diffForHumans() }}
                                        @endif
                                    @else
                                        Sin expiración
                                    @endif
                                </td>
                                <td class="py-3 pr-4 text-gray-500 dark:text-gray-400">
                                    {{ $token->created_at->format('d/m/Y') }}
                                </td>
                                <td class="py-3">
                                    <x-filament::button
                                        color="danger"
                                        size="sm"
                                        wire:click="revokeToken({{ $token->id }})"
                                        wire:confirm="¿Revocar este token? Las integraciones que lo usen dejarán de funcionar."
                                    >
                                        Revocar
                                    </x-filament::button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
