<?php

namespace App\Filament\Resources\Maintenance\WorkOrder\Pages;

use App\Domain\Maintenance\Enums\WorkOrderSignatureType;
use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Domain\Maintenance\Services\WorkOrderService;
use App\Filament\Resources\Maintenance\WorkOrder\WorkOrderResource;
use App\Models\WorkOrder;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewWorkOrder extends ViewRecord
{
    protected static string $resource = WorkOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Plan: Draft → Planned
            Action::make('plan')
                ->label('Planificar')
                ->icon(Heroicon::OutlinedCalendar)
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Planificar OT')
                ->modalDescription('Confirma que los técnicos y fechas están asignados en los tabs de abajo.')
                ->visible(fn (): bool => $this->record->status === WorkOrderStatus::Draft)
                ->action(fn (WorkOrderService $service) => $this->doTransition($service, WorkOrderStatus::Planned)),

            // Start: Planned → InProgress
            Action::make('start')
                ->label('Iniciar trabajo')
                ->icon(Heroicon::OutlinedPlay)
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record->status === WorkOrderStatus::Planned)
                ->action(fn (WorkOrderService $service) => $this->doTransition($service, WorkOrderStatus::InProgress)),

            // Pause: InProgress → OnHold
            Action::make('pause')
                ->label('Pausar')
                ->icon(Heroicon::OutlinedPause)
                ->color('gray')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record->status === WorkOrderStatus::InProgress)
                ->action(fn (WorkOrderService $service) => $this->doTransition($service, WorkOrderStatus::OnHold)),

            // Resume: OnHold → InProgress
            Action::make('resume')
                ->label('Reanudar')
                ->icon(Heroicon::OutlinedPlay)
                ->color('info')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record->status === WorkOrderStatus::OnHold)
                ->action(fn (WorkOrderService $service) => $this->doTransition($service, WorkOrderStatus::InProgress)),

            // Complete: InProgress → Completed
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
                ->visible(fn (): bool => $this->record->status === WorkOrderStatus::InProgress)
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

            // Verify: Completed → Verified
            Action::make('verify')
                ->label('Verificar')
                ->icon(Heroicon::OutlinedCheckBadge)
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Verificar trabajo realizado')
                ->visible(fn (): bool => $this->record->status === WorkOrderStatus::Completed)
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

            // Reject back: Completed → InProgress
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
                ->visible(fn (): bool => $this->record->status === WorkOrderStatus::Completed)
                ->action(fn (array $data, WorkOrderService $service) => $this->doTransition(
                    $service, WorkOrderStatus::InProgress, $data
                )),

            // Close: Verified → Closed
            Action::make('close')
                ->label('Cerrar OT')
                ->icon(Heroicon::OutlinedArchiveBox)
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('¿Cerrar definitivamente?')
                ->modalDescription('Una vez cerrada, la OT no puede modificarse.')
                ->visible(fn (): bool => $this->record->status === WorkOrderStatus::Verified)
                ->action(fn (WorkOrderService $service) => $this->doTransition($service, WorkOrderStatus::Closed)),

            // Cancel
            Action::make('cancel')
                ->label('Cancelar OT')
                ->icon(Heroicon::OutlinedArchiveBoxXMark)
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('¿Cancelar orden de trabajo?')
                ->visible(fn (): bool => ! $this->record->status->isTerminal()
                    && $this->record->status->canTransitionTo(WorkOrderStatus::Cancelled))
                ->action(fn (WorkOrderService $service) => $this->doTransition($service, WorkOrderStatus::Cancelled)),

            EditAction::make()
                ->visible(fn (): bool => $this->record->isEditable()),
            DeleteAction::make(),
        ];
    }

    private function doTransition(
        WorkOrderService $service,
        WorkOrderStatus $toStatus,
        array $extra = [],
    ): void {
        /** @var WorkOrder $wo */
        $wo = $this->record;

        $service->transition($wo, $toStatus, auth()->user(), $extra);
        $this->record->refresh();

        Notification::make()
            ->title('Estado actualizado: '.$toStatus->label())
            ->success()
            ->send();
    }
}
