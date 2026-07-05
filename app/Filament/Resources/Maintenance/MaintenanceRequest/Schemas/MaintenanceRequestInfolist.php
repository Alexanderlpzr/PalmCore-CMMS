<?php

namespace App\Filament\Resources\Maintenance\MaintenanceRequest\Schemas;

use App\Domain\Maintenance\Enums\MaintenanceRequestPriority;
use App\Domain\Maintenance\Enums\MaintenanceRequestStatus;
use App\Domain\Maintenance\Enums\MaintenanceRequestType;
use App\Models\MaintenanceRequest;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MaintenanceRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identificación')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('request_number')
                            ->label('Número de solicitud')
                            ->copyable(),
                        TextEntry::make('status')
                            ->label('Estado')
                            ->badge()
                            ->color(fn (MaintenanceRequestStatus $state): string => $state->color())
                            ->formatStateUsing(fn (MaintenanceRequestStatus $state): string => $state->label()),
                        TextEntry::make('request_type')
                            ->label('Tipo')
                            ->badge()
                            ->color(fn (MaintenanceRequestType $state): string => $state->color())
                            ->formatStateUsing(fn (MaintenanceRequestType $state): string => $state->label()),
                        TextEntry::make('priority')
                            ->label('Prioridad')
                            ->badge()
                            ->color(fn (MaintenanceRequestPriority $state): string => $state->color())
                            ->formatStateUsing(fn (MaintenanceRequestPriority $state): string => $state->label()),
                    ]),

                Section::make('Equipo')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('equipment.code')
                            ->label('Código'),
                        TextEntry::make('equipment.name')
                            ->label('Nombre'),
                        TextEntry::make('equipment.plant.name')
                            ->label('Planta')
                            ->placeholder('—'),
                        TextEntry::make('equipment.area.name')
                            ->label('Área')
                            ->placeholder('—'),
                    ]),

                Section::make('Contenido')
                    ->schema([
                        TextEntry::make('title')
                            ->label('Título'),
                        TextEntry::make('description')
                            ->label('Descripción')
                            ->columnSpanFull(),
                        TextEntry::make('requested_due_date')
                            ->label('Fecha límite solicitada')
                            ->date('d/m/Y')
                            ->placeholder('Sin fecha límite'),
                        TextEntry::make('rejection_reason')
                            ->label('Motivo de rechazo')
                            ->placeholder('—')
                            ->visible(fn (MaintenanceRequest $record): bool => $record->rejection_reason !== null),
                    ]),

                Section::make('Seguimiento')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('createdBy.name')
                            ->label('Creado por'),
                        TextEntry::make('created_at')
                            ->label('Creado el')
                            ->dateTime('d/m/Y H:i'),
                        TextEntry::make('assignedReviewer.name')
                            ->label('Revisor asignado')
                            ->placeholder('Sin asignar'),
                        TextEntry::make('submitted_at')
                            ->label('Enviado el')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('—'),
                        TextEntry::make('approvedBy.name')
                            ->label('Aprobado por')
                            ->placeholder('—'),
                        TextEntry::make('approved_at')
                            ->label('Aprobado el')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('—'),
                        TextEntry::make('issueReport.id')
                            ->label('Reporte de origen')
                            ->placeholder('Sin reporte asociado'),
                    ]),
            ]);
    }
}
