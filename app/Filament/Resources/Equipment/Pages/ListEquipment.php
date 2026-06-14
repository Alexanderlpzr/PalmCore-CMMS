<?php

namespace App\Filament\Resources\Equipment\Pages;

use App\Domain\Reports\Enums\ExcelReportType;
use App\Domain\Reports\Excel\ExcelReportManager;
use App\Filament\Resources\Equipment\EquipmentResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListEquipment extends ListRecords
{
    protected static string $resource = EquipmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_reliability_excel')
                ->label('Exportar Confiabilidad')
                ->icon(Heroicon::OutlinedTableCells)
                ->color('gray')
                ->action(function (ExcelReportManager $manager): void {
                    $manager->dispatch(
                        ExcelReportType::Reliability,
                        Filament::getTenant()->id,
                        auth()->id(),
                    );

                    Notification::make()
                        ->title('Generando reporte de confiabilidad (Excel)')
                        ->body('Recibirás una notificación cuando esté listo para descargar.')
                        ->info()
                        ->send();
                }),

            Action::make('export_downtime_excel')
                ->label('Exportar Paradas')
                ->icon(Heroicon::OutlinedTableCells)
                ->color('gray')
                ->action(function (ExcelReportManager $manager): void {
                    $manager->dispatch(
                        ExcelReportType::DowntimeEvents,
                        Filament::getTenant()->id,
                        auth()->id(),
                    );

                    Notification::make()
                        ->title('Generando reporte de paradas (Excel)')
                        ->body('Recibirás una notificación cuando esté listo para descargar.')
                        ->info()
                        ->send();
                }),

            CreateAction::make(),
        ];
    }
}
