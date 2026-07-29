<?php

namespace App\Filament\Resources\Maintenance\WorkOrder\Pages;

use App\Domain\Reports\DTOs\ReportRequest;
use App\Domain\Reports\Enums\ExcelReportType;
use App\Domain\Reports\Enums\ReportType;
use App\Domain\Reports\Excel\ExcelReportManager;
use App\Domain\Reports\Services\ReportManager;
use App\Filament\Resources\Maintenance\WorkOrder\WorkOrderResource;
use App\Models\WorkOrder;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListWorkOrders extends ListRecords
{
    protected static string $resource = WorkOrderResource::class;

    /**
     * Abiertas (por defecto) vs Histórico (cerradas/canceladas): antes convivían
     * en la misma tabla y se hacía difícil distinguir el trabajo pendiente del ya
     * resuelto — sobre todo en la exportación, donde el histórico ensuciaba el
     * PDF de pendientes. «PDF de Pendientes» ya filtraba solo abiertas por su
     * cuenta; esto es lo mismo pero visible en pantalla.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'abiertas' => Tab::make('Abiertas')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->open())
                ->badge(fn (): int => WorkOrder::query()->open()->count()),
            'historico' => Tab::make('Histórico')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->historical())
                ->badge(fn (): int => WorkOrder::query()->historical()->count()),
        ];
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

            Action::make('download_pending_pdf')
                ->label('PDF de Pendientes')
                ->tooltip('Descarga un PDF con todas las OT que aún no están completadas')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(function (ReportManager $manager): mixed {
                    return $manager->streamDownload(new ReportRequest(
                        type: ReportType::PendingWorkOrders,
                        tenantId: Filament::getTenant()->id,
                        requestedBy: auth()->id(),
                    ));
                }),

            CreateAction::make(),
        ];
    }
}
