<x-filament-panels::page>

    {{-- La cola huérfana: trabajos que ningún worker va a procesar nunca. --}}
    @if (! empty($orphans))
        <div class="rounded-xl border border-red-200 bg-red-50 p-5 shadow-sm dark:border-red-800 dark:bg-red-900/20">
            <p class="font-semibold text-red-800 dark:text-red-300">
                Hay trabajos encolados que ningún worker atiende
            </p>
            <p class="mt-1 text-sm text-red-700 dark:text-red-400">
                Estas colas tienen trabajos esperando, pero Horizon no declara ningún supervisor para
                ellas. No van a fallar: se van a quedar ahí para siempre, en silencio.
            </p>
            <ul class="mt-3 space-y-1 text-sm text-red-800 dark:text-red-300">
                @foreach ($orphans as $queue => $size)
                    <li>
                        <span class="font-mono font-semibold">{{ $queue }}</span>
                        — {{ $size }} trabajo(s) esperando
                    </li>
                @endforeach
            </ul>
            <p class="mt-3 text-xs text-red-700 dark:text-red-400">
                Se arregla declarando un supervisor para esa cola en <span class="font-mono">config/horizon.php</span>.
            </p>
        </div>
    @endif

    {{-- Colas atendidas --}}
    <section>
        <h2 class="mb-3 text-lg font-semibold text-gray-900 dark:text-white">Colas</h2>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
            @forelse ($supervised as $queue)
                <div class="flex items-center justify-between border-b border-gray-100 p-4 last:border-0 dark:border-gray-800">
                    <span class="font-mono text-sm text-gray-900 dark:text-white">{{ $queue }}</span>
                    <span class="text-sm text-gray-600 dark:text-gray-400">
                        {{ $pending[$queue] ?? 0 }} en espera
                    </span>
                </div>
            @empty
                <p class="p-4 text-sm text-gray-500 dark:text-gray-400">
                    Horizon no declara ninguna cola.
                </p>
            @endforelse
        </div>

        @if (config('queue.default') !== 'redis')
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                La cola no corre sobre Redis en este entorno, así que no se puede saber cuántos trabajos
                esperan. No se afirma nada.
            </p>
        @endif
    </section>

    {{-- Trabajos fallidos --}}
    <section>
        <h2 class="mb-3 text-lg font-semibold text-gray-900 dark:text-white">Trabajos fallidos</h2>
        {{ $this->table }}
    </section>

</x-filament-panels::page>
