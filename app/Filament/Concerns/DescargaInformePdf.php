<?php

namespace App\Filament\Concerns;

use App\Domain\Analytics\Support\DashboardPeriod;
use App\Domain\Reports\Contracts\PeriodReport;
use App\Models\Plant;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * El botón «Descargar PDF» de las pantallas de Indicadores.
 *
 * Las cuatro hacen lo mismo: resolver la planta y el período del filtro que el usuario ya
 * tiene puesto, y volcarlo al informe. Lo único que cambia es qué servicio lo genera, así
 * que se escribe una vez.
 *
 * El período sale de {@see DashboardPeriod::snapshotWindow()}, la misma función que usan
 * los widgets de la pantalla. No es una comodidad: si el informe resolviera el filtro por
 * su cuenta, un día diría un período distinto del que se está mirando, y ese fallo exacto
 * ya ocurrió una vez con el rótulo de la pantalla.
 */
trait DescargaInformePdf
{
    /**
     * @param  class-string<PeriodReport>  $informe
     */
    protected function descargarInformeAction(string $informe, string $tooltip): Action
    {
        return Action::make('descargarInforme')
            ->label('Descargar PDF')
            ->tooltip($tooltip)
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->action(function () use ($informe): ?StreamedResponse {
                $planta = $this->plantaDelInforme();

                if ($planta === null) {
                    Notification::make()
                        ->title('Elige una planta primero')
                        ->body('El informe se emite por planta.')
                        ->warning()
                        ->send();

                    return null;
                }

                [$desde, $hasta] = DashboardPeriod::snapshotWindow($this->filters);

                $servicio = app($informe);
                $bytes = $servicio->generate($planta, $desde, $hasta);

                return response()->streamDownload(
                    fn () => print ($bytes),
                    $servicio->filename($planta, $desde, $hasta),
                    ['Content-Type' => 'application/pdf'],
                );
            });
    }

    /**
     * La planta del filtro, resuelta dentro del tenant activo.
     *
     * Se busca entre las del tenant y no por el id a secas: aceptar el que llegue del
     * navegador dejaría emitir un informe con los datos de la planta de otro.
     */
    protected function plantaDelInforme(): ?Plant
    {
        $id = $this->filters['plant_id'] ?? null;

        if (blank($id)) {
            return null;
        }

        return Plant::where('tenant_id', Filament::getTenant()->id)->find($id);
    }
}
