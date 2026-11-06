{{--
    Marca Palmo para la calculadora R-100: letras y colores. Va en el <head> de
    las páginas de /r100. Los colores se cambian redefiniendo las variables de
    Tailwind dentro de .palmo, así las clases brand-* y petrol-* que ya usa la
    página toman la paleta de Palmo sin reescribir el HTML.

    Fruto #E8622C · Racimo #5A1F1B · Hoja #2F8F4E · Aceite #F4B740 · Noche #10291F · Crema #FBF6EC
--}}
<link rel="icon" type="image/svg+xml" href="{{ asset('images/palmo/icono.svg') }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Atkinson+Hyperlegible:wght@400;700&family=Baloo+2:wght@600;700;800&display=swap" rel="stylesheet">
<style>
    .palmo {
        /* Hoja: positivo, «listo», ganancias. El 500 lleva texto blanco: 4,6:1. */
        --color-brand-50: #F1F8F3;
        --color-brand-100: #E3F1E7;
        --color-brand-200: #C9E8D2;
        --color-brand-300: #9FD5AE;
        --color-brand-400: #6BBF84;
        --color-brand-500: #2A8447;
        --color-brand-600: #2F8F4E;
        --color-brand-700: #1E6B3A;
        --color-brand-800: #17532D;
        --color-brand-900: #10291F;
        /* Noche y crema: textos, fondos oscuros y bordes. */
        --color-petrol-50: #FBF6EC;
        --color-petrol-100: #E6DCC8;
        --color-petrol-500: #5C6B63;
        --color-petrol-800: #10291F;
        --color-petrol-900: #10291F;

        background-color: #FBF6EC;
        font-family: 'Atkinson Hyperlegible', ui-sans-serif, system-ui, sans-serif;
    }

    .palmo h1,
    .palmo h2,
    .palmo h3,
    .palmo .palmo-display {
        font-family: 'Baloo 2', ui-sans-serif, system-ui, sans-serif;
    }

    /* Botón principal: Fruto con letra Noche (5,5:1). */
    .palmo .palmo-cta {
        background-color: #E8622C;
        color: #10291F;
    }

    .palmo .palmo-cta:hover {
        background-color: #D4541F;
    }
</style>
