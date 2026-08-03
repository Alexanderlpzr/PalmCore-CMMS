<?php

use App\Support\FrondaPalette;
use Filament\Support\Colors\Color;
use Filament\Support\View\Components\ColorMaps\ButtonComponentColorMap;

/*
 * La paleta de marca vive en dos sitios que tienen que decir lo mismo:
 * app/Support/FrondaPalette.php (lo que consume Filament) y
 * resources/css/fronda-tokens.css (lo que consumen Tailwind, Ops y Mobile).
 * Nada obliga al compilador a mantenerlos sincronizados, así que lo hace este
 * test — junto con el contraste, que es la razón por la que el login era
 * ilegible en modo oscuro y no había forma de detectarlo automáticamente.
 */

/** Luminancia relativa WCAG 2.1 de un color #rrggbb. */
function relativeLuminance(string $hex): float
{
    [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');

    $channel = fn (int $c): float => ($c /= 255) <= 0.03928
        ? $c / 12.92
        : (($c + 0.055) / 1.055) ** 2.4;

    return 0.2126 * $channel($r) + 0.7152 * $channel($g) + 0.0722 * $channel($b);
}

/** Razón de contraste WCAG 2.1 entre dos colores, de 1 a 21. */
function contrastRatio(string $foreground, string $background): float
{
    $a = relativeLuminance($foreground);
    $b = relativeLuminance($background);

    [$lighter, $darker] = $a > $b ? [$a, $b] : [$b, $a];

    return round(($lighter + 0.05) / ($darker + 0.05), 2);
}

/**
 * Lee las rampas --color-brand-* y --color-petrol-* del archivo de tokens CSS.
 *
 * @return array<string, array<int, string>>
 */
function cssTokenRamps(): array
{
    // Ruta relativa a propósito: es un test unitario y no arranca la aplicación,
    // así que los helpers de Laravel como resource_path() no están disponibles.
    $css = file_get_contents(dirname(__DIR__, 2).'/resources/css/fronda-tokens.css');

    preg_match_all('/--color-(brand|petrol)-(\d+):\s*(#[0-9a-f]{6});/i', $css, $matches, PREG_SET_ORDER);

    $ramps = [];

    foreach ($matches as [, $name, $shade, $hex]) {
        $ramps[$name][(int) $shade] = strtolower($hex);
    }

    return $ramps;
}

it('ancla cada rampa en el color exacto del logotipo', function (): void {
    // Muestreados de los píxeles de public/images/logo.png: si alguien cambia el
    // logo, este test es el que avisa de que la paleta dejó de coincidir.
    expect(FrondaPalette::Brand[600])->toBe('#1a7e42')
        ->and(FrondaPalette::Petrol[600])->toBe('#00384c')
        ->and(FrondaPalette::LogoGreen)->toBe(FrondaPalette::Brand[600])
        ->and(FrondaPalette::LogoPetrol)->toBe(FrondaPalette::Petrol[600]);
});

it('mantiene el CSS y el PHP diciendo exactamente lo mismo', function (): void {
    $css = cssTokenRamps();

    expect($css['brand'])->toBe(FrondaPalette::Brand)
        ->and($css['petrol'])->toBe(FrondaPalette::Petrol);
});

it('define las once tonalidades en orden de claro a oscuro', function (string $ramp): void {
    $shades = constant(FrondaPalette::class.'::'.$ramp);

    expect(array_keys($shades))->toBe([50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950]);

    // Cada paso tiene que ser más oscuro que el anterior: una rampa que se
    // invierte en algún punto rompe cualquier elección de contraste hecha sobre ella.
    $previous = 1.0;

    foreach ($shades as $hex) {
        $luminance = relativeLuminance($hex);
        expect($luminance)->toBeLessThan($previous);
        $previous = $luminance;
    }
})->with(['Brand', 'Petrol']);

it('hace que Filament pinte el botón primario con el verde del logotipo', function (): void {
    // Filament no usa el tono que uno le pasa: elige uno de la rampa según el
    // contraste. Prefiere el par (600, 500), pero lo descarta si sobre el 500
    // cabe un texto oscuro que cumpla AA — y entonces cae al 400, que en verde
    // sale pálido y parece un botón deshabilitado. La rampa está calibrada para
    // que gane el 600; este test es lo que impide que alguien aclare el 500 y
    // deshaga el ajuste sin darse cuenta. Los candidatos replican los que declara
    // Filament\Support\View\Components\ButtonComponent.
    $choice = ButtonComponentColorMap::make(
        array_map(Color::convertToOklch(...), FrondaPalette::Brand)
    )
        ->lightBackground(bg: 600, hover: 500)
        ->lightBackground(bg: 400, hover: 300, alternateHover: 500)
        ->darkBackground(bg: 600, hover: 500, alternateHover: 700)
        ->get();

    expect($choice['bg'])->toBe(600)
        ->and($choice['dark:bg'])->toBe(600);

    // Tono 0 significa blanco: el botón lleva texto claro, no verde sobre verde.
    expect($choice['text'])->toBe(0)
        ->and($choice['dark:text'])->toBe(0);
});

it('cumple WCAG AA en los pares de color que la interfaz usa de verdad', function (string $label, string $foreground, string $background): void {
    expect(contrastRatio($foreground, $background))->toBeGreaterThanOrEqual(4.5, $label);
})->with([
    // Botón primario y su estado hover — texto blanco sobre el verde de marca.
    ['botón primario', '#ffffff', FrondaPalette::Brand[600]],
    ['botón primario en hover', '#ffffff', FrondaPalette::Brand[700]],
    // Enlaces: en claro se oscurece contra blanco, en oscuro se aclara.
    ['enlace en modo claro', FrondaPalette::Brand[700], '#ffffff'],
    ['enlace en modo oscuro', FrondaPalette::Brand[400], FrondaPalette::Petrol[900]],
    // Píldoras de estado de shared/design.js, en sus dos temas.
    ['píldora clara', FrondaPalette::Brand[700], FrondaPalette::Brand[50]],
    ['píldora oscura', FrondaPalette::Brand[300], FrondaPalette::Petrol[950]],
    // Barra lateral y panel de plataforma, sobre superficies petróleo.
    ['barra lateral', '#ffffff', FrondaPalette::Petrol[800]],
    ['barra lateral inactiva', FrondaPalette::Petrol[200], FrondaPalette::Petrol[800]],
    ['primario de plataforma', '#ffffff', FrondaPalette::Petrol[600]],
    // La tarjeta del login en modo oscuro — el par que estaba roto.
    ['título del login en oscuro', '#ffffff', FrondaPalette::Petrol[900]],
    ['subtítulo del login en oscuro', FrondaPalette::Petrol[300], FrondaPalette::Petrol[900]],
    // El desplegable de un <select> nativo en oscuro. Las <option> no heredan el
    // fondo del control —lo pinta el sistema operativo— así que con
    // `dark:bg-white/5`, que es translúcido, quedaba texto blanco sobre el blanco
    // por defecto del popup. Este es el par que lo reemplaza en theme.css.
    ['opción de select en oscuro', FrondaPalette::Petrol[50], FrondaPalette::Petrol[900]],
]);
