<?php

namespace App\Filament\Resources\Alerts\Alert\Schemas;

use App\Domain\Alerts\Enums\AlertCategory;
use App\Domain\Alerts\Enums\AlertSeverity;
use App\Domain\Alerts\Enums\AlertStatus;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AlertInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Alerta')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('severity')
                            ->label('Severidad')
                            ->badge()
                            ->color(fn (AlertSeverity $state): string => $state->color())
                            ->formatStateUsing(fn (AlertSeverity $state): string => $state->label()),

                        TextEntry::make('category')
                            ->label('Categoría')
                            ->badge()
                            ->color(fn (AlertCategory $state): string => $state->color())
                            ->formatStateUsing(fn (AlertCategory $state): string => $state->label()),

                        TextEntry::make('status')
                            ->label('Estado')
                            ->badge()
                            ->color(fn (AlertStatus $state): string => $state->color())
                            ->formatStateUsing(fn (AlertStatus $state): string => $state->label()),

                        TextEntry::make('title')
                            ->label('Título')
                            ->columnSpanFull()
                            ->weight('bold'),

                        TextEntry::make('message')
                            ->label('Mensaje')
                            ->columnSpanFull()
                            ->placeholder('Sin mensaje adicional.'),

                        TextEntry::make('entity_type')
                            ->label('Tipo de entidad')
                            ->placeholder('—'),

                        TextEntry::make('entity_id')
                            ->label('ID de entidad')
                            ->copyable()
                            ->placeholder('—'),

                        TextEntry::make('created_at')
                            ->label('Generada')
                            ->dateTime('d/m/Y H:i'),
                    ]),

                Section::make('Cierre')
                    ->columns(3)
                    ->visible(fn ($record): bool => $record->status !== AlertStatus::Open)
                    ->schema([
                        TextEntry::make('status')
                            ->label('Resultado')
                            ->badge()
                            ->color(fn (AlertStatus $state): string => $state->color())
                            ->formatStateUsing(fn (AlertStatus $state): string => $state->label()),

                        TextEntry::make('closedBy.name')
                            ->label('Cerrada por')
                            ->placeholder('Sistema'),

                        TextEntry::make('closed_at')
                            ->label('Fecha de cierre')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('—'),
                    ]),

                Section::make('Metadatos')
                    ->collapsible()
                    ->collapsed()
                    ->visible(fn ($record): bool => ! empty($record->metadata))
                    ->schema([
                        KeyValueEntry::make('metadata')
                            ->label(''),
                    ]),
            ]);
    }
}
