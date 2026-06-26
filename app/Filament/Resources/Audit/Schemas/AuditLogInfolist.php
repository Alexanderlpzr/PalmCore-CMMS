<?php

namespace App\Filament\Resources\Audit\Schemas;

use App\Models\AuditLog;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AuditLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Detalles del Evento')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('event')
                            ->label('Acción')
                            ->badge()
                            ->formatStateUsing(fn (AuditLog $record): string => $record->eventLabel())
                            ->color(fn (AuditLog $record): string => $record->eventColor()),

                        TextEntry::make('created_at')
                            ->label('Fecha')
                            ->dateTime('d/m/Y H:i:s'),

                        TextEntry::make('auditable_type')
                            ->label('Modelo')
                            ->formatStateUsing(fn (string $state): string => class_basename($state)),

                        TextEntry::make('auditable_id')
                            ->label('ID del Registro'),

                        TextEntry::make('user.name')
                            ->label('Usuario')
                            ->placeholder('Sistema'),

                        TextEntry::make('tenant.name')
                            ->label('Empresa')
                            ->placeholder('—'),

                        TextEntry::make('ip_address')
                            ->label('Dirección IP')
                            ->placeholder('—'),

                        TextEntry::make('user_agent')
                            ->label('Navegador / Agente')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),

                Grid::make(2)
                    ->schema([
                        Section::make('Valores Anteriores')
                            ->schema([
                                KeyValueEntry::make('old_values')
                                    ->hiddenLabel()
                                    ->placeholder('—'),
                            ]),

                        Section::make('Valores Nuevos')
                            ->schema([
                                KeyValueEntry::make('new_values')
                                    ->hiddenLabel()
                                    ->placeholder('—'),
                            ]),
                    ]),
            ]);
    }
}
