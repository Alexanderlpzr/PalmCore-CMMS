<?php

use Filament\Facades\Filament;

/**
 * El orden maestro del menú tiene que nombrar todos los grupos que se usan.
 *
 * Filament pinta los grupos **no declarados antes** de los declarados. Un grupo olvidado
 * en la lista no cae al final: se cuela arriba del todo y deshace el orden entero. Pasó
 * durante meses con «Gestión de Activos» —los catálogos por delante del ciclo diario de
 * mantenimiento— porque la lista seguía nombrando «Estructura Operativa», un grupo que
 * ningún recurso usaba ya.
 *
 * Un comentario no lo habría impedido: la lista y los recursos viven en archivos
 * distintos y nadie los cruza al añadir una pantalla.
 */
it('declara en el orden maestro todos los grupos que algún recurso usa', function (): void {
    $panel = Filament::getPanel('admin');

    $declarados = collect($panel->getNavigationGroups())
        ->map(fn ($grupo): ?string => is_string($grupo) ? $grupo : $grupo->getLabel())
        ->filter()
        ->values()
        ->all();

    $usados = collect([...$panel->getResources(), ...$panel->getPages()])
        ->map(function (string $clase): ?string {
            if (! method_exists($clase, 'getNavigationGroup')) {
                return null;
            }

            $grupo = $clase::getNavigationGroup();

            return is_string($grupo) ? $grupo : $grupo?->getLabel();
        })
        ->filter()
        ->unique()
        ->values();

    $huerfanos = $usados->reject(fn (string $g): bool => in_array($g, $declarados, true));

    expect($huerfanos->all())->toBe(
        [],
        'Estos grupos no están en navigationGroups() y Filament los pintará por delante '
        .'de todo lo demás: '.$huerfanos->implode(', ')
    );
});

it('no deja en el orden maestro grupos que ya nadie usa', function (): void {
    $panel = Filament::getPanel('admin');

    $declarados = collect($panel->getNavigationGroups())
        ->map(fn ($grupo): ?string => is_string($grupo) ? $grupo : $grupo->getLabel())
        ->filter();

    $usados = collect([...$panel->getResources(), ...$panel->getPages()])
        ->map(function (string $clase): ?string {
            if (! method_exists($clase, 'getNavigationGroup')) {
                return null;
            }

            $grupo = $clase::getNavigationGroup();

            return is_string($grupo) ? $grupo : $grupo?->getLabel();
        })
        ->filter()
        ->unique();

    // Un nombre muerto en la lista no rompe nada por sí solo, pero es exactamente cómo
    // empezó el problema: se renombró el grupo y la lista se quedó apuntando al anterior.
    $muertos = $declarados->reject(fn (string $g): bool => $usados->contains($g));

    expect($muertos->all())->toBe([], 'Grupos declarados que ningún recurso usa: '.$muertos->implode(', '));
});

it('pone el ciclo diario de mantenimiento antes que los catálogos', function (): void {
    $declarados = collect(Filament::getPanel('admin')->getNavigationGroups())
        ->map(fn ($grupo): ?string => is_string($grupo) ? $grupo : $grupo->getLabel())
        ->filter()
        ->values();

    // El principio que el propio archivo declara: lo que se usa a diario primero, la
    // administración esporádica al final.
    expect($declarados->search('Mantenimiento'))
        ->toBeLessThan($declarados->search('Gestión de Activos'))
        ->and($declarados->search('Indicadores'))
        ->toBeLessThan($declarados->search('Usuarios & Acceso'));
});
