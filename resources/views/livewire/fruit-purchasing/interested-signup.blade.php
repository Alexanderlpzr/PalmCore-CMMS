{{-- mb-36 en el celular: deja ver la casilla sobre la barra fija del resultado. --}}
<section class="mx-auto mb-36 max-w-6xl px-4 lg:mb-12">
    <div class="rounded-3xl border-2 border-petrol-100 bg-white p-5 shadow-sm sm:p-8">
        @if ($savedEmail)
            <div class="text-center" role="status">
                <p class="text-5xl" aria-hidden="true">✅</p>
                <h2 class="mt-3 text-2xl font-bold text-petrol-900">¡Gracias! Quedó registrado</h2>
                <p class="mt-2 text-lg text-gray-700">Le escribiremos a <strong>{{ $savedEmail }}</strong>.</p>
                <button type="button" wire:click="$set('savedEmail', null)" class="mt-4 text-base font-semibold text-brand-700 underline underline-offset-4">Registrar otro correo</button>
            </div>
        @else
            <h2 class="flex items-center gap-3 text-2xl font-bold text-petrol-900">
                <span class="text-3xl" aria-hidden="true">✉️</span> ¿Quiere saber más?
            </h2>
            <p class="mt-2 text-lg text-gray-700">Déjenos su correo y le contamos sobre el Método R-100, el precio de la fruta y cómo sacarle más a su cosecha.</p>

            <form wire:submit="save" class="mt-5 space-y-4" novalidate>
                <div class="max-w-xl">
                    <label for="lead-email" class="block text-lg font-bold text-petrol-900">Su correo</label>
                    <input id="lead-email" type="email" inputmode="email" autocomplete="email" wire:model="email" aria-describedby="lead-email-example"
                           class="mt-2 block w-full rounded-xl border-2 border-gray-300 bg-white px-4 py-3 text-xl text-gray-900 focus:border-brand-600 focus:outline-none focus:ring-4 focus:ring-brand-600/20">
                    <p id="lead-email-example" class="mt-2 text-base text-gray-500">Ejemplo: nombre@gmail.com</p>
                    @error('email')
                        <p class="mt-2 rounded-xl bg-red-50 px-4 py-2 text-base font-semibold text-red-800" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Trampa para robots: oculta para las personas. --}}
                <div class="hidden" aria-hidden="true">
                    <label for="lead-website">No llene este campo</label>
                    <input id="lead-website" type="text" tabindex="-1" autocomplete="off" wire:model="website">
                </div>

                <label class="flex max-w-2xl cursor-pointer items-start gap-3 text-base text-gray-700">
                    <input type="checkbox" wire:model="consent" class="mt-1 h-6 w-6 shrink-0 rounded border-2 border-gray-400 text-brand-600 focus:ring-brand-600">
                    <span>Autorizo a la Extractora El Pajuil a guardar mi correo para enviarme información sobre el Método R-100 y la compra de fruta, según la Ley 1581 de 2012. Puedo pedir que lo borren cuando quiera.</span>
                </label>
                @error('consent')
                    <p class="rounded-xl bg-red-50 px-4 py-2 text-base font-semibold text-red-800" role="alert">{{ $message }}</p>
                @enderror

                <button type="submit" wire:loading.attr="disabled"
                        class="w-full rounded-2xl bg-brand-600 px-6 py-4 text-xl font-bold text-white hover:bg-brand-700 disabled:opacity-60 sm:w-auto">
                    Quiero saber más
                </button>
            </form>
        @endif
    </div>
</section>
