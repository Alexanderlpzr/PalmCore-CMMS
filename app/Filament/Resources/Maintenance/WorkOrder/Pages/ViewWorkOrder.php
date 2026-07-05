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
            Action::make('plan')
                ->label('Planificar')
                ->icon(Heroicon::OutlinedCalendar)
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Planificar OT')
                ->modalDescription('Confirma que los técnicos y fechas están asignados en los tabs de abajo.')
                ->visible(fn (): bool => $this->record->status === WorkOrderStatus::Draft
                    && auth()->user()->can('work-orders.plan'))
                ->action(fn (WorkOrderService $service) => $this->doTransition($service, WorkOrderStatus::Planned)),

            // Start: Planned → InProgress  (work-orders.execute)
            // No confirmation modal — reversible operational toggle used constantly
            // by técnicos during the day; the confirmation was pure friction.
            Action::make('start')
                ->label('Iniciar trabajo')
                ->icon(Heroicon::OutlinedPlay)
                ->color('warning')
                ->visible(fn (): bool => $this->record->status === WorkOrderStatus::Planned
                    && auth()->user()->can('work-orders.execute'))
                ->action(fn (WorkOrderService $service) => $this->doTransition($service, WorkOrderStatus::InProgress)),

            // Pause: InProgress → OnHold  (work-orders.execute)
            Action::make('pause')
                ->label('Pausar')
                ->icon(Heroicon::OutlinedPause)
                ->color('gray')
                ->visible(fn (): bool => $this->record->status === WorkOrderStatus::InProgress
                    && auth()->user()->can('work-orders.execute'))
                ->action(fn (WorkOrderService $service) => $this->doTransition($service, WorkOrderStatus::OnHold)),

            // Resume: OnHold → InProgress  (work-orders.execute)
            Action::make('resume')
                ->label('Reanudar')
                ->icon(Heroicon::OutlinedPlay)
                ->color('info')
                ->visible(fn (): bool => $this->record->status === WorkOrderStatus::OnHold
                    && auth()->user()->can('work-orders.execute'))
                ->action(fn (WorkOrderService $service) => $this->doTransition($service, WorkOrderStatus::InProgress)),

            // Complete: InProgress → Completed  (work-orders.execute)
            Action::make('complete')
                ->label('Completar')
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
                ])
                ->visible(fn (): bool => $this->record->status === WorkOrderStatus::InProgress
                    && auth()->user()->can('work-orders.execute'))
                ->action(function (array $data, WorkOrderService $service): void {
                    $this->doTransition($service, WorkOrderStatus::Completed, $data);

                    // Auto-create technician signature
                    $service->addSignature(
                        $this->record,
                        auth()->user(),
                        WorkOrderSignatureType::TechnicianCompletion,
                        $data['work_performed'] ?? null
                    );
                }),

            // Verify: Completed → Verified  (work-orders.verify — ingeniero/supervisor)
            Action::make('verify')
                ->label('Verificar')
                ->icon(Heroicon::OutlinedCheckBadge)
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Verificar trabajo realizado')
                ->visible(fn (): bool => $this->record->status === WorkOrderStatus::Completed
                    && auth()->user()->can('work-orders.verify'))
                ->action(function (WorkOrderService $service): void {
                    $this->doTransition($service, WorkOrderStatus::Verified);

                    // Auto-create supervisor signature
                    $service->addSignature(
                        $this->record,
                        auth()->user(),
                        WorkOrderSignatureType::SupervisorVerification
                    );

                    // Recalculate costs before closing
                    $service->recalculateCosts($this->record);
                }),

            // Reject back: Completed → InProgress  (work-orders.verify)
            Action::make('reject_completion')
                ->label('Rechazar (volver a ejecución)')
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
                ->icon(Heroicon::OutlinedArchiveBoxXMark)
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('¿Cancelar orden de trabajo?')
                ->visible(fn (): bool => ! $this->record->status->isTerminal()
                    && $this->record->status->canTransitionTo(WorkOrderStatus::Cancelled)
                    && auth()->user()->can('work-orders.update'))
                ->action(fn (WorkOrderService $service) => $this->doTransition($service, WorkOrderStatus::Cancelled)),

            Action::make('download_pdf')
                ->label('Descargar PDF')
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
                ->visible(fn (): bool => $this->record->isEditable()),
            DeleteAction::make(),
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
