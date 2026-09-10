<?php

namespace App\Filament\Resources\Downtime\Pages;

use App\Domain\Reports\Services\ParadasPdfService;
use App\Filament\Resources\Downtime\DowntimeEventResource;
use App\Models\Plant;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListDowntimeEvents extends ListRecords
{
    protected static string $resource = DowntimeEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->descargarPdfAction(),
            CreateAction::make()->label('Registrar paro'),
        ];
    }

    /**
     * El PDF de lo que la pantalla está mostrando ahora mismo.
     *
     * Va sobre `getFilteredTableQuery()` —la consulta con los filtros ya aplicados— y no
     * sobre un rango de fechas. De ahí salen tanto el listado como los gráficos, así que el
     * documento no puede contradecirse: filtrado por una sección, las tortas son de esa
     * sección y no de toda la planta.
     *
     * Y viaja con los filtros escritos dentro, leídos de la propia tabla. Un informe de un
     * subconjunto que no dice cuál es un informe que engaña.
     */
    private function descargarPdfAction(): Action
    {
        return Action::make('descargarParos')
            ->label('Descargar PDF')
            ->tooltip('Los paros que muestra el filtro, con sus gráficos')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->action(function (ParadasPdfService $informe): ?StreamedResponse {
                $query = $this->getFilteredTableQuery();

                if ($query === null) {
                    return null;
                }

                $filtros = collect($this->getTable()->getFilterIndicators())
                    ->map(fn ($indicador): string => (string) $indicador->getLabel())
                    ->all();

                $bytes = $informe->generate($this->plantaActual(), $query, $filtros);

                return response()->streamDownload(
                    fn () => print ($bytes),
                    $informe->filename(),
                    ['Content-Type' => 'application/pdf'],
                );
            });
    }

    /**
     * La planta del tenant, solo para encabezar el documento.
     *
     * Los paros ya vienen acotados por la consulta de la tabla, que respeta el tenant; esto
     * es únicamente el nombre que va en la cabecera.
     */
    private function plantaActual(): ?Plant
    {
        return Plant::where('tenant_id', Filament::getTenant()->id)->orderBy('name')->first();
    }
}
