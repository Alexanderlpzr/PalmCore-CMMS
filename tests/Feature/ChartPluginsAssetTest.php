<?php

use Filament\Support\Facades\FilamentAsset;

/*
 * El complemento de etiquetas de las gráficas se registra sin esquema («//host/…»): con
 * APP_URL en http y la página en https, un «http://» lo bloqueaba la política de
 * seguridad del navegador y las tortas se quedaban sin sus valores.
 */

it('registers the chart labels plugin without the http scheme', function (): void {
    config(['app.url' => 'http://fronda.app']);

    $src = FilamentAsset::getScriptSrc('chart-js-plugins');

    expect($src)->toStartWith('//')
        ->and($src)->toContain('/build/assets/filament-chart-js-plugins');
})->skip(fn (): bool => ! is_file(public_path('build/manifest.json')), 'Sin los recursos compilados no se registra el complemento.');
