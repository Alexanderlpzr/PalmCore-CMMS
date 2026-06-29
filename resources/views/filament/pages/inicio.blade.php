@php
    /**
     * HOME-2.2 — Fronda Workspace Experience (Premium Polish).
     *
     * Tone → class maps centralise the 5 Design-System roles (brand/emerald,
     * info/blue, warning/amber, danger/red, neutral/gray). NO violet, no new
     * colors, no scattered hardcoding — every section reads tones from here.
     */
    $toneDot = [
        'brand' => 'bg-emerald-500', 'info' => 'bg-blue-500',
        'warning' => 'bg-amber-500', 'danger' => 'bg-red-500', 'neutral' => 'bg-gray-400',
    ];
    $toneIconChip = [
        'brand' => 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400',
        'info' => 'bg-blue-100 text-blue-600 dark:bg-blue-500/15 dark:text-blue-400',
        'warning' => 'bg-amber-100 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400',
        'danger' => 'bg-red-100 text-red-600 dark:bg-red-500/15 dark:text-red-400',
        'neutral' => 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-300',
    ];
    $toneAccent = [
        'brand' => 'text-emerald-600 dark:text-emerald-400',
        'info' => 'text-blue-600 dark:text-blue-400',
        'warning' => 'text-amber-600 dark:text-amber-400',
        'danger' => 'text-red-600 dark:text-red-400',
        'neutral' => 'text-gray-500 dark:text-gray-400',
    ];
    $toneRing = [
        'brand' => 'hover:ring-emerald-300 dark:hover:ring-emerald-500/40',
        'info' => 'hover:ring-blue-300 dark:hover:ring-blue-500/40',
        'warning' => 'hover:ring-amber-300 dark:hover:ring-amber-500/40',
        'danger' => 'hover:ring-red-300 dark:hover:ring-red-500/40',
        'neutral' => 'hover:ring-gray-300 dark:hover:ring-gray-600',
    ];
    // Soft tinted pill for the HERO status — the container now accompanies the tone.
    $toneStatusPill = [
        'brand' => 'border-emerald-200 bg-emerald-50/80 text-emerald-800 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-200',
        'warning' => 'border-amber-200 bg-amber-50/80 text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200',
        'danger' => 'border-red-200 bg-red-50/80 text-red-800 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-200',
        'neutral' => 'border-gray-200 bg-white/70 text-gray-700 dark:border-gray-700 dark:bg-gray-800/60 dark:text-gray-200',
    ];
    $newsBadge = [
        'blue' => 'bg-blue-100 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300',
        'emerald' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
    ];
    // Category-tinted placeholder so image-less news cards keep their identity.
    $newsPlaceholder = [
        'blue' => 'from-blue-50 to-blue-100/40 dark:from-blue-500/10 dark:to-blue-500/5',
        'emerald' => 'from-emerald-50 to-emerald-100/40 dark:from-emerald-500/10 dark:to-emerald-500/5',
    ];
    $heroTone = $home->hero['tone'] ?? ($home->hero['status']['tone'] ?? 'brand');
    $heroBackdrop = $home->carouselSlides[0]['image_url'] ?? null;
    $companyInitial = mb_strtoupper(mb_substr($home->hero['company'] ?? '', 0, 1));
@endphp

<x-filament-panels::page>
    <div class="space-y-14">

        {{-- ═══════════════════ 1 · HERO ═══════════════════ --}}
        <section
            aria-label="Bienvenida"
            class="relative -mx-4 -mt-6 overflow-hidden border-b border-gray-100 sm:-mx-6 lg:-mx-8 dark:border-gray-800"
        >
            {{-- Faint company photo backdrop --}}
            @if ($heroBackdrop)
                <img src="{{ $heroBackdrop }}" alt="" aria-hidden="true"
                     class="absolute inset-0 h-full w-full object-cover opacity-[0.07] dark:opacity-[0.05]">
            @endif
            <div class="absolute inset-0 bg-gradient-to-br from-white via-white to-emerald-50/40 dark:from-gray-900 dark:via-gray-900 dark:to-emerald-950/20"></div>

            {{-- Subtle corporate watermark: the company initial, very faint --}}
            @if ($companyInitial)
                <span aria-hidden="true"
                      class="pointer-events-none absolute -right-2 top-1/2 hidden -translate-y-1/2 select-none text-[12rem] font-black leading-none text-emerald-950/[0.04] lg:block dark:text-white/[0.03]">
                    {{ $companyInitial }}
                </span>
            @endif

            <div
                x-data="{
                    time: '',
                    tick() { this.time = new Date().toLocaleTimeString('es', { hour: '2-digit', minute: '2-digit' }) },
                }"
                x-init="tick(); setInterval(() => tick(), 30000)"
                class="relative px-6 py-10 sm:px-10 lg:px-12 lg:py-14"
            >
                <p class="text-base font-medium text-gray-500 dark:text-gray-400">
                    {{ $home->hero['greeting'] }}@if ($home->hero['name']), <span class="text-gray-700 dark:text-gray-200">{{ $home->hero['name'] }}</span>@endif.
                </p>

                <h1 class="mt-1 text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl lg:text-4xl dark:text-white">
                    {{ $home->hero['company'] }}
                </h1>

                <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-gray-500 dark:text-gray-400">
                    <span>{{ $home->hero['role'] }}</span>
                    <span aria-hidden="true" class="text-gray-300 dark:text-gray-600">•</span>
                    <span>{{ $home->hero['date_human'] }}</span>
                    <span aria-hidden="true" class="text-gray-300 dark:text-gray-600">•</span>
                    <span x-text="time" class="tabular-nums"></span>
                </div>

                {{-- Estado general — a calm, human sentence whose pill now carries the tone --}}
                <div class="mt-6 inline-flex max-w-xl items-center gap-2.5 rounded-full border px-4 py-2 backdrop-blur {{ $toneStatusPill[$heroTone] ?? $toneStatusPill['neutral'] }}">
                    <span class="relative flex h-2.5 w-2.5 shrink-0">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full opacity-60 motion-reduce:animate-none {{ $toneDot[$heroTone] ?? $toneDot['neutral'] }}"></span>
                        <span class="relative inline-flex h-2.5 w-2.5 rounded-full {{ $toneDot[$heroTone] ?? $toneDot['neutral'] }}"></span>
                    </span>
                    <span class="text-sm font-medium">
                        {{ $home->hero['headline'] ?? ($home->hero['status']['message'] ?? '') }}
                    </span>
                </div>
            </div>
        </section>

        {{-- ═══════════════════ 2 · ATENCIÓN REQUERIDA ═══════════════════ --}}
        <section aria-labelledby="attention-heading">
            <h2 id="attention-heading" class="mb-5 text-xl font-bold tracking-tight text-gray-900 dark:text-white">
                Atención requerida
            </h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($home->attentionItems as $item)
                    @php $isZero = (int) $item['count'] === 0; @endphp
                    <a href="{{ $item['route'] }}"
                       aria-label="{{ $item['count'] }} {{ $item['label'] }} — {{ $item['hint'] }}"
                       @class([
                            'group relative flex flex-col gap-3 rounded-2xl border p-5 shadow-sm ring-1 ring-transparent transition duration-200 hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500',
                            'border-gray-100 bg-white dark:border-gray-700 dark:bg-gray-800' => ! $isZero,
                            $toneRing[$item['tone']] => ! $isZero,
                            // Zero → the whole card recedes: it requires no attention.
                            'border-gray-100 bg-gray-50/60 opacity-60 hover:opacity-100 dark:border-gray-800 dark:bg-gray-800/40' => $isZero,
                       ])>
                        <div class="flex items-center justify-between">
                            <span class="flex h-11 w-11 items-center justify-center rounded-xl {{ $isZero ? $toneIconChip['neutral'] : $toneIconChip[$item['tone']] }}">
                                <x-filament::icon :icon="$item['icon']" class="h-6 w-6" />
                            </span>
                            @if ($isZero)
                                <span class="text-3xl font-bold tabular-nums text-gray-300 dark:text-gray-600">0</span>
                            @else
                                <span
                                    class="text-3xl font-bold tabular-nums {{ $toneAccent[$item['tone']] }}"
                                    x-data="{ n: 0, target: {{ (int) $item['count'] }} }"
                                    x-init="
                                        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) { n = target; return }
                                        const start = performance.now(), dur = 340;
                                        const step = (t) => {
                                            const p = Math.min((t - start) / dur, 1);
                                            n = Math.round((1 - Math.pow(1 - p, 3)) * target);
                                            if (p < 1) requestAnimationFrame(step);
                                        };
                                        requestAnimationFrame(step);
                                    "
                                    x-text="n"
                                >{{ $item['count'] }}</span>
                            @endif
                        </div>
                        <div>
                            <p class="font-semibold {{ $isZero ? 'text-gray-500 dark:text-gray-400' : 'text-gray-900 dark:text-white' }}">{{ $item['label'] }}</p>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $item['hint'] }}</p>
                        </div>
                        <span aria-hidden="true"
                              class="absolute bottom-4 right-4 text-gray-300 opacity-0 transition group-hover:translate-x-0.5 group-hover:opacity-100 dark:text-gray-500">
                            <x-filament::icon icon="heroicon-m-arrow-right" class="h-4 w-4" />
                        </span>
                    </a>
                @endforeach
            </div>
        </section>

        {{-- ═══════════════════ 3 · ACCIONES RÁPIDAS ═══════════════════ --}}
        <section aria-labelledby="actions-heading">
            <h2 id="actions-heading" class="mb-5 text-xl font-bold tracking-tight text-gray-900 dark:text-white">
                Acciones rápidas
            </h2>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                @foreach ($home->quickActions as $action)
                    <a href="{{ $action['route'] }}"
                       aria-label="{{ $action['label'] }} — {{ $action['description'] }}"
                       class="group flex flex-col gap-3 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-emerald-200 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-emerald-500/40">
                        <span class="flex h-11 w-11 items-center justify-center rounded-xl transition duration-200 group-hover:scale-110 {{ $toneIconChip[$action['tone']] }}">
                            <x-filament::icon :icon="$action['icon']" class="h-6 w-6" />
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $action['label'] }}</p>
                            <p class="mt-0.5 text-xs leading-snug text-gray-500 line-clamp-2 dark:text-gray-400">{{ $action['description'] }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>

        {{-- ═══════════════════ 4 · CARRUSEL INSTITUCIONAL ═══════════════════ --}}
        @if (count($home->carouselSlides))
            <section
                aria-label="Carrusel institucional"
                aria-roledescription="carrusel"
                x-data="{
                    active: 0,
                    count: {{ count($home->carouselSlides) }},
                    reduced: window.matchMedia('(prefers-reduced-motion: reduce)').matches,
                    duration: 7000,
                    progress: 0,
                    raf: null,
                    slideStart: 0,
                    start() {
                        if (this.reduced || this.count < 2) return;
                        this.slideStart = performance.now();
                        const loop = (t) => {
                            const p = Math.min((t - this.slideStart) / this.duration, 1);
                            this.progress = p * 100;
                            if (p >= 1) { this.next(); this.slideStart = t; this.progress = 0; }
                            this.raf = requestAnimationFrame(loop);
                        };
                        this.raf = requestAnimationFrame(loop);
                    },
                    stop() { if (this.raf) cancelAnimationFrame(this.raf); this.raf = null; },
                    reset() { this.slideStart = performance.now(); this.progress = 0; },
                    next() { this.active = (this.active + 1) % this.count },
                    prev() { this.active = (this.active - 1 + this.count) % this.count },
                    go(i) { this.active = i },
                }"
                x-init="start()"
                @mouseenter="stop()" @mouseleave="start()"
                @keydown.left.prevent="prev(); reset()" @keydown.right.prevent="next(); reset()"
                tabindex="0"
                class="relative -mx-4 overflow-hidden rounded-none bg-gray-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 sm:-mx-6 sm:rounded-3xl lg:-mx-8"
            >
                <div class="relative h-64 sm:h-80 lg:h-[26rem]">
                    @foreach ($home->carouselSlides as $i => $slide)
                        <div
                            x-show="active === {{ $i }}"
                            x-transition:enter="transition ease-out duration-700"
                            x-transition:enter-start="opacity-0"
                            x-transition:enter-end="opacity-100"
                            role="group"
                            aria-roledescription="diapositiva"
                            aria-label="{{ $i + 1 }} de {{ count($home->carouselSlides) }}{{ $slide['title'] ? ': '.$slide['title'] : '' }}"
                            class="absolute inset-0"
                            @if ($i !== 0) style="display: none;" @endif
                        >
                            @if ($slide['image_url'])
                                <img src="{{ $slide['image_url'] }}" alt="{{ $slide['title'] ?? '' }}"
                                     @if ($i === 0) fetchpriority="high" @else loading="lazy" @endif
                                     class="absolute inset-0 h-full w-full object-cover">
                            @else
                                <div class="absolute inset-0 bg-gradient-to-br from-emerald-600 via-emerald-700 to-gray-900"></div>
                            @endif
                            <div class="absolute inset-0 bg-gradient-to-r from-black/75 via-black/45 to-transparent"></div>

                            <div class="relative flex h-full items-center">
                                <div class="max-w-2xl px-8 sm:px-12 lg:px-16">
                                    @if ($slide['subtitle'])
                                        <p class="mb-2 text-xs font-semibold uppercase tracking-widest text-emerald-300">{{ $slide['subtitle'] }}</p>
                                    @endif
                                    @if ($slide['title'])
                                        <h3 class="text-2xl font-bold leading-tight text-white sm:text-3xl lg:text-4xl">{{ $slide['title'] }}</h3>
                                    @endif
                                    @if ($slide['description'])
                                        <p class="mt-3 max-w-xl text-sm text-gray-100 line-clamp-3 sm:text-base">{{ $slide['description'] }}</p>
                                    @endif
                                    @if ($slide['button_label'] && $slide['button_url'])
                                        <a href="{{ $slide['button_url'] }}"
                                           class="mt-5 inline-flex items-center gap-2 rounded-lg bg-emerald-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg transition hover:bg-emerald-400 focus:outline-none focus-visible:ring-2 focus-visible:ring-white">
                                            {{ $slide['button_label'] }}
                                            <x-filament::icon icon="heroicon-m-arrow-right" class="h-4 w-4" />
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                @if (count($home->carouselSlides) > 1)
                    <button type="button" @click="prev(); reset()" aria-label="Diapositiva anterior"
                            class="absolute left-5 top-1/2 -translate-y-1/2 rounded-full bg-black/25 p-2 text-white ring-1 ring-white/20 backdrop-blur transition hover:bg-black/40 focus:outline-none focus-visible:ring-2 focus-visible:ring-white">
                        <x-filament::icon icon="heroicon-m-chevron-left" class="h-6 w-6" />
                    </button>
                    <button type="button" @click="next(); reset()" aria-label="Diapositiva siguiente"
                            class="absolute right-5 top-1/2 -translate-y-1/2 rounded-full bg-black/25 p-2 text-white ring-1 ring-white/20 backdrop-blur transition hover:bg-black/40 focus:outline-none focus-visible:ring-2 focus-visible:ring-white">
                        <x-filament::icon icon="heroicon-m-chevron-right" class="h-6 w-6" />
                    </button>

                    <div class="absolute bottom-5 left-1/2 flex -translate-x-1/2 gap-2">
                        @foreach ($home->carouselSlides as $i => $slide)
                            <button type="button" @click="go({{ $i }}); reset()"
                                    :aria-label="'Ir a la diapositiva {{ $i + 1 }}'"
                                    :aria-current="active === {{ $i }} ? 'true' : 'false'"
                                    :class="active === {{ $i }} ? 'w-8 bg-white' : 'w-2.5 bg-white/50'"
                                    class="h-2.5 rounded-full transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-white"></button>
                        @endforeach
                    </div>

                    {{-- Sutil barra de progreso del auto-avance (oculta con reduced-motion) --}}
                    <div class="absolute inset-x-0 bottom-0 h-0.5 bg-white/15" x-show="!reduced" x-cloak>
                        <div class="h-full bg-white/80" :style="`width: ${progress}%`"></div>
                    </div>
                @endif
            </section>
        @endif

        {{-- ═══════════════════ 5 · NOTICIAS ═══════════════════ --}}
        <section aria-labelledby="news-heading">
            <h2 id="news-heading" class="mb-5 text-xl font-bold tracking-tight text-gray-900 dark:text-white">
                Noticias y comunicados
            </h2>

            @if (count($home->newsAndCommunications))
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($home->newsAndCommunications as $news)
                        <article class="group flex flex-col overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-md dark:border-gray-700 dark:bg-gray-800">
                            @if ($news['image_url'])
                                <div class="h-40 w-full overflow-hidden">
                                    <img src="{{ $news['image_url'] }}" alt="{{ $news['title'] }}" loading="lazy"
                                         class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]">
                                </div>
                            @else
                                <div class="flex h-32 w-full items-center justify-center bg-gradient-to-br {{ $newsPlaceholder[$news['category_color'] ?? ''] ?? 'from-gray-50 to-gray-100/50 dark:from-gray-700/40 dark:to-gray-700/20' }}">
                                    <x-filament::icon icon="heroicon-o-newspaper" class="h-8 w-8 text-gray-300 dark:text-gray-600" />
                                </div>
                            @endif
                            <div class="flex flex-1 flex-col p-5">
                                <div class="mb-2 flex items-center gap-2">
                                    <span class="inline-flex rounded px-2 py-0.5 text-xs font-medium {{ $newsBadge[$news['category_color'] ?? ''] ?? 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' }}">
                                        {{ $news['category_label'] ?? 'Noticia' }}
                                    </span>
                                    @if ($news['published_human'])
                                        <span class="text-xs text-gray-400">{{ $news['published_human'] }}</span>
                                    @endif
                                </div>
                                <h3 class="font-semibold leading-snug text-gray-900 dark:text-white">{{ $news['title'] }}</h3>
                                @if ($news['summary'])
                                    <p class="mt-1.5 flex-1 text-sm text-gray-600 line-clamp-3 dark:text-gray-300">{{ $news['summary'] }}</p>
                                @endif
                                <a href="{{ $news['button_url'] ?? '#' }}"
                                   class="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-emerald-600 transition hover:text-emerald-800 focus:outline-none focus-visible:underline dark:text-emerald-400">
                                    {{ $news['button_label'] }}
                                    <x-filament::icon icon="heroicon-m-arrow-right" class="h-3.5 w-3.5 transition group-hover:translate-x-0.5" />
                                </a>
                            </div>
                        </article>
                    @endforeach
                </div>
            @else
                <div class="rounded-2xl border border-dashed border-gray-200 bg-white p-10 text-center dark:border-gray-700 dark:bg-gray-800">
                    <x-filament::icon icon="heroicon-o-newspaper" class="mx-auto h-10 w-10 text-gray-300" />
                    <p class="mt-2 text-sm text-gray-500">Aún no hay noticias publicadas.</p>
                </div>
            @endif
        </section>

        {{-- ═══════════════════ 6 · ACTIVIDAD ═══════════════════ --}}
        <section aria-labelledby="activity-heading">
            <h2 id="activity-heading" class="mb-5 text-xl font-bold tracking-tight text-gray-900 dark:text-white">
                Actividad reciente
            </h2>

            @if (count($home->recentActivity))
                <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <ol class="relative space-y-6 border-l border-gray-200 pl-6 dark:border-gray-700">
                        @foreach ($home->recentActivity as $event)
                            <li class="relative">
                                <span class="absolute -left-[1.95rem] flex h-6 w-6 items-center justify-center rounded-full ring-4 ring-white {{ $toneIconChip[$event['tone']] }} dark:ring-gray-800">
                                    <x-filament::icon :icon="$event['icon']" class="h-3.5 w-3.5" />
                                </span>
                                <p class="text-sm leading-snug text-gray-700 dark:text-gray-200">
                                    <span class="font-semibold text-gray-900 dark:text-white">{{ $event['actor'] }}</span>
                                    <span class="text-gray-500 dark:text-gray-400">{{ $event['action'] }}</span>
                                    <span class="font-medium">{{ $event['title'] }}</span>
                                </p>
                                <div class="mt-0.5 flex items-center gap-2 text-xs text-gray-400">
                                    @if ($event['meta'])
                                        <span>{{ $event['meta'] }}</span>
                                        <span aria-hidden="true">·</span>
                                    @endif
                                    <time datetime="{{ $event['iso'] }}" title="{{ $event['time_human'] }}">{{ $event['at_human'] }}</time>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </div>
            @else
                <div class="rounded-2xl border border-dashed border-gray-200 bg-white p-10 text-center dark:border-gray-700 dark:bg-gray-800">
                    <x-filament::icon icon="heroicon-o-clock" class="mx-auto h-10 w-10 text-gray-300" />
                    <p class="mt-2 text-sm text-gray-500">Sin actividad reciente.</p>
                </div>
            @endif
        </section>

    </div>
</x-filament-panels::page>
