{{--
    Una barra fina arriba mientras se abre otra página del panel: las que calculan el
    corte tardan un par de segundos en el servidor, y sin aviso parece que el clic no
    hizo nada. Aparece solo si la espera pasa de un instante, y no se mueve para quien
    pidió menos movimiento en su sistema.
--}}
<div id="fronda-barra-carga" aria-hidden="true" class="pointer-events-none fixed inset-x-0 top-0 z-[100] h-0.5 overflow-hidden opacity-0 transition-opacity duration-300">
    <div class="fronda-barra-carga-avance h-full w-1/3 rounded-full bg-primary-500"></div>
</div>

<style>
    @keyframes fronda-barra-carga {
        0% { transform: translateX(-100%); }
        100% { transform: translateX(300%); }
    }

    .fronda-barra-carga-avance {
        animation: fronda-barra-carga 1.1s ease-in-out infinite;
    }

    @media (prefers-reduced-motion: reduce) {
        .fronda-barra-carga-avance { animation: none; width: 100%; }
    }
</style>

<script>
    (() => {
        const barra = document.getElementById('fronda-barra-carga');
        let espera = null;
        let tope = null;

        const mostrar = () => {
            clearTimeout(espera);
            espera = setTimeout(() => barra.classList.replace('opacity-0', 'opacity-100'), 150);
            // Por si el enlace era una descarga y la página no se va: se apaga sola.
            clearTimeout(tope);
            tope = setTimeout(ocultar, 15000);
        };

        const ocultar = () => {
            clearTimeout(espera);
            clearTimeout(tope);
            barra.classList.replace('opacity-100', 'opacity-0');
        };

        document.addEventListener('click', (evento) => {
            const enlace = evento.target.closest('a[href]');

            if (! enlace || evento.defaultPrevented || evento.button !== 0 || evento.metaKey || evento.ctrlKey || evento.shiftKey || evento.altKey) {
                return;
            }

            if ((enlace.target && enlace.target !== '_self') || enlace.hasAttribute('download')) {
                return;
            }

            const destino = new URL(enlace.href, location.href);

            // Solo las páginas del mismo sitio; un ancla de la misma página no carga nada.
            if (destino.origin !== location.origin || (destino.pathname === location.pathname && destino.search === location.search)) {
                return;
            }

            mostrar();
        });

        // Al volver con «atrás», la página sale de la memoria del navegador: sin barra.
        window.addEventListener('pageshow', ocultar);
    })();
</script>
