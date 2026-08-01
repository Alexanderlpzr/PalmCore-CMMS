@php
    $heading = $this->getHeading();
    $subheading = $this->getSubheading();
    $hasLogo = $this->hasLogo();
    $images = $this->getBackgroundImages();
@endphp

<div class="fi-simple-page w-full">
    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIMPLE_PAGE_START, scopes: $this->getRenderHookScopes()) }}

    {{-- Degradado entre los dos colores del logo: petróleo del engranaje →
         verde de la fronda. Antes eran dos verdes inventados (#264a35 → #0d2016)
         que no salían de ninguna parte de la marca. --}}
    <div class="relative min-h-screen w-full overflow-hidden bg-[linear-gradient(150deg,#002331,#052811_70%)]">
        @if ($images->isNotEmpty())
            <div
                @if ($images->count() > 1)
                    x-data="{ slide: 0 }"
                    x-init="setInterval(() => slide = (slide + 1) % {{ $images->count() }}, 6000)"
                @endif
                class="absolute inset-0"
            >
                @foreach ($images as $index => $image)
                    <div
                        @if ($images->count() > 1)
                            x-show="slide === {{ $index }}"
                            x-transition:enter="transition-opacity ease-out duration-1000"
                            x-transition:enter-start="opacity-0"
                            x-transition:enter-end="opacity-100"
                            x-transition:leave="transition-opacity ease-in duration-1000"
                            x-transition:leave-start="opacity-100"
                            x-transition:leave-end="opacity-0"
                        @endif
                        class="absolute inset-0"
                    >
                        <img
                            src="{{ $image->imageUrl() }}"
                            alt="{{ $image->caption }}"
                            class="h-full w-full object-cover"
                            onerror="this.style.display='none'"
                        />
                    </div>
                @endforeach
            </div>
        @else
            {{-- Sin fotos activas: textura orgánica sutil sobre el degradado, no un
                 panel liso vacío. --}}
            <div
                class="absolute inset-0"
                style="background-image: repeating-linear-gradient(100deg, rgba(255,255,255,.04) 0 3px, transparent 3px 30px)"
            ></div>
        @endif

        {{-- Oscurecimiento de arriba hacia abajo, para que la tarjeta y el título
             siempre tengan contraste sin importar qué tan clara sea la foto. --}}
        <div
            class="pointer-events-none absolute inset-0"
            style="background-image: linear-gradient(to top, rgba(0,13,20,.78), rgba(0,13,20,.15) 45%)"
        ></div>

        @if ($images->isNotEmpty())
            @foreach ($images as $index => $image)
                @if ($image->caption)
                    <div
                        @if ($images->count() > 1)
                            x-show="slide === {{ $index }}"
                        @endif
                        class="pointer-events-none absolute inset-x-0 bottom-0 z-10 px-10 py-8"
                    >
                        <p class="font-medium text-white/90">{{ $image->caption }}</p>
                    </div>
                @endif
            @endforeach
        @endif

        <div class="relative z-10 flex min-h-screen items-center justify-center px-6 lg:justify-end lg:px-14">
            {{-- La tarjeta tiene que seguir al tema. Cuando era crema fija, el modo
                 oscuro de Filament seguía pintando los campos, etiquetas y mensajes
                 de error de adentro con sus colores oscuros (texto claro), y quedaban
                 blancos sobre crema: ilegibles. Ahora la superficie cambia con el
                 tema igual que su contenido. --}}
            <div class="w-full max-w-[360px] rounded-2xl bg-[rgba(251,250,246,0.94)] px-[34px] py-10 shadow-[0_20px_50px_rgba(0,0,0,0.35)] backdrop-blur-md dark:bg-[rgba(1,28,39,0.94)] dark:ring-1 dark:ring-white/10">
                @if ($hasLogo)
                    {{-- «FRONDA» está escrito en el petróleo del logo (#00384C), que
                         sobre la tarjeta oscura sería prácticamente invisible. En vez
                         de teñir el logo con un filtro —que le cambiaría los colores
                         de marca— se le da una placa clara en modo oscuro, que es su
                         fondo previsto. --}}
                    <div class="mb-7 dark:-mx-2 dark:inline-block dark:rounded-lg dark:bg-white/95 dark:px-3 dark:py-2">
                        <img
                            src="{{ filament()->getBrandLogo() }}"
                            alt="{{ filament()->getBrandName() }}"
                            class="h-7 w-auto"
                        />
                    </div>
                @endif

                @if (filled($heading))
                    <h1 class="mb-6 font-[Fraunces] text-2xl font-medium text-petrol-900 dark:text-white">{{ $heading }}</h1>
                @endif

                @if (filled($subheading))
                    <p class="-mt-4 mb-6 text-sm text-petrol-500 dark:text-petrol-300">{{ $subheading }}</p>
                @endif

                {{ $this->content }}
            </div>
        </div>
    </div>

    @if (! $this instanceof \Filament\Tables\Contracts\HasTable)
        <x-filament-actions::modals />
    @endif

    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIMPLE_PAGE_END, scopes: $this->getRenderHookScopes()) }}
</div>

<style>
    /*
     * El handoff de diseño (LOGIN-2, opción 1b) pide bordes, radios y tamaños de
     * fuente exactos en los inputs que no vienen en la paleta de colores de
     * Filament — no hay forma de lograrlos solo con ->color(). Se targetea la
     * clase marcador `fi-login-field` (añadida vía ->extraInputAttributes() en
     * Login.php) en vez de .fi-input directo, para no tocar el resto del panel.
     *
     * El borde y el foco se declaran con variables resueltas por tema: cuando
     * eran hex fijos, ganaban por especificidad sobre el modo oscuro de Filament
     * y los campos quedaban con borde claro sobre superficie oscura.
     */
    .fi-simple-page .fi-login-field {
        border-color: var(--login-field-border);
        border-radius: 7px;
        font-size: 13.5px;
    }

    .fi-simple-page .fi-login-field:focus {
        border-color: var(--login-field-focus);
        box-shadow: 0 0 0 1px var(--login-field-focus);
    }

    .fi-simple-page {
        --login-field-border: #d8dcd4;
        --login-field-focus: #1a7e42; /* brand-600, el verde del logo */
    }

    .dark .fi-simple-page {
        --login-field-border: rgba(255, 255, 255, 0.14);
        --login-field-focus: #64b67b; /* brand-400: contrasta 7.2:1 en oscuro */
    }
</style>
