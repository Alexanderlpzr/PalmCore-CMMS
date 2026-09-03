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
use Filament\Infolists\Components\ImageEntry;
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
                            ->label('Clase de mantenimiento')
                            ->badge()
                            ->placeholder('—')
                            ->color(fn (?MaintenanceArea $state): string => $state?->color() ?? 'gray')
                            ->formatStateUsing(fn (?MaintenanceArea $state): ?string => $state?->label()),
                        TextEntry::make('process')
                            ->label('Proceso')
                            ->placeholder('—')
                            ->formatStateUsing(fn (?PlantProcess $state): ?string => $state?->label()),
                        TextEntry::make('executed_by')
                            ->label('Responsable(s)')
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
                        TextEntry::make('area.name')->label('Sección')->placeholder('—'),
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
                        // Solo se llenan cuando el modo de falla quedó en «Otro». La
                        // causa raíz ya no se pide al cerrar; se conserva aquí para
                        // las OT viejas que sí la tienen.
                        TextEntry::make('failure_cause')
                            ->label('Causa de la falla')
                            ->visible(fn (WorkOrder $record): bool => filled($record->failure_cause)),
                        TextEntry::make('root_cause')
                            ->label('Causa raíz')
                            ->visible(fn (WorkOrder $record): bool => filled($record->root_cause)),
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

                // El antes y el después, lado a lado: es la comparación que da
                // trazabilidad visual del trabajo sin tener que leer la OT.
                Section::make('Registro fotográfico')
                    ->columns(2)
                    ->visible(fn (WorkOrder $record): bool => $record->hasPhotos())
                    ->schema([
                        // Sin `stacked()`: apiladas se superponen y aquí lo que se viene
                        // a hacer es comparar, así que van una al lado de otra aunque
                        // ocupen más.
                        ImageEntry::make('before_photos')
                            ->label(fn (WorkOrder $record): string => 'Antes'
                                .self::countSuffix($record->before_photos))
                            ->disk(persistent_disk())
                            ->height(240)
                            ->placeholder('Sin fotos del antes'),
                        ImageEntry::make('after_photos')
                            ->label(fn (WorkOrder $record): string => 'Después'
                                .self::countSuffix($record->after_photos))
                            ->disk(persistent_disk())
                            ->height(240)
                            ->placeholder('Sin fotos del después'),
                    ]),

                Section::make('Costo')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('actual_cost_labor')
                            ->label('Mano de obra')
                            ->money('COP')
                            ->placeholder('—'),
                        TextEntry::make('actual_cost_parts')
                            ->label('Repuesto')
                            ->money('COP')
                            ->placeholder('—'),
                        TextEntry::make('actual_cost_consumables')
                            ->label('Consumible')
                            ->money('COP')
                            ->placeholder('—'),
                        TextEntry::make('actual_cost_total')
                            ->label('Total')
                            ->money('COP')
                            ->placeholder('—')
                            ->weight('bold')
                            ->helperText('Alimenta los gastos del presupuesto al cerrar la OT.'),

                        // Al lado del costo, que es contra lo que se lee: qué se cotizó
                        // frente a qué terminó costando.
                        TextEntry::make('quoteAttachment.file_name')
                            ->label('Soporte de cotización')
                            ->columnSpanFull()
                            ->icon('heroicon-o-document-arrow-down')
                            ->color('info')
                            ->url(fn (WorkOrder $record): ?string => $record->quoteAttachment !== null
                                ? route('work-order-attachments.download', $record->quoteAttachment)
                                : null)
                            ->openUrlInNewTab()
                            ->visible(fn (WorkOrder $record): bool => $record->quoteAttachment !== null),
                    ]),

                Section::make('Seguimiento')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('planned_start_at')->label('Fecha planificada')->date('d/m/Y')->placeholder('—'),
                        TextEntry::make('actual_end_at')->label('Fecha ejecutada')->date('d/m/Y')->placeholder('—'),
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
                            ->label('Criticidad')
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

    /**
     * El «(3)» que va detrás de «Antes» cuando hay más de una foto.
     *
     * Con una sola no se pone nada: un contador que siempre dice «(1)» es ruido, y lo
     * que interesa saber de un vistazo es si hay varias y hay que desplazarse.
     *
     * @param  array<int, string>|null  $photos
     */
    private static function countSuffix(?array $photos): string
    {
        $count = count($photos ?? []);

        return $count > 1 ? " ({$count})" : '';
    }
}
