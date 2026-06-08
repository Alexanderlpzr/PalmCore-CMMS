<?php

namespace App\Filament\Resources\Maintenance\WorkOrder\Schemas;

use App\Domain\Maintenance\Enums\WorkOrderPriority;
use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Domain\Maintenance\Enums\WorkOrderType;
use App\Models\WorkOrder;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class WorkOrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
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
                            ->color(fn (WorkOrderStatus $state): string => $state->color()),
                        TextEntry::make('work_order_type')
                            ->label('Tipo')
                            ->badge()
                            ->color(fn (WorkOrderType $state): string => $state->color()),
                        TextEntry::make('priority')
                            ->label('Prioridad')
                            ->badge()
                            ->color(fn (WorkOrderPriority $state): string => $state->color()),
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
                            ->suffix(' min')
                            ->placeholder('—'),
                    ]),

                Section::make('Contenido')
                    ->schema([
                        TextEntry::make('title')->label('Título'),
                        TextEntry::make('description')->label('Descripción')->columnSpanFull(),
                        TextEntry::make('instructions')->label('Instrucciones')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('failure_cause')->label('Causa de la falla')->placeholder('—')->columnSpanFull(),
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
                        TextEntry::make('planned_labor_hours')->label('Horas planif.')->suffix(' h')->placeholder('—'),
                        TextEntry::make('actual_labor_hours')->label('Horas reales')->suffix(' h')->placeholder('—'),
                    ]),

                Section::make('Costos')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('estimated_cost')->label('Estimado')->money('COP')->placeholder('—'),
                        TextEntry::make('actual_cost_total')->label('Total real')->money('COP')->placeholder('—'),
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
