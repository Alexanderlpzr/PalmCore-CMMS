<?php

namespace App\Filament\Resources\Maintenance\IssueReport\Schemas;

use App\Domain\Assets\Enums\IssueSeverity;
use App\Domain\Maintenance\Enums\IssueReportStatus;
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
                    ]),

                Section::make('Reportante')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('reporter_name')
                            ->label('Nombre')
                            ->placeholder('Anónimo'),
                        TextEntry::make('reporter_phone')
                            ->label('Teléfono')
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
                        TextEntry::make('maintenanceRequest.request_number')
                            ->label('Solicitud de mantenimiento')
                            ->placeholder('Sin convertir'),
                        TextEntry::make('created_at')
                            ->label('Fecha de reporte')
                            ->dateTime('d/m/Y H:i'),
                    ]),
            ]);
    }
}
