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
                            ->formatStateUsing(fn (array $state): string => implode("\n", $state))
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
                            ->formatStateUsing(fn ($state): string => $state
                                ->map(fn ($log) => "[{$log->delivered_at?->format('d/m H:i')}] {$log->event_name} — HTTP {$log->http_status} ({$log->status}) {$log->duration_ms}ms")
                                ->implode("\n")
                            )
                            ->placeholder('Sin registros')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
