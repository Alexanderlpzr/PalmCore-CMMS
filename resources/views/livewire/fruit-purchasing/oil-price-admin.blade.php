@php
    $current = $this->current;
    $field = 'block w-full rounded-xl border-2 border-gray-300 bg-white px-4 py-3 pl-10 text-2xl font-semibold tabular-nums text-gray-900 placeholder:text-gray-400 placeholder:font-normal focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-600/20';
@endphp

<div class="mx-auto max-w-2xl px-4 py-8 text-lg text-gray-800 sm:py-12">
    <header class="mb-8 flex items-center gap-4">
        <img src="{{ asset('images/logo.png') }}" alt="Extractora El Pajuil" width="512" height="512" class="h-16 w-16 shrink-0 drop-shadow-md sm:h-20 sm:w-20">
        <div>
            <h1 class="text-3xl font-black leading-tight text-petrol-900 sm:text-4xl">Método <span class="text-brand-600">R-100</span></h1>
            <p class="text-xl font-bold text-petrol-800">Precio del aceite (CPO)</p>
        </div>
    </header>

    {{-- Precio vigente --}}
    <section class="rounded-3xl bg-petrol-800 p-6 text-white shadow-lg">
        <p class="text-base text-white/70">Precio que ven hoy los proveedores</p>
        @if ($current)
            <p class="mt-1 text-4xl font-extrabold tabular-nums sm:text-5xl" data-testid="current-price">$ {{ number_format($current['price'], 0, ',', '.') }}</p>
            <p class="mt-1 text-lg text-white/80">por tonelada de aceite</p>
            <p class="mt-4 text-base text-white/70">
                @if ($current['updatedAt'])
                    Publicado el {{ $current['updatedAt']->timezone(config('app.timezone'))->format('d/m/Y \a \l\a\s g:i a') }}@if ($current['updatedBy']) por {{ $current['updatedBy'] }}@endif.
                @else
                    Viene de la configuración del servidor; todavía nadie lo ha publicado aquí.
                @endif
            </p>
        @else
            <p class="mt-1 text-2xl font-bold">Todavía no hay precio publicado</p>
            <p class="mt-2 text-base text-white/75">Mientras tanto la calculadora muestra solo el rendimiento (TEA), sin pesos.</p>
        @endif
    </section>

    {{-- Publicar uno nuevo --}}
    <section class="mt-8 rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
        <h2 class="text-2xl font-bold text-petrol-900"><label for="newPrice">Publicar un precio nuevo</label></h2>
        <p class="mt-1 text-base text-gray-600">Precio de la tonelada de aceite (CPO) en pesos. Apenas lo guarde, la calculadora lo usa para todos.</p>

        <form wire:submit="save" class="mt-4 space-y-4">
            <div class="relative max-w-sm">
                <span class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-2xl font-bold text-gray-500">$</span>
                <input id="newPrice" type="text" inputmode="decimal" autocomplete="off" placeholder="4.000.000"
                       wire:model="newPrice"
                       x-on:blur="$el.value = window.frondaFormatNumber($el.value); $el.dispatchEvent(new Event('input'))"
                       class="{{ $field }}">
            </div>
            @error('newPrice')
                <p class="rounded-xl bg-red-50 px-4 py-3 text-base font-semibold text-red-800" role="alert">{{ $message }}</p>
            @enderror

            <button type="submit" wire:loading.attr="disabled"
                    class="w-full rounded-2xl bg-brand-600 px-6 py-4 text-xl font-bold text-white hover:bg-brand-700 disabled:opacity-60 sm:w-auto">
                Publicar precio
            </button>
        </form>

        @if ($saved)
            <p class="mt-4 rounded-xl bg-brand-50 px-4 py-3 text-lg font-semibold text-brand-800" role="status">✔ Listo. La calculadora ya usa el precio nuevo.</p>
        @endif
    </section>

    <p class="mt-8 text-center">
        <a href="{{ route('r100.calculator') }}" class="text-lg font-semibold text-brand-700 underline underline-offset-4">Ver la calculadora →</a>
    </p>
</div>
