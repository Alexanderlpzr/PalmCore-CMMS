<?php

namespace App\Filament\Resources\Maintenance\MaintenanceRequest\Pages;

use App\Domain\Maintenance\Enums\MaintenanceRequestStatus;
use App\Domain\Maintenance\Enums\TechnicianRole;
use App\Domain\Maintenance\Enums\WorkOrderStatus;
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
            // Submit: Draft → Submitted (fallback for records created outside the
            // normal flow — new requests skip this automatically on creation)
            Action::make('submit')
                ->label('Enviar para revisión')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->color('info')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record->status === MaintenanceRequestStatus::Draft
                    && auth()->user()->can('review', $this->record))
                ->action(fn (MaintenanceRequestService $service): MaintenanceRequest => $this->transitionAndRefresh(
                    $service, MaintenanceRequestStatus::Submitted
                )),

            // Assign to review: Submitted → UnderReview (same fallback as above)
            Action::make('assign_reviewer')
                ->label('Tomar para revisión')
                ->icon(Heroicon::OutlinedEye)
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record->status === MaintenanceRequestStatus::Submitted
                    && auth()->user()->can('review', $this->record))
                ->action(fn (MaintenanceRequestService $service): MaintenanceRequest => $this->transitionAndRefresh(
                    $service, MaintenanceRequestStatus::UnderReview
                )),

            // Approve & create OT in one step: UnderReview → Approved → Converted
            Action::make('approve_and_create_wo')
                ->label('Aprobar y Crear OT')
                ->icon(Heroicon::OutlinedCheckBadge)
                ->color('success')
                ->modalHeading('Aprobar solicitud y crear Orden de Trabajo')
                ->modalDescription('La solicitud quedará aprobada y la OT se creará ya planificada y lista para que el técnico inicie el trabajo.')
                ->visible(fn (): bool => $this->record->status === MaintenanceRequestStatus::UnderReview
                    && auth()->user()->can('approve', $this->record)
                    && auth()->user()->can('convert', $this->record))
                ->form([
                    Select::make('technician_ids')
                        ->label('Técnico(s) asignado(s)')
                        ->options(User::query()->operationalStaff()->orderBy('name')->pluck('name', 'id'))
                        ->multiple()
                        ->searchable()
                        ->required()
                        ->default(fn (): array => $this->record->preliminary_technician_id
                            ? [$this->record->preliminary_technician_id]
                            : [])
                        ->helperText('No es posible crear la Orden de Trabajo sin al menos un técnico asignado.'),
                    Select::make('work_order_type')
                        ->label('Tipo de OT')
                        ->options(WorkOrderType::class)
                        ->required()
                        ->default(fn (): string => WorkOrderType::fromMaintenanceRequestType(
                            $this->record->request_type
                        )->value),
                    Select::make('assigned_supervisor')
                        ->label('Supervisor responsable')
                        ->options(User::query()->operationalStaff()->orderBy('name')->pluck('name', 'id'))
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
                ->action(function (array $data, MaintenanceRequestService $mrService, WorkOrderService $woService): void {
                    /** @var MaintenanceRequest $mr */
                    $mr = $this->record;

                    $technicianIds = $data['technician_ids'];
                    unset($data['technician_ids']);

                    $mrService->transition($mr, MaintenanceRequestStatus::Approved, auth()->user());

                    // Explicit multi-technician selection replaces the preliminary single-technician hint.
                    $mr->update(['preliminary_technician_id' => null]);

                    $workOrder = $woService->createFromMaintenanceRequest($mr, $data, auth()->user());

                    foreach ($technicianIds as $userId) {
                        $technician = User::find($userId);
                        if ($technician) {
                            $woService->assignTechnician($workOrder, $technician, TechnicianRole::Technician);
                        }
                    }

                    // Technicians are already assigned above, so the OT can go straight to
                    // Planned — no need for a separate manual "Planificar" click.
                    $woService->transition($workOrder, WorkOrderStatus::Planned, auth()->user());

                    $this->record->refresh();

                    Notification::make()
                        ->title('Solicitud aprobada y OT planificada: '.$workOrder->work_order_number)
                        ->success()
                        ->send();

                    $this->redirect(WorkOrderResource::getUrl('view', ['record' => $workOrder]));
                }),

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
                ->visible(fn (): bool => $this->record->status === MaintenanceRequestStatus::UnderReview
                    && auth()->user()->can('review', $this->record))
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

            // Assign preliminary technician (before deciding to approve — optional early planning)
            Action::make('assign_technician')
                ->label('Asignar técnico')
                ->icon(Heroicon::OutlinedUserPlus)
                ->color('info')
                ->modalHeading('Asignar técnico preliminar')
                ->modalDescription('Selecciona el técnico sugerido para ejecutar el trabajo. Se precargará al aprobar la solicitud, pero puedes cambiarlo en ese momento.')
                ->visible(fn (): bool => $this->record->status === MaintenanceRequestStatus::UnderReview
                    && $this->record->work_order_id === null
                    && auth()->user()->can('review', $this->record))
                ->form([
                    Select::make('preliminary_technician_id')
                        ->label('Técnico asignado')
                        ->options(User::query()->operationalStaff()->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->nullable()
                        ->default(fn (): ?string => $this->record->preliminary_technician_id),
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
