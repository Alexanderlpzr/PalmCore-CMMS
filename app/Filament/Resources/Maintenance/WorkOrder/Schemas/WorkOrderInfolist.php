<?php

namespace App\Filament\Resources\Maintenance\WorkOrder\Schemas;

use App\Domain\Assets\Enums\IssueSeverity;
use App\Domain\Assets\Enums\StoppageCategory;
use App\Domain\Maintenance\Enums\FailureMode;
use App\Domain\Maintenance\Enums\MaintenanceArea;
use App\Domain\Maintenance\Enums\PlantProcess;
use App\Domain\Maintenance\Enums\WorkOrderPriority;
use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Domain\Maintenance\Enums\WorkOrderType;
use App\Filament\Resources\Maintenance\IssueReport\IssueReportResource;
use App\Models\WorkOrder;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WorkOrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identificación')
                    ->columns(3)
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
                        TextEntry::make('created_at')
                            ->label('Fecha')
                            ->date('d/m/Y'),
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
                        TextEntry::make('maintenance_area')
                            ->label('Área de Mtto')
                            ->badge()
                            ->placeholder('—')
                            ->color(fn (?MaintenanceArea $state): string => $state?->color() ?? 'gray')
                            ->formatStateUsing(fn (?MaintenanceArea $state): ?string => $state?->label()),
                        TextEntry::make('process')
                            ->label('Proceso')
                            ->placeholder('—')
                            ->formatStateUsing(fn (?PlantProcess $state): ?string => $state?->label()),
                        TextEntry::make('executed_by')
                            ->label('Ejecutante(s)')
                            ->placeholder('—'),
                        TextEntry::make('meter_reading')
                            ->label('Horómetro')
                            ->placeholder('—')
                            ->suffix(' h'),
                    ]),

                Section::make('Equipo')
                    ->columns(3)
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

                Section::make('Trabajo y falla')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('title')->label('Título')->columnSpanFull(),
                        TextEntry::make('description')->label('Descripción')->columnSpanFull(),
                        TextEntry::make('instructions')
                            ->label('Instrucciones')
                            ->columnSpanFull()
                            ->visible(fn (WorkOrder $record): bool => filled($record->instructions)),
                        TextEntry::make('work_performed')->label('Trabajo realizado')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('failure_cause')->label('Causa de la falla')->placeholder('—'),
                        TextEntry::make('root_cause')->label('Causa raíz')->placeholder('—'),
                        TextEntry::make('failure_mode')
                            ->label('Modo de falla')
                            ->badge()
                            ->placeholder('—')
                            ->formatStateUsing(fn (?FailureMode $state): ?string => $state?->label())
                            ->visible(fn (WorkOrder $record): bool => $record->failure_mode !== null),
                        TextEntry::make('diagnosed_stoppage_category')
                            ->label('Tipo I diagnosticado')
                            ->badge()
                            ->placeholder('—')
                            ->formatStateUsing(fn (?StoppageCategory $state): ?string => $state?->label())
                            ->visible(fn (WorkOrder $record): bool => $record->diagnosed_stoppage_category !== null),
                    ]),

                Section::make('Costo')
                    ->schema([
                        TextEntry::make('actual_cost_total')
                            ->label('Costo de la OT')
                            ->money('COP')
                            ->placeholder('—')
                            ->helperText('Alimenta los gastos del presupuesto al cerrar la OT.'),
                    ]),

                Section::make('Seguimiento')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('createdBy.name')->label('Creado por')->placeholder('—'),
                        TextEntry::make('created_at')->label('Creado el')->dateTime('d/m/Y H:i'),
                        TextEntry::make('closed_at')->label('Cerrada el')->dateTime('d/m/Y H:i')->placeholder('—'),
                    ]),

                // Trazabilidad inversa: si la OT nació de un reporte de novedad, se
                // muestra el reporte y un enlace para ir a él.
                Section::make('Reporte de origen')
                    ->description('Esta orden nació de un reporte de novedad.')
                    ->visible(fn (WorkOrder $record): bool => $record->issue_report_id !== null)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('issueReport.reporter_name')
                            ->label('Reportado por')
                            ->placeholder('—'),
                        TextEntry::make('issueReport.severity')
                            ->label('Severidad')
                            ->badge()
                            ->placeholder('—')
                            ->formatStateUsing(fn (?IssueSeverity $state): string => $state?->label() ?? '—')
                            ->color(fn (?IssueSeverity $state): string => $state?->color() ?? 'gray'),
                        TextEntry::make('issueReport.description')
                            ->label('Novedad reportada')
                            ->columnSpanFull()
                            ->placeholder('—'),
                        TextEntry::make('issueReport.created_at')
                            ->label('Fecha del reporte')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('—'),
                        TextEntry::make('issue_report_link')
                            ->label('Trazabilidad')
                            ->state('Ver reporte de novedad →')
                            ->url(fn (WorkOrder $record): ?string => $record->issue_report_id !== null
                                ? IssueReportResource::getUrl('view', ['record' => $record->issue_report_id])
                                : null)
                            ->openUrlInNewTab()
                            ->color('primary'),
                    ]),
            ]);
    }
}
