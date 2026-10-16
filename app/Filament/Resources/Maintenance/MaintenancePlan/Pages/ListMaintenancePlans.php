<?php

namespace App\Filament\Resources\Maintenance\MaintenancePlan\Pages;

use App\Domain\Reports\Enums\ExcelReportType;
use App\Domain\Reports\Excel\ExcelReportManager;
use App\Filament\Resources\Maintenance\MaintenancePlan\MaintenancePlanResource;
use App\Filament\Widgets\Lists\MaintenancePlanKpis;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListMaintenancePlans extends ListRecords
{
    protected static string $resource = MaintenancePlanResource::class;

    /** Lo que dice la tabla en cuatro tarjetas; cada una filtra la lista al tocarla. */
    protected function getHeaderWidgets(): array
    {
        return [MaintenancePlanKpis::class];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_excel')
                ->label('Exportar Excel')
                ->icon(Heroicon::OutlinedTableCells)
                ->color('gray')
                ->action(function (ExcelReportManager $manager): void {
                    $manager->dispatch(
                        ExcelReportType::MaintenancePlans,
                        Filament::getTenant()->id,
                        auth()->id(),
                    );

                    Notification::make()
                        ->title('Generando reporte de planes de mantenimiento (Excel)')
                        ->body('Recibirás una notificación cuando esté listo para descargar.')
                        ->info()
                        ->send();
                }),

            CreateAction::make(),
        ];
    }
}
