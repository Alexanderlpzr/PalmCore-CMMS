<?php

namespace App\Filament\Resources\Maintenance\MaintenanceRequest\Pages;

use App\Domain\Maintenance\Enums\MaintenanceRequestStatus;
use App\Domain\Maintenance\Enums\WorkOrderType;
use App\Domain\Maintenance\Services\MaintenanceRequestService;
use App\Domain\Maintenance\Services\WorkOrderService;
use App\Filament\Resources\Concerns\HasBackAction;
use App\Filament\Resources\Maintenance\MaintenanceRequest\MaintenanceRequestResource;
use App\Filament\Resources\Maintenance\WorkOrder\WorkOrderResource;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewMaintenanceRequest extends ViewRecord
{
    use HasBackAction;

    protected static string $resource = MaintenanceRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Submit: Draft → Submitted
            Action::make('submit')
                ->label('Enviar para revisión')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->color('info')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record->status === MaintenanceRequestStatus::Draft)
                ->action(fn (MaintenanceRequestService $service): MaintenanceRequest => $this->transitionAndRefresh(
                    $service, MaintenanceRequestStatus::Submitted
                )),

            // Assign to review: Submitted → UnderReview
            Action::make('assign_reviewer')
                ->label('Tomar para revisión')
                ->icon(Heroicon::OutlinedEye)
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record->status === MaintenanceRequestStatus::Submitted)
                ->action(fn (MaintenanceRequestService $service): MaintenanceRequest => $this->transitionAndRefresh(
                    $service, MaintenanceRequestStatus::UnderReview
                )),

            // Approve: UnderReview → Approved
            Action::make('approve')
                ->label('Aprobar')
                ->icon(Heroicon::OutlinedCheckBadge)
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('¿Aprobar solicitud?')
                ->visible(fn (): bool => $this->record->status === MaintenanceRequestStatus::UnderReview)
                ->action(fn (MaintenanceRequestService $service): MaintenanceRequest => $this->transitionAndRefresh(
                    $service, MaintenanceRequestStatus::Approved
                )),

            // Reject: UnderReview → Rejected (requires reason)
            Action::make('reject')
                ->label('Rechazar')
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->modalHeading('Rechazar solicitud')
                ->form([
                    Textarea::make('rejection_reason')
                        ->label('Motivo del rechazo')
                        ->required()
                        ->rows(3),
                ])
                ->visible(fn (): bool => $this->record->status === MaintenanceRequestStatus::UnderReview)
                ->action(fn (array $data, MaintenanceRequestService $service): MaintenanceRequest => $this->transitionAndRefresh(
                    $service,
                    MaintenanceRequestStatus::Rejected,
                    ['rejection_reason' => $data['rejection_reason']]
                )),

            // Resubmit: Rejected → Submitted
            Action::make('resubmit')
                ->label('Reenviar')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('info')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record->status === MaintenanceRequestStatus::Rejected)
                ->action(fn (MaintenanceRequestService $service): MaintenanceRequest => $this->transitionAndRefresh(
                    $service, MaintenanceRequestStatus::Submitted
                )),

            // Cancel
            Action::make('cancel')
                ->label('Cancelar')
                ->icon(Heroicon::OutlinedArchiveBoxXMark)
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('¿Cancelar solicitud?')
                ->modalDescription('La solicitud quedará cancelada. Esta acción no se puede deshacer.')
                ->visible(fn (): bool => ! $this->record->status->isTerminal()
                    && in_array(
                        MaintenanceRequestStatus::Cancelled,
                        $this->record->status->allowedTransitions(),
                        strict: true
                    ))
                ->action(fn (MaintenanceRequestService $service): MaintenanceRequest => $this->transitionAndRefresh(
                    $service, MaintenanceRequestStatus::Cancelled
                )),

            // Assign preliminary technician (visible from UnderReview until conversion)
            Action::make('assign_technician')
                ->label('Asignar técnico')
                ->icon(Heroicon::OutlinedUserPlus)
                ->color('info')
                ->modalHeading('Asignar técnico preliminar')
                ->modalDescription('Selecciona el técnico que ejecutará el trabajo. Puedes cambiar esto en cualquier momento antes de crear la OT.')
                ->visible(fn (): bool => in_array(
                    $this->record->status,
                    [MaintenanceRequestStatus::UnderReview, MaintenanceRequestStatus::Approved],
                    strict: true,
                ) && $this->record->work_order_id === null)
                ->form([
                    Select::make('preliminary_technician_id')
                        ->label('Técnico asignado')
                        ->options(User::query()->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->nullable()
                        ->default(fn (): ?string => $this->record->preliminary_technician_id)
                        ->helperText('Este técnico se asignará automáticamente al crear la OT.'),
                ])
                ->action(function (array $data): void {
                    $this->record->update(['preliminary_technician_id' => $data['preliminary_technician_id']]);
                    $this->record->refresh();

                    Notification::make()
                        ->title($data['preliminary_technician_id']
                            ? 'Técnico asignado correctamente'
                            : 'Técnico removido')
                        ->success()
                        ->send();
                }),

            // Convert to Work Order
            Action::make('convert_to_wo')
                ->label('Convertir a OT')
                ->icon(Heroicon::OutlinedWrenchScrewdriver)
                ->color('success')
                ->modalHeading('Crear Orden de Trabajo')
                ->modalDescription('La OT se creará en estado Borrador vinculada a esta solicitud.')
                ->visible(fn (): bool => $this->record->status === MaintenanceRequestStatus::Approved
                    && $this->record->work_order_id === null)
                ->form([
                    Select::make('work_order_type')
                        ->label('Tipo de OT')
                        ->options(WorkOrderType::class)
                        ->required()
                        ->default(fn (): string => WorkOrderType::fromMaintenanceRequestType(
                            $this->record->request_type
                        )->value),
                    Select::make('assigned_supervisor')
                        ->label('Supervisor responsable')
                        ->options(User::query()->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->nullable(),
                    DateTimePicker::make('planned_start_at')
                        ->label('Inicio planificado')
                        ->nullable(),
                    DateTimePicker::make('planned_end_at')
                        ->label('Fin planificado')
                        ->nullable(),
                    Textarea::make('instructions')
                        ->label('Instrucciones adicionales')
                        ->rows(3)
                        ->nullable(),
                ])
                ->action(function (array $data, WorkOrderService $service): void {
                    /** @var MaintenanceRequest $mr */
                    $mr = $this->record;

                    $workOrder = $service->createFromMaintenanceRequest($mr, $data, auth()->user());

                    $this->record->refresh();

                    Notification::make()
                        ->title('OT creada: '.$workOrder->work_order_number)
                        ->success()
                        ->send();

                    $this->redirect(WorkOrderResource::getUrl('view', ['record' => $workOrder]));
                }),

            EditAction::make()
                ->visible(fn (): bool => $this->record->isEditable()),
            DeleteAction::make(),
            $this->getBackAction(),
        ];
    }

    private function transitionAndRefresh(
        MaintenanceRequestService $service,
        MaintenanceRequestStatus $toStatus,
        array $extra = [],
    ): MaintenanceRequest {
        /** @var MaintenanceRequest $request */
        $request = $this->record;

        $service->transition($request, $toStatus, auth()->user(), $extra);
        $this->record->refresh();

        Notification::make()
            ->title('Estado actualizado: '.$toStatus->label())
            ->success()
            ->send();

        return $request;
    }
}
