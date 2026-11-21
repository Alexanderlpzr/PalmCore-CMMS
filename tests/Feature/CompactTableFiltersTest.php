<?php

/*
 * Los filtros de las tablas van en una barra compacta, en los dos paneles. El CSS no se
 * puede ejercer desde PHP: aquí se cuida lo que lo rompería sin que nadie lo note.
 */

it('loads the compact filter bar in both panels', function (string $panel): void {
    expect(file_get_contents(resource_path("css/filament/{$panel}/theme.css")))
        ->toContain("@import '../compact-table-filters.css';");
})->with(['admin', 'platform']);

it('does not nest :has(), which makes the browser drop the whole rule', function (): void {
    $css = file_get_contents(resource_path('css/filament/compact-table-filters.css'));

    expect($css)->toContain('.fi-ta-filters-above-content-ctn')
        ->and(preg_match('/:has\((?:[^()]|\([^()]*\))*:has\(/', $css))->toBe(0);
});

it('keeps the filter buttons short enough to share the row', function (): void {
    app()->setLocale('es');

    expect(__('filament-tables::table.filters.actions.apply.label'))->toBe('Aplicar')
        ->and(__('filament-tables::table.filters.actions.reset.label'))->toBe('Limpiar');
});
