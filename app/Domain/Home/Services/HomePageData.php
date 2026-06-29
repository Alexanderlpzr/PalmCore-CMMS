<?php

namespace App\Domain\Home\Services;

/**
 * Immutable, view-ready snapshot of every Inicio portal section.
 *
 * Keeps the Filament page and Blade view decoupled from how each section is
 * sourced: the page exposes one object, the view reads named properties. The
 * property order mirrors the HOME-2 narrative the user scrolls through:
 * hero → atención → acciones → carrusel → noticias → actividad.
 */
class HomePageData
{
    /**
     * @param  array<string, mixed>  $hero  Greeting context: greeting, name, role, company, date, status.
     * @param  array<int, array<string, mixed>>  $attentionItems
     * @param  array<int, array<string, mixed>>  $quickActions
     * @param  array<int, array<string, mixed>>  $carouselSlides
     * @param  array<int, array<string, mixed>>  $importantNotices
     * @param  array<int, array<string, mixed>>  $newsAndCommunications
     * @param  array<int, array<string, mixed>>  $recentActivity
     */
    public function __construct(
        public array $hero,
        public array $attentionItems,
        public array $quickActions,
        public array $carouselSlides,
        public array $importantNotices,
        public array $newsAndCommunications,
        public array $recentActivity,
    ) {}
}
