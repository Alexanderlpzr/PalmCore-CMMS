<?php

namespace App\Filament\Resources\Maintenance\WorkOrder\Pages;

use App\Domain\Maintenance\Enums\WorkOrderSignatureType;
use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Domain\Maintenance\Services\WorkOrderService;
use App\Domain\Reports\DTOs\ReportRequest;
use App\Domain\Reports\Enums\ReportType;
use App\Domain\Reports\Services\ReportManager;
use App\Filament\Resources\Concerns\HasBackAction;
use App\Filament\Resources\Maintenance\WorkOrder\WorkOrderResource;
use App\Models\WorkOrder;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewWorkOrder extends ViewRecord
{
    use HasBackAction;

    protected static string $resource = WorkOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Plan: Draft → Planned  (work-orders.plan)
            // No confirmation modal — WorkOrderService already blocks this
            // transition with a clear error if no technician is assigned yet,
            // so a "are you sure?" step here would just repeat that check.
            Action::make('plan')
                ->label('Planificar')
                ->tooltip('Confirma técnicos y fechas para dejar la OT lista para iniciar')
                ->icon(Heroicon::OutlinedCalendar)
                ->color('info')
                ->visible(fn (): bool => $this->record->status === WorkOrderStatus::Draft
                    && auth()->user()->can('work-orders.plan'))
                ->action(fn (WorkOrderService $service) => $this->doTransition($service, WorkOrderStatus::Planned)),

            // Start: Planned → InProgress  (work-orders.execute)
            // No confirmation modal — reversible operational toggle used constantly
            // by técnicos during the day; the confirmation was pure friction.
            Action::make('start')
                ->label('Iniciar trabajo')
                ->tooltip('Empieza la ejecución de la OT y comienza a contar el tiempo trabajado')
                ->icon(Heroicon::OutlinedPlay)
                ->color('warning')
                ->visible(fn (): bool => $this->record->status === WorkOrderStatus::Planned
                    && auth()->user()->can('work-orders.execute'))
                ->action(fn (WorkOrderService $service) => $this->doTransition($service, WorkOrderStatus::InProgress)),

            // Pause: InProgress → OnHold  (work-orders.execute)
            Action::make('pause')
                ->label('Pausar')
                ->tooltip('Detiene el conteo de tiempo mientras la OT queda en espera')
                ->icon(Heroicon::OutlinedPause)
                ->color('gray')
                ->visible(fn (): bool => $this->record->status === WorkOrderStatus::InProgress
                    && auth()->user()->can('work-orders.execute'))
                ->action(fn (WorkOrderService $service) => $this->doTransition($service, WorkOrderStatus::OnHold)),

            // Resume: OnHold → InProgress  (work-orders.execute)
            Action::make('resume')
                ->label('Reanudar')
                ->tooltip('Retoma la ejecución de la OT desde donde quedó')
                ->icon(Heroicon::OutlinedPlay)
                ->color('info')
                ->visible(fn (): bool => $this->record->status === WorkOrderStatus::OnHold
                    && auth()->user()->can('work-orders.execute'))
                ->action(fn (WorkOrderService $service) => $this->doTransition($service, WorkOrderStatus::InProgress)),

            // Complete: InProgress → Completed  (work-orders.execute)
            Action::make('complete')
                ->label('Completar')
                ->tooltip('Registra el trabajo realizado y firma para cerrar la ejecución')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->modalHeading('Completar OT')
                ->form([
                    Textarea::make('work_performed')
                        ->label('Trabajo realizado')
                        ->required()
                        ->rows(4),
                    Textarea::make('failure_cause')
                        ->label('Causa de la falla (si aplica)')
                        ->rows(3),
                    Textarea::make('root_cause')
                        ->label('Causa raíz (si aplica)')
                        ->rows(3),
                    ViewField::make('signature')
                        ->label('Firma digital del técnico')
                        ->view('filament.forms.signature-pad')
                        ->required()
                        ->columnSpanFull(),
                ])
                ->visible(fn (): bool => $this->record->status === WorkOrderStatus::InProgress
                    && auth()->user()->can('work-orders.execute'))
                ->action(function (array $data, WorkOrderService $service): void {
                    $signature = $data['signature'] ?? null;
                    unset($data['signature']);

                    $this->doTransition($service, WorkOrderStatus::Completed, $data);

                    // Real drawn signature captured on-screen at the moment of completion.
                    $service->addSignature(
                        $this->record,
                        auth()->user(),
                        WorkOrderSignatureType::TechnicianCompletion,
                        $data['work_performed'] ?? null,
                        null,
                        $signature,
                    );
                }),

            // Verify: Completed → Verified  (work-orders.verify — ingeniero/supervisor)
            Action::make('verify')
                ->label('Verificar')
                ->tooltip('Como supervisor: confirma que el trabajo quedó bien hecho y firma')
                ->icon(Heroicon::OutlinedCheckBadge)
                ->color('success')
                ->modalHeading('Verificar trabajo realizado')
                ->form([
                    ViewField::make('signature')
                        ->label('Firma digital del supervisor')
                        ->view('filament.forms.signature-pad')
                        ->required()
                        ->columnSpanFull(),
                ])
                ->visible(fn (): bool => $this->record->status === WorkOrderStatus::Completed
                    && auth()->user()->can('work-orders.verify'))
                ->action(function (array $data, WorkOrderService $service): void {
                    $this->doTransition($service, WorkOrderStatus::Verified);

                    // Real drawn signature captured on-screen at the moment of verification.
                    $service->addSignature(
                        $this->record,
                        auth()->user(),
                        WorkOrderSignatureType::SupervisorVerification,
                        null,
                        null,
                        $data['signature'] ?? null,
                    );

                    // Recalculate costs before closing
                    $service->recalculateCosts($this->record);
                }),

            // Reject back: Completed → InProgress  (work-orders.verify)
            Action::make('reject_completion')
                ->label('Rechazar (volver a ejecución)')
                ->tooltip('El trabajo no quedó conforme — la OT vuelve a "En ejecución"')
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->color('danger')
                ->modalHeading('Rechazar trabajo')
                ->form([
                    Textarea::make('rejection_reason')
                        ->label('Motivo del rechazo')
                        ->required()
                        ->rows(3),
                ])
                ->visible(fn (): bool => $this->record->status === WorkOrderStatus::Completed
                    && auth()->user()->can('work-orders.verify'))
                ->action(fn (array $data, WorkOrderService $service) => $this->doTransition(
                    $service, WorkOrderStatus::InProgress, $data
                )),

            // Close: Verified → Closed  (work-orders.close)
            Action::make('close')
                ->label('Cerrar OT')
                ->tooltip('Cierra la OT de forma definitiva — ya no podrá modificarse')
                ->icon(Heroicon::OutlinedArchiveBox)
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('¿Cerrar definitivamente?')
                ->modalDescription('Una vez cerrada, la OT no puede modificarse.')
                ->visible(fn (): bool => $this->record->status === WorkOrderStatus::Verified
                    && auth()->user()->can('work-orders.close'))
                ->action(fn (WorkOrderService $service) => $this->doTransition($service, WorkOrderStatus::Closed)),

            // Cancel  (work-orders.update)
            Action::make('cancel')
                ->label('Cancelar OT')
                ->tooltip('Cancela la orden de trabajo sin completarla')
                ->icon(Heroicon::OutlinedArchiveBoxXMark)
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('¿Cancelar orden de trabajo?')
                ->modalDescription('La OT quedará cancelada. Esta acción no se puede deshacer.')
                ->visible(fn (): bool => ! $this->record->status->isTerminal()
                    && $this->record->status->canTransitionTo(WorkOrderStatus::Cancelled)
                    && auth()->user()->can('work-orders.update'))
                ->action(fn (WorkOrderService $service) => $this->doTransition($service, WorkOrderStatus::Cancelled)),

            Action::make('download_pdf')
                ->label('Descargar PDF')
                ->tooltip('Descarga el reporte de esta OT en PDF')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(function (ReportManager $manager): mixed {
                    /** @var WorkOrder $wo */
                    $wo = $this->record;

                    return $manager->streamDownload(new ReportRequest(
                        type: ReportType::WorkOrder,
                        tenantId: Filament::getTenant()->id,
                        requestedBy: auth()->id(),
                        recordId: $wo->id,
                    ));
                }),

            EditAction::make()
                ->tooltip('Editar los datos de la OT')
                ->visible(fn (): bool => $this->record->isEditable()),
            DeleteAction::make()
                ->tooltip('Eliminar esta orden de trabajo'),
            $this->getBackAction(),
        ];
    }

    private function doTransition(
        WorkOrderService $service,
        WorkOrderStatus $toStatus,
        array $extra = [],
    ): void {
        /** @var WorkOrder $wo */
        $wo = $this->record;

        try {
            $service->transition($wo, $toStatus, auth()->user(), $extra);
        } catch (\RuntimeException $e) {
            Notification::make()
                ->title($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->record->refresh();

        Notification::make()
            ->title('Estado actualizado: '.$toStatus->label())
            ->success()
            ->send();
    }
}
