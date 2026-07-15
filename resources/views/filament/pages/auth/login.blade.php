@php
    $heading = $this->getHeading();
    $subheading = $this->getSubheading();
    $hasLogo = $this->hasLogo();
    $images = $this->getBackgroundImages();
@endphp

<div class="fi-simple-page">
    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIMPLE_PAGE_START, scopes: $this->getRenderHookScopes()) }}

    <div class="grid w-full min-h-screen lg:grid-cols-2">
        <div
            @if ($images->count() > 1)
                x-data="{ slide: 0 }"
                x-init="setInterval(() => slide = (slide + 1) % {{ $images->count() }}, 6000)"
            @endif
            class="relative hidden overflow-hidden bg-linear-to-br from-emerald-700 via-emerald-800 to-emerald-950 lg:block"
        >
            @if ($images->isNotEmpty())
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
                        />

                        @if ($image->caption)
                            <div class="absolute inset-x-0 bottom-0 bg-linear-to-t from-emerald-950/85 to-transparent px-10 py-8">
                                <p class="text-lg font-medium text-white">{{ $image->caption }}</p>
                            </div>
                        @endif
                    </div>
                @endforeach

                {{-- Vela de color de marca sobre las fotos: sin esto, cada foto trae su
                     propio balance de color y el carrusel se ve como fotos sueltas, no
                     como parte de un mismo producto. --}}
                <div class="pointer-events-none absolute inset-0 bg-linear-to-b from-emerald-950/30 via-transparent to-emerald-950/50 mix-blend-multiply"></div>
                <div class="pointer-events-none absolute inset-0 bg-emerald-800/10"></div>
            @endif
        </div>

        <div class="flex items-center justify-center px-6 py-12 sm:px-12">
            <div class="w-full max-w-md">
                @if (filled($heading) || $hasLogo || filled($subheading))
                    <x-filament-panels::header.simple
                        :heading="$heading"
                        :logo="$hasLogo"
                        :subheading="$subheading"
                    />
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
