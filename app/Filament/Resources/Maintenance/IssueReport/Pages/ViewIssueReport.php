<?php

namespace App\Filament\Resources\Maintenance\IssueReport\Pages;

use App\Domain\Maintenance\Enums\IssueReportStatus;
use App\Domain\Maintenance\Enums\MaintenanceArea;
use App\Domain\Maintenance\Enums\PlantProcess;
use App\Domain\Maintenance\Enums\WorkOrderPriority;
use App\Domain\Maintenance\Enums\WorkOrderType;
use App\Domain\Maintenance\Services\WorkOrderService;
use App\Filament\Resources\Concerns\HasBackAction;
use App\Filament\Resources\Maintenance\IssueReport\IssueReportResource;
use App\Models\EquipmentIssueReport;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewIssueReport extends ViewRecord
{
    use HasBackAction;

    protected static string $resource = IssueReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('acknowledge')
                ->label('Reconocer')
                ->tooltip('Marca que ya revisaste este reporte y lo estás atendiendo')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Reconocer reporte')
                ->modalDescription('Indica que el reporte fue revisado y está siendo atendido.')
                ->visible(fn (): bool => $this->record->status === IssueReportStatus::Open)
                ->action(function (): void {
                    /** @var EquipmentIssueReport $report */
                    $report = $this->record;
                    $report->acknowledge(auth()->user());
                    $this->record->refresh();

                    Notification::make()->title('Reporte reconocido')->success()->send();
                }),

            Action::make('create_wo')
                ->label('Crear OT')
                ->tooltip('Genera una orden de trabajo a partir de este reporte, ligada para la trazabilidad')
                ->icon(Heroicon::OutlinedWrenchScrewdriver)
                ->color('success')
                ->modalHeading('Crear orden de trabajo')
                ->modalWidth('xl')
                ->visible(fn (): bool => in_array($this->record->status, [
                    IssueReportStatus::Open,
                    IssueReportStatus::Acknowledged,
                ], true))
                // Mismos campos que «Crear Orden de Trabajo». Solo falta elegir
                // Sección y Equipo, que ya vienen del reporte. Antes el modal
                // pedía cuatro datos y dejaba la OT sin responsable ni clase de
                // mantenimiento, que había que completar entrando a editarla.
                ->form([
                    Select::make('work_order_type')
                        ->label('Tipo')
                        ->options(WorkOrderType::options())
                        ->required()
                        ->default(WorkOrderType::Corrective->value),
                    Select::make('priority')
                        ->label('Prioridad')
                        ->options(WorkOrderPriority::options())
                        ->required()
                        ->default(WorkOrderPriority::P3Medium->value),
                    Select::make('process')
                        ->label('Proceso')
                        ->options(PlantProcess::options())
                        ->native(false)
                        ->searchable(),
                    Select::make('maintenance_area')
                        ->label('Clase de mantenimiento')
                        ->options(MaintenanceArea::options())
                        ->native(false),
                    TextInput::make('executed_by')
                        ->label('Responsable(s)')
                        ->helperText('Quién hizo el trabajo — la cuadrilla (ej: «El mecánico y su auxiliar», «Fernando A.»).')
                        ->maxLength(255),
                    TextInput::make('meter_reading')
                        ->label('Horómetro al hacer el trabajo')
                        ->numeric()
                        ->minValue(0)
                        ->suffix('h'),
                    TextInput::make('title')
                        ->label('Título')
                        ->required()
                        ->maxLength(255)
                        ->default(fn (): string => 'Mantenimiento — '.$this->record->equipment->code),
                    Textarea::make('description')
                        ->label('Descripción')
                        ->required()
                        ->rows(4)
                        ->default(fn (): string => $this->record->description),
                    Textarea::make('instructions')
                        ->label('Instrucciones de trabajo')
                        ->rows(4),
                    Toggle::make('equipment_stopped')
                        ->label('Equipo detenido')
                        ->default(false),
                    FileUpload::make('before_photos')
                        ->label('Fotos del antes')
                        ->helperText('Cómo se encontró el equipo. Puede subir varias; sirven de referencia para comparar con las del después al cerrar la OT.')
                        ->image()
                        ->imageEditor()
                        ->multiple()
                        ->reorderable()
                        ->appendFiles()
                        ->maxFiles(10)
                        ->disk(persistent_disk())
                        ->visibility(persistent_disk() === 'public' ? 'public' : 'private')
                        ->directory('work-order-photos')
                        ->maxSize(10240),
                ])
                ->action(function (array $data, WorkOrderService $service): void {
                    /** @var EquipmentIssueReport $report */
                    $report = $this->record;

                    $workOrder = $service->createFromIssueReport($report, $data, auth()->user());
                    $this->record->refresh();

                    Notification::make()
                        ->title("Orden de trabajo {$workOrder->work_order_number} creada")
                        ->success()
                        ->send();
                }),

            DeleteAction::make()
                ->label('Eliminar')
                ->tooltip('Eliminar este reporte de novedad')
                ->modalHeading('Eliminar reporte')
                ->modalDescription('El reporte dejará de aparecer en el listado. Puedes recuperarlo luego con el filtro "Papelera".')
                ->visible(fn (): bool => $this->record->status !== IssueReportStatus::Open),

            $this->getBackAction(),
        ];
    }
}
