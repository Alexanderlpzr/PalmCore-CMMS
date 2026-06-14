<?php

namespace App\Filament\Resources\Inventory\SparePart\Pages;

use App\Domain\Reports\DTOs\ReportRequest;
use App\Domain\Reports\Enums\ExcelReportType;
use App\Domain\Reports\Enums\ReportType;
use App\Domain\Reports\Excel\ExcelReportManager;
use App\Domain\Reports\Services\ReportManager;
use App\Filament\Resources\Inventory\SparePart\SparePartResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListSpareParts extends ListRecords
{
    protected static string $resource = SparePartResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_excel')
                ->label('Exportar Excel')
                ->icon(Heroicon::OutlinedTableCells)
                ->color('gray')
                ->action(function (ExcelReportManager $manager): void {
                    $manager->dispatch(
                        ExcelReportType::Inventory,
                        Filament::getTenant()->id,
                        auth()->id(),
                    );

                    Notification::make()
                        ->title('Generando reporte de inventario (Excel)')
                        ->body('Recibirás una notificación cuando esté listo para descargar.')
                        ->info()
                        ->send();
                }),

            Action::make('export_pdf')
                ->label('Exportar PDF')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(function (ReportManager $manager): void {
                    $manager->dispatchInventoryReport(new ReportRequest(
                        type: ReportType::Inventory,
                        tenantId: Filament::getTenant()->id,
                        requestedBy: auth()->id(),
                    ));

                    Notification::make()
                        ->title('Generando reporte de inventario')
                        ->body('Recibirás una notificación cuando esté listo para descargar.')
                        ->info()
                        ->send();
                }),

            CreateAction::make(),
        ];
    }
}
