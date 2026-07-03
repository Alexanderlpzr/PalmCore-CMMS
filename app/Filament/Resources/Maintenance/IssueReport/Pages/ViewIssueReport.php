<?php

namespace App\Filament\Resources\Maintenance\IssueReport\Pages;

use App\Domain\Maintenance\Enums\IssueReportStatus;
use App\Domain\Maintenance\Enums\MaintenanceRequestPriority;
use App\Domain\Maintenance\Enums\MaintenanceRequestType;
use App\Domain\Maintenance\Services\MaintenanceRequestService;
use App\Filament\Resources\Concerns\HasBackAction;
use App\Filament\Resources\Maintenance\IssueReport\IssueReportResource;
use App\Models\EquipmentIssueReport;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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

            Action::make('convert_to_mr')
                ->label('Convertir a Solicitud')
                ->icon(Heroicon::OutlinedArrowRightCircle)
                ->color('success')
                ->modalHeading('Crear Solicitud de Mantenimiento')
                ->modalWidth('xl')
                ->visible(fn (): bool => $this->record->status !== IssueReportStatus::ConvertedToMR)
                ->form([
                    TextInput::make('title')
                        ->label('Título')
                        ->required()
                        ->maxLength(255)
                        ->default(fn (): string => 'Mantenimiento — '.$this->record->equipment->code),
                    Select::make('request_type')
                        ->label('Tipo')
                        ->options(MaintenanceRequestType::options())
                        ->required()
                        ->default(MaintenanceRequestType::Corrective->value),
                    Select::make('priority')
                        ->label('Prioridad')
                        ->options(MaintenanceRequestPriority::options())
                        ->required()
                        ->default(MaintenanceRequestPriority::P3Medium->value),
                    DatePicker::make('requested_due_date')
                        ->label('Fecha límite solicitada')
                        ->displayFormat('d/m/Y'),
                    Textarea::make('description')
                        ->label('Descripción')
                        ->required()
                        ->rows(4)
                        ->default(fn (): string => $this->record->description),
                ])
                ->action(function (array $data, MaintenanceRequestService $service): void {
                    /** @var EquipmentIssueReport $report */
                    $report = $this->record;

                    $service->createFromIssueReport($report, $data, auth()->user());
                    $this->record->refresh();

                    Notification::make()->title('Solicitud de mantenimiento creada')->success()->send();
                }),

            $this->getBackAction(),
        ];
    }
}
