<?php

namespace App\Filament\Resources\Maintenance\IssueReport\Schemas;

use App\Domain\Assets\Enums\IssueSeverity;
use App\Domain\Maintenance\Enums\IssueReportStatus;
use App\Domain\Maintenance\Enums\WorkOrderStatus;
use App\Models\EquipmentIssueReport;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class IssueReportInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Reporte')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('equipment.code')
                            ->label('Equipo (código)'),
                        TextEntry::make('equipment.name')
                            ->label('Equipo (nombre)'),
                        TextEntry::make('severity')
                            ->label('Severidad')
                            ->badge()
                            ->color(fn (IssueSeverity $state): string => $state->color())
                            ->formatStateUsing(fn (IssueSeverity $state): string => $state->label()),
                        TextEntry::make('status')
                            ->label('Estado')
                            ->badge()
                            ->color(fn (IssueReportStatus $state): string => $state->color())
                            ->formatStateUsing(fn (IssueReportStatus $state): string => $state->label()),
                        TextEntry::make('description')
                            ->label('Descripción')
                            ->columnSpanFull(),
                        ImageEntry::make('photo_path')
                            ->label('Foto')
                            ->disk(persistent_disk())
                            ->height(220)
                            ->columnSpanFull()
                            ->visible(fn (EquipmentIssueReport $record): bool => (bool) $record->photo_path),
                    ]),

                Section::make('Reportante')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('reporter_name')
                            ->label('Nombre')
                            ->placeholder('—'),
                        TextEntry::make('reporter_position')
                            ->label('Cargo')
                            ->placeholder('—'),
                        TextEntry::make('reporter.name')
                            ->label('Usuario registrado')
                            ->placeholder('—'),
                    ]),

                Section::make('Seguimiento')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('acknowledgedBy.name')
                            ->label('Reconocido por')
                            ->placeholder('Pendiente'),
                        TextEntry::make('acknowledged_at')
                            ->label('Reconocido el')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('—'),
                        TextEntry::make('admin_notes')
                            ->label('Notas internas')
                            ->placeholder('—')
                            ->columnSpanFull(),
                        TextEntry::make('created_at')
                            ->label('Fecha de reporte')
                            ->dateTime('d/m/Y H:i'),
                    ]),

                // Trazabilidad reporte → OT → solución: la OT que atendió el reporte y,
                // cuando se completó, lo que se hizo.
                Section::make('Orden de trabajo')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('workOrder.work_order_number')
                            ->label('OT')
                            ->placeholder('Sin OT — usa «Crear OT»'),
                        TextEntry::make('workOrder.status')
                            ->label('Estado de la OT')
                            ->badge()
                            ->placeholder('—')
                            ->formatStateUsing(fn (?WorkOrderStatus $state): string => $state?->label() ?? '—')
                            ->color(fn (?WorkOrderStatus $state): string => $state?->color() ?? 'gray'),
                        TextEntry::make('workOrder.work_performed')
                            ->label('Solución (lo que se hizo)')
                            ->placeholder('Pendiente')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
