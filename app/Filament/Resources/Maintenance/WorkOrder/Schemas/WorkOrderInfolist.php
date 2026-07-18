<?php

namespace App\Filament\Resources\Maintenance\WorkOrder\Schemas;

use App\Domain\Maintenance\Enums\FailureMode;
use App\Domain\Maintenance\Enums\WorkOrderPriority;
use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Domain\Maintenance\Enums\WorkOrderType;
use App\Models\WorkOrder;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WorkOrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                ViewEntry::make('status_timeline')
                    ->hiddenLabel()
                    ->view('filament.infolists.work-order-status-timeline')
                    ->columnSpanFull(),

                // ->label('') no basta para ocultar el título: Entry::getLabel()
                // trata la cadena vacía como "sin label" y genera uno a partir del
                // nombre del campo — «Missing technician alert» apareciendo arriba
                // de esta misma alerta era ese defecto. hiddenLabel() sí lo suprime.
                TextEntry::make('missing_technician_alert')
                    ->hiddenLabel()
                    ->badge()
                    ->columnSpanFull()
                    ->visible(fn (WorkOrder $record): bool => $record->status === WorkOrderStatus::Draft
                        && $record->technicians()->doesntExist())
                    ->getStateUsing(fn (): string => '⚠ Falta asignar un técnico para poder planificar esta OT — agrégalo en la pestaña "Técnicos" de abajo.')
                    ->color('danger'),

                TextEntry::make('pending_verification_alert')
                    ->hiddenLabel()
                    ->badge()
                    ->columnSpanFull()
                    ->visible(fn (WorkOrder $record): bool => $record->status->isPendingVerification())
                    ->getStateUsing(fn (): string => '🕓 En revisión — el técnico ya firmó, pero falta que el supervisor verifique el trabajo para cerrar esta OT.')
                    ->color('warning'),

                Section::make('Identificación')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('work_order_number')
                            ->label('Número de OT')
                            ->copyable()
                            ->weight('bold'),
                        TextEntry::make('status')
                            ->label('Estado')
                            ->badge()
                            ->color(fn (WorkOrderStatus $state): string => $state->color())
                            ->formatStateUsing(fn (WorkOrderStatus $state): string => $state->label()),
                        TextEntry::make('work_order_type')
                            ->label('Tipo')
                            ->badge()
                            ->color(fn (WorkOrderType $state): string => $state->color())
                            ->formatStateUsing(fn (WorkOrderType $state): string => $state->label()),
                        TextEntry::make('priority')
                            ->label('Prioridad')
                            ->badge()
                            ->color(fn (WorkOrderPriority $state): string => $state->color())
                            ->formatStateUsing(fn (WorkOrderPriority $state): string => $state->label()),
                    ]),

                Section::make('Equipo')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('equipment.code')->label('Código'),
                        TextEntry::make('equipment.name')->label('Nombre'),
                        TextEntry::make('plant.name')->label('Planta')->placeholder('—'),
                        TextEntry::make('area.name')->label('Área')->placeholder('—'),
                        IconEntry::make('equipment_stopped')
                            ->label('Equipo detenido')
                            ->boolean()
                            ->trueColor('danger'),
                        TextEntry::make('downtime_minutes')
                            ->label('Tiempo de paro')
                            ->formatStateUsing(fn (?int $state): ?string => format_hours_minutes($state !== null ? $state / 60 : null))
                            ->placeholder('—'),
                    ]),

                Section::make('Contenido')
                    ->schema([
                        TextEntry::make('title')->label('Título'),
                        TextEntry::make('description')->label('Descripción')->columnSpanFull(),
                        TextEntry::make('instructions')->label('Instrucciones')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('failure_cause')->label('Causa de la falla')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('failure_mode')
                            ->label('Modo de falla')
                            ->badge()
                            ->placeholder('—')
                            ->formatStateUsing(fn ($state): string => $state instanceof FailureMode ? $state->label() : (string) $state)
                            ->visible(fn (WorkOrder $record): bool => $record->failure_mode !== null),
                        TextEntry::make('work_performed')->label('Trabajo realizado')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('root_cause')->label('Causa raíz')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('rejection_reason')
                            ->label('Motivo de rechazo')
                            ->placeholder('—')
                            ->visible(fn (WorkOrder $record): bool => $record->rejection_reason !== null),
                    ]),

                Section::make('Planificación y Ejecución')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('planned_start_at')->label('Inicio planif.')->dateTime('d/m/Y H:i')->placeholder('—'),
                        TextEntry::make('planned_end_at')->label('Fin planif.')->dateTime('d/m/Y H:i')->placeholder('—'),
                        TextEntry::make('actual_start_at')->label('Inicio real')->dateTime('d/m/Y H:i')->placeholder('—'),
                        TextEntry::make('actual_end_at')->label('Fin real')->dateTime('d/m/Y H:i')->placeholder('—'),
                        TextEntry::make('planned_labor_hours')
                            ->label('Horas planif.')
                            ->getStateUsing(fn (WorkOrder $record): ?string => format_hours_minutes($record->plannedHours()))
                            ->placeholder('—'),
                        TextEntry::make('actual_labor_hours')
                            ->label('Horas reales')
                            ->getStateUsing(fn (WorkOrder $record): ?string => format_hours_minutes($record->actualHours()))
                            ->placeholder('—'),
                    ]),

                Section::make('Costos')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('estimated_cost')->label('Estimado')->money('COP')->placeholder('—'),
                        TextEntry::make('actual_cost_total')->label('Total real')->money('COP')->placeholder('—'),
                        TextEntry::make('cost_variance')
                            ->label('Desviación (real − estimado)')
                            ->badge()
                            ->placeholder('—')
                            ->getStateUsing(function (WorkOrder $record): ?string {
                                $variance = $record->costVariance();

                                if ($variance === null) {
                                    return null;
                                }

                                $pct = $record->costVariancePercentage();
                                $sign = $variance > 0 ? '+' : ($variance < 0 ? '−' : '');
                                $amount = number_format(abs($variance), 0, ',', '.');
                                $suffix = $pct !== null ? ' ('.($pct > 0 ? '+' : '').$pct.'%)' : '';

                                return $sign.'$'.$amount.$suffix;
                            })
                            ->color(fn (WorkOrder $record): string => match (true) {
                                $record->costVariance() === null => 'gray',
                                $record->costVariance() > 0 => 'danger',   // over budget
                                $record->costVariance() < 0 => 'success',  // under budget
                                default => 'gray',
                            })
                            ->tooltip(fn (WorkOrder $record): ?string => match (true) {
                                $record->costVariance() === null => null,
                                $record->costVariance() > 0 => 'La OT superó el costo estimado (sobrecosto).',
                                $record->costVariance() < 0 => 'La OT costó menos de lo estimado (ahorro).',
                                default => 'La OT terminó exactamente en el costo estimado.',
                            }),
                        TextEntry::make('actual_cost_labor')->label('Mano de obra')->money('COP')->placeholder('—'),
                        TextEntry::make('actual_cost_parts')->label('Repuestos')->money('COP')->placeholder('—'),
                        TextEntry::make('actual_cost_external')->label('Externo')->money('COP')->placeholder('—'),
                    ]),

                Section::make('Seguimiento')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('createdBy.name')->label('Creado por'),
                        TextEntry::make('created_at')->label('Creado el')->dateTime('d/m/Y H:i'),
                        TextEntry::make('assignedSupervisor.name')->label('Supervisor')->placeholder('Sin asignar'),
                        TextEntry::make('completedBy.name')->label('Completado por')->placeholder('—'),
                        TextEntry::make('completed_at')->label('Completado el')->dateTime('d/m/Y H:i')->placeholder('—'),
                        TextEntry::make('verifiedBy.name')->label('Verificado por')->placeholder('—'),
                        TextEntry::make('verified_at')->label('Verificado el')->dateTime('d/m/Y H:i')->placeholder('—'),
                        TextEntry::make('maintenanceRequest.request_number')->label('Solicitud origen')->placeholder('—'),
                    ]),
            ]);
    }
}
