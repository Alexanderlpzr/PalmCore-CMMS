<?php

namespace App\Support;

/**
 * Paleta de marca Fronda — fuente única de verdad del lado PHP.
 *
 * Los dos únicos colores de marca son los que existen en el logotipo
 * (public/images/logo.png), muestreados de sus píxeles:
 *
 *   verde  #1A7E42 → la fronda y las letras «CMMS»
 *   petrol #00384C → el engranaje y las letras «FRONDA»
 *
 * Cada rampa está anclada de modo que el paso 600 ES exactamente el color del
 * logo; el resto de los pasos conserva su tono y curva de croma en OKLCH. Los
 * nueve pares de color que la interfaz usa de verdad (botón primario, enlaces,
 * píldoras, barra lateral) cumplen WCAG AA — ver PaletteContrastTest.
 *
 * El espejo CSS de estas mismas rampas vive en resources/css/fronda-tokens.css.
 * Si cambias un valor aquí, cámbialo allí: el test los compara entre sí.
 */
final class FrondaPalette
{
    /**
     * Verde de la fronda. Color primario: acciones, enlaces, estado activo.
     *
     * @var array<int, string>
     */
    public const Brand = [
        50 => '#f0f9f2',
        100 => '#dff1e2',
        200 => '#c1e5c9',
        300 => '#98d1a6',
        400 => '#64b67b',
        // Deliberadamente más oscuro de lo que pediría una rampa regular. Filament
        // elige solo el tono del botón: prefiere el par (600, 500), pero lo descarta
        // si sobre el 500 cabe un texto oscuro que cumpla AA, y entonces cae al 400
        // pálido. Los verdes son perceptualmente claros y caen justo en ese umbral
        // — le pasa también al Emerald del propio Filament. Con el 500 aquí, el
        // botón primario es el verde del logo con texto blanco. Ver BrandPaletteTest.
        500 => '#2e8c4f',
        600 => '#1a7e42',
        700 => '#046732',
        800 => '#065428',
        900 => '#0d4522',
        950 => '#052811',
    ];

    /**
     * Petróleo del engranaje. Color estructural: barras laterales, encabezados,
     * superficies oscuras — y color primario del panel de plataforma, que así se
     * distingue del panel admin sin salirse del logotipo.
     *
     * @var array<int, string>
     */
    public const Petrol = [
        50 => '#eef3f6',
        100 => '#dae5eb',
        200 => '#baced9',
        300 => '#8dadbd',
        400 => '#568297',
        500 => '#2f5f75',
        600 => '#00384c',
        700 => '#002c3e',
        800 => '#002331',
        900 => '#011c27',
        950 => '#000d14',
    ];

    /** Los colores tal cual aparecen en el logotipo, sin rampa. */
    public const LogoGreen = '#1a7e42';

    public const LogoPetrol = '#00384c';
}
