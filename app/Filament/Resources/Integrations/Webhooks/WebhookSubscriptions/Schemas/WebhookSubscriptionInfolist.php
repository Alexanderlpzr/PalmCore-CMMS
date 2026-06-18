<?php

namespace App\Filament\Resources\Integrations\Webhooks\WebhookSubscriptions\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WebhookSubscriptionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Configuración')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('url')
                            ->label('URL de destino')
                            ->copyable()
                            ->columnSpanFull(),

                        TextEntry::make('events')
                            ->label('Eventos suscritos')
                            // Same Filament v5 per-element behavior as the table column (see WebhookSubscriptionsTable).
                            ->formatStateUsing(fn ($state): string => implode("\n", is_array($state) ? $state : (json_decode($state, true) ?? [$state])))
                            ->columnSpanFull(),

                        IconEntry::make('is_active')
                            ->label('Estado')
                            ->boolean()
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('success')
                            ->falseColor('danger'),

                        TextEntry::make('failure_count')
                            ->label('Fallos consecutivos')
                            ->badge()
                            ->color(fn (int $state): string => $state >= 3 ? 'danger' : ($state > 0 ? 'warning' : 'gray')),

                        TextEntry::make('last_triggered_at')
                            ->label('Último delivery exitoso')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('—'),

                        TextEntry::make('last_error')
                            ->label('Último error')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),

                Section::make('Historial de entregas recientes')
                    ->schema([
                        TextEntry::make('recentLogs')
                            ->label('')
                            // Use getStateUsing to return a scalar string from PHP directly.
                            // formatStateUsing would pass the Collection through Livewire serialization,
                            // causing Alpine.js to call .includes() on null → 58 JS errors per load.
                            ->getStateUsing(fn ($record): string => $record->recentLogs
                                ->map(fn ($log) => "[{$log->delivered_at?->format('d/m H:i')}] {$log->event_name} — HTTP {$log->http_status} ({$log->status}) {$log->duration_ms}ms")
                                ->implode("\n")
                            )
                            ->placeholder('Sin registros')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
