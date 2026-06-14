<?php

namespace App\Filament\Resources\Maintenance\WorkOrder\Pages;

use App\Domain\Reports\Enums\ExcelReportType;
use App\Domain\Reports\Excel\ExcelReportManager;
use App\Filament\Resources\Maintenance\WorkOrder\WorkOrderResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListWorkOrders extends ListRecords
{
    protected static string $resource = WorkOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_excel')
                ->label('Exportar Excel')
                ->icon(Heroicon::OutlinedTableCells)
                ->color('gray')
                ->action(function (ExcelReportManager $manager): void {
                    $manager->dispatch(
                        ExcelReportType::WorkOrders,
                        Filament::getTenant()->id,
                        auth()->id(),
                    );

                    Notification::make()
                        ->title('Generando reporte de órdenes de trabajo (Excel)')
                        ->body('Recibirás una notificación cuando esté listo para descargar.')
                        ->info()
                        ->send();
                }),

            CreateAction::make(),
        ];
    }
}
