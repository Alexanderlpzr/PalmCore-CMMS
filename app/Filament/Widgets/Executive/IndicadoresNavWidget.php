<?php

namespace App\Filament\Widgets\Executive;

use App\Filament\Pages\ConsumoDeEnergia;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Presupuesto;
use App\Filament\Pages\ProductividadYEficiencia;
use Filament\Widgets\Widget;

/**
 * La barra de pestañas de los indicadores: las del Dashboard —Producción, Paros,
 * Confiabilidad, Costos— y, al lado, Eficiencia, Presupuesto y Energía, que salieron del
 * menú y se abren desde aquí (2026-11-19). Va arriba de esas tres pantallas; en el
 * Dashboard la pinta {@see DashboardSummaryWidget}, con sus pestañas sin recargar.
 */
class IndicadoresNavWidget extends Widget
{
    protected string $view = 'filament.widgets.indicadores-nav';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -20;

    protected static bool $isLazy = false;

    /** La pestaña de esta pantalla: eficiencia, presupuesto o energia. */
    public string $activo = '';

    /**
     * Las pantallas de indicadores que esta persona puede abrir, aparte del Dashboard.
     *
     * @return array<string, array{label: string, url: string}>
     */
    public static function extras(): array
    {
        $paginas = [
            'eficiencia' => ['Eficiencia', ProductividadYEficiencia::class],
            'presupuesto' => ['Presupuesto', Presupuesto::class],
            'energia' => ['Energía', ConsumoDeEnergia::class],
        ];

        $extras = [];

        foreach ($paginas as $clave => [$label, $pagina]) {
            if ($pagina::canAccess()) {
                $extras[$clave] = ['label' => $label, 'url' => $pagina::getUrl()];
            }
        }

        return $extras;
    }

    /** @return array<string, array{label: string, url: string}> */
    public function pestanas(): array
    {
        $dashboard = [];

        if (Dashboard::canAccess()) {
            foreach (Dashboard::SECTIONS as $clave => $label) {
                $dashboard[$clave] = ['label' => $label, 'url' => Dashboard::getUrl(['ver' => $clave])];
            }
        }

        return [...$dashboard, ...self::extras()];
    }
}
